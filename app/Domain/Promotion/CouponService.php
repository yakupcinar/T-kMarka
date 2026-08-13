<?php

namespace App\Domain\Promotion;

use App\Enums\CouponType;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Kupon uygulama ve tüketme. (2A)
 *
 * ★ İKİ AYRI AN, KARIŞTIRILMAMALI:
 *
 *   uygula()   sepette — indirim HESAPLANIR, kota HARCANMAZ
 *   tuket()    siparişte — kota HARCANIR, kullanım kaydı açılır
 *
 * ⚠️ Sepette harcansaydı, kuponu deneyip vazgeçen her müşteri kotadan bir
 * kullanım yerdi ve kampanya hiç satış olmadan tükenirdi.
 */
class CouponService
{
    public function __construct(private readonly DiscountCalculator $hesap) {}

    /**
     * Kuponu sepete uygular — yalnızca doğrular, kota harcamaz.
     *
     * @throws InvalidCouponException
     */
    public function uygula(Cart $sepet, string $kod, string $urunToplami, ?Customer $musteri = null): Coupon
    {
        $kupon = $this->bul($kod);

        $this->dogrula($kupon, $urunToplami, $musteri);

        $sepet->coupon_code = $kupon->code;
        $sepet->save();

        return $kupon;
    }

    public function kaldir(Cart $sepet): void
    {
        $sepet->coupon_code = null;
        $sepet->save();
    }

    /**
     * @throws InvalidCouponException
     */
    public function bul(string $kod): Coupon
    {
        /*
        | ⚠️ Kod NORMALLEŞTİRİLİYOR: Türkçe büyütme tuzağı (1B'de
        | ölçülmüştü). `indirim` → `İNDİRİM` olsaydı müşterinin yazdığı
        | `INDIRIM` bulunamazdı.
        */
        $normal = CouponCode::normallestir($kod);

        $kupon = $normal === null ? null : Coupon::where('code', $normal)->first();

        if ($kupon === null) {
            throw new InvalidCouponException('Kupon bulunamadı.');
        }

        return $kupon;
    }

    /**
     * Kupon bu sepette geçerli mi?
     *
     * @throws InvalidCouponException
     */
    public function dogrula(Coupon $kupon, string $urunToplami, ?Customer $musteri = null): void
    {
        if (! $kupon->yururlukteMi()) {
            throw new InvalidCouponException('Kupon geçerli değil.');
        }

        if (bccomp($this->sayi($urunToplami), $this->sayi($kupon->min_subtotal), 2) < 0) {
            throw new InvalidCouponException('Sepet tutarı kupon için yeterli değil.');
        }

        /*
        | ⚠️ Müşteri başına sınır MİSAFİRDE UYGULANAMIYOR: kimlik yok.
        | Sessizce uygulanmış sayılsaydı marka "kişi başı 1" derken
        | misafirler sınırsız kullanırdı — dürüst olan, sınırın yalnızca
        | kayıtlı müşteride işlediğini bilmek.
        */
        if ($kupon->max_uses_per_customer !== null && $musteri !== null) {
            $kullanim = CouponRedemption::where('coupon_id', $kupon->id)
                ->where('customer_id', $musteri->id)
                ->count();

            if ($kullanim >= $kupon->max_uses_per_customer) {
                throw new InvalidCouponException('Bu kuponu daha fazla kullanamazsınız.');
            }
        }
    }

    /**
     * ★ 2A-K3 — KOTA SATIR KİLİDİYLE HARCANIYOR.
     *
     * ⚠️ 1D-K5'in tekrarı: "acaba kullanılmış mı" kontrolü yarışı ÇÖZMEZ.
     * Son bir kullanımı kalan kupon, aynı anda gelen iki istekte iki kez
     * kullanılırdı — ve hata vermezdi.
     *
     * ⚠️ Sipariş oluşturma transaction'ının İÇİNDEN çağrılıyor: sipariş
     * geri sarılırsa kota da geri sarılıyor.
     *
     * @throws InvalidCouponException
     */
    public function tuket(Order $siparis, string $kod, string $indirim): void
    {
        $normal = CouponCode::normallestir($kod);

        if ($normal === null) {
            return;
        }

        /** @var Coupon|null $kupon */
        $kupon = Coupon::where('code', $normal)->lockForUpdate()->first();

        if ($kupon === null) {
            throw new InvalidCouponException('Kupon bulunamadı.');
        }

        /*
        | ⚠️ Kota kontrolü KİLİTTEN SONRA tekrar yapılıyor. Sepette
        | doğrulanmıştı ama arada başka bir sipariş son kullanımı almış
        | olabilir.
        */
        if ($kupon->max_uses !== null && $kupon->used_count >= $kupon->max_uses) {
            throw new InvalidCouponException('Kupon kotası dolmuş.');
        }

        $kupon->used_count += 1;
        $kupon->save();

        $kayit = new CouponRedemption;
        $kayit->coupon()->associate($kupon);
        $kayit->order()->associate($siparis);
        $kayit->customer_id = $siparis->customer_id;
        $kayit->amount = $indirim;
        $kayit->save();
    }

    /**
     * Kuponun bu sepette yaratacağı indirim ve kargo etkisi.
     *
     * @return array{discount: numeric-string, free_shipping: bool}
     */
    public function etki(?string $kod, string $urunToplami): array
    {
        $normal = CouponCode::normallestir($kod);
        $kupon = $normal === null ? null : Coupon::where('code', $normal)->first();

        if ($kupon === null || ! $kupon->yururlukteMi()) {
            return ['discount' => '0.00', 'free_shipping' => false];
        }

        if (bccomp($this->sayi($urunToplami), $this->sayi($kupon->min_subtotal), 2) < 0) {
            return ['discount' => '0.00', 'free_shipping' => false];
        }

        return [
            'discount' => $this->hesap->indirim($kupon, $urunToplami),
            'free_shipping' => $kupon->type === CouponType::FreeShipping,
        ];
    }

    /**
     * Sayaç denetimi — `used_count` gerçekten kullanım sayısı kadar mı?
     *
     * ⚠️ `committed` sayacındaki (1D.5) dersin aynısı: materyalleştirilmiş
     * sayının bedeli denetimdir. ONARMIYOR, haber veriyor.
     *
     * @return list<array{code: string, used_count: int, redemptions: int}>
     */
    public function tutarsizliklar(): array
    {
        $satirlar = DB::table('coupons as k')
            ->leftJoin('coupon_redemptions as r', 'r.coupon_id', '=', 'k.id')
            ->groupBy('k.id', 'k.code', 'k.used_count')
            ->havingRaw('k.used_count <> COUNT(r.id)')
            ->select('k.code', 'k.used_count', DB::raw('COUNT(r.id) AS kullanim'))
            ->get();

        $sonuc = [];

        foreach ($satirlar as $satir) {
            $sonuc[] = [
                'code' => (string) $satir->code,
                'used_count' => (int) $satir->used_count,
                'redemptions' => (int) $satir->kullanim,
            ];
        }

        return $sonuc;
    }

    /** @return numeric-string */
    private function sayi(mixed $deger): string
    {
        return is_numeric($deger) ? (string) $deger : '0';
    }
}
