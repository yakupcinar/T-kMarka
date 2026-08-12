<?php

namespace App\Domain\Cart;

use App\Domain\Analytics\EventRecorder;
use App\Enums\CartStatus;
use App\Enums\EventType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sepet — oluşturma, satır ekleme, birleştirme. (1C-K1…K5)
 *
 * ⚠️ Sepette FİYAT YOK. Fiyat her okuyuşta varyanttan geliyor; marka
 * fiyatı değiştirirse sepette de değişir. Fiyat ancak SİPARİŞ anında
 * donuyor (1D).
 */
class CartService
{
    /** Bir satırda en fazla adet — panelde yanlış tuşa basmayı sınırlar. */
    public const MAKS_ADET = 99;

    public function __construct(private readonly EventRecorder $olaylar) {}

    /**
     * Misafir sepeti açar.
     *
     * ⚠️ Token KRİPTOGRAFİK rastgele (`Str::random` altında
     * `random_bytes`). Ardışık olsaydı biri başkasının sepetini okurdu —
     * adres yok ama ne aldığı görünür.
     */
    public function misafirSepetiOlustur(): Cart
    {
        $sepet = new Cart;
        $sepet->session_token = Str::random(64);
        $sepet->status = CartStatus::Active;
        $sepet->last_activity_at = now();
        $sepet->save();

        return $sepet;
    }

    /** Misafir sepetini token'ından bulur. */
    public function misafirSepetiBul(?string $token): ?Cart
    {
        if ($token === null || $token === '') {
            return null;
        }

        return Cart::where('session_token', $token)
            ->where('status', CartStatus::Active)
            ->first();
    }

    /**
     * Müşterinin aktif sepeti; yoksa açar.
     *
     * Kısmi benzersiz indeks sayesinde müşteri başına tek aktif sepet
     * garantili (1C-K4).
     */
    public function musteriSepeti(Customer $musteri): Cart
    {
        $sepet = Cart::where('customer_id', $musteri->id)
            ->where('status', CartStatus::Active)
            ->first();

        if ($sepet !== null) {
            return $sepet;
        }

        $yeni = new Cart;
        $yeni->customer()->associate($musteri);
        $yeni->status = CartStatus::Active;
        $yeni->last_activity_at = now();
        $yeni->save();

        return $yeni;
    }

    /**
     * Satır ekler; varsa adedi ARTIRIR.
     *
     * ⚠️ Stok kontrolü YUMUŞAK (1C-K3): sepet rezerve etmiyor, bu yüzden
     * burada durdurmak yerine mevcut stoğa KIRPIYORUZ. Bağlayıcı kontrol
     * ödeme adımında.
     *
     * Sert reddetseydik: müşteri 5 isterken 3 varsa hiçbir şey eklenmez ve
     * "neden olmadı" sorusuyla baş başa kalırdı. Kırpma, alabileceğini
     * veriyor ve farkı görünür kılıyor.
     *
     * @throws VariantNotPurchasableException
     */
    public function ekle(Cart $sepet, ProductVariant $varyant, int $adet): CartItem
    {
        // Satın alınamayan varyant sepete HİÇ girmiyor. (Sepetteyken
        // satılamaz hâle gelen satır ayrı konu — o silinmiyor, işaretleniyor.)
        if (! $varyant->satinAlinabilirMi()) {
            throw new VariantNotPurchasableException($varyant->sku);
        }

        return DB::transaction(function () use ($sepet, $varyant, $adet) {
            $satir = $sepet->items()->where('variant_id', $varyant->id)->first();

            $yeniAdet = ($satir === null ? 0 : $satir->quantity) + $adet;
            $yeniAdet = $this->adediKirp($yeniAdet, $varyant);

            if ($satir === null) {
                $satir = $sepet->items()->make(['quantity' => $yeniAdet]);
                $satir->variant()->associate($varyant);
                $satir->save();
            } else {
                $satir->update(['quantity' => $yeniAdet]);
            }

            $this->dokunuldu($sepet);

            /*
            | ⚠️ Olay TRANSACTION İÇİNDEN çağrılıyor ama kuyruğa COMMIT'ten
            | SONRA giriyor (1F-K5, `afterCommit`). Geri sarılırsa satır
            | hiç var olmaz ve olay da hiç atılmaz.
            */
            $this->olaylar->kaydet(EventType::CartItemAdded, [
                'variant_id' => $varyant->id,
                'sku' => $varyant->sku,
                'quantity' => $adet,
            ], $sepet->customer);

            return $satir;
        });
    }

    /** Adedi doğrudan belirler. 0 verilirse satır silinir. */
    public function adetDegistir(CartItem $satir, int $adet): ?CartItem
    {
        if ($adet <= 0) {
            $this->satirSil($satir);

            return null;
        }

        $varyant = $satir->variant;

        if ($varyant !== null) {
            $adet = $this->adediKirp($adet, $varyant);
        }

        $satir->update(['quantity' => $adet]);
        $this->dokunuldu($satir->cart);

        return $satir;
    }

    public function satirSil(CartItem $satir): void
    {
        $sepet = $satir->cart;
        $varyant = $satir->variant;

        $satir->delete();
        $this->dokunuldu($sepet);

        $this->olaylar->kaydet(EventType::CartItemRemoved, [
            // ⚠️ Varyantı silinmiş satır da çıkarılabiliyor — id null olabilir.
            'variant_id' => $varyant?->id,
            'sku' => $varyant?->sku,
        ], $sepet?->customer);
    }

    /**
     * ★ MİSAFİR SEPETİNİ MÜŞTERİNİN SEPETİNE TAŞIR. (1C-K5)
     *
     * Kural: aynı varyant iki sepette de varsa **adetler TOPLANMAZ, BÜYÜK
     * OLAN alınır.**
     *
     * ⚠️ Neden toplanmıyor — ve bu bir varsayım değil, ölçülmüş bir hata:
     * Magento topluyor (`setQty($quoteItem->getQty() + $item->getQty())`)
     * ve bu kayıtlı bir hata kaynağı (magento2 #26981: birleştirme
     * stok/uygunluk kontrolü yapmadan koşuyor). WooCommerce ise
     * birleştirmeyi bir ara tamamen kaldırmış, topluluk baskısıyla geri
     * koymuş.
     *
     * "İki cihazda 2'şer ekledim" diyen kullanıcının niyeti 4 almak
     * değildir. Toplama sessizce yanlış sipariş üretir; büyüğü almak en
     * kötü ihtimalle fazladan bir adet bırakır ve kullanıcı bunu GÖRÜR.
     *
     * ⚠️ Birleştirmeden SONRA stok kontrolü koşuyor — Magento'nun atladığı
     * adım tam olarak bu.
     */
    public function birlestir(Cart $misafirSepeti, Customer $musteri): Cart
    {
        $hedef = $this->musteriSepeti($musteri);

        if ($misafirSepeti->is($hedef)) {
            return $hedef;
        }

        return DB::transaction(function () use ($misafirSepeti, $hedef) {
            foreach ($misafirSepeti->items as $gelen) {
                $mevcut = $hedef->items()->where('variant_id', $gelen->variant_id)->first();

                if ($mevcut === null) {
                    $yeni = $hedef->items()->make(['quantity' => $gelen->quantity]);
                    $yeni->variant()->associate($gelen->variant);
                    $yeni->save();

                    continue;
                }

                // ⚠️ TOPLAMA DEĞİL, BÜYÜĞÜ AL.
                $mevcut->update(['quantity' => max($mevcut->quantity, $gelen->quantity)]);
            }

            // Misafir sepeti tüketildi.
            $misafirSepeti->items()->delete();
            $misafirSepeti->delete();

            /*
            | ⚠️ BİRLEŞTİRMEDEN SONRA STOK KONTROLÜ.
            |
            | Magento'nun kayıtlı hatası (#26981) tam olarak bu adımın
            | eksikliği. İki sepetten gelen adetler stoğu aşabilir; burada
            | mevcut stoğa kırpılıyor.
            */
            $hedef->load('items.variant');

            foreach ($hedef->items as $satir) {
                $varyant = $satir->variant;

                if ($varyant === null) {
                    continue;
                }

                $kirpilmis = $this->adediKirp($satir->quantity, $varyant);

                if ($kirpilmis !== $satir->quantity) {
                    $satir->update(['quantity' => $kirpilmis]);
                }
            }

            $this->dokunuldu($hedef);

            return $hedef->load('items.variant');
        });
    }

    /**
     * Ödeme adımı için: sepet sipariş verilebilir durumda mı? (1C-K2/K3)
     *
     * Boş liste dönerse sepet hazır. Dönen her satır kullanıcıya
     * gösterilecek bir engel.
     *
     * @return list<array{sku: string, sorun: string}>
     */
    public function engeller(Cart $sepet): array
    {
        $engeller = [];

        foreach ($sepet->load('items.variant.product')->items as $satir) {
            $varyant = $satir->variant;
            $sku = $varyant === null ? '?' : $varyant->sku;

            if (! $satir->kullanilabilirMi()) {
                $engeller[] = ['sku' => $sku, 'sorun' => 'Bu ürün artık satışta değil.'];

                continue;
            }

            if (! $satir->stokYetiyorMu()) {
                $engeller[] = [
                    'sku' => $sku,
                    'sorun' => 'Stok yetersiz: '.($varyant === null ? 0 : $varyant->stock).' adet kaldı.',
                ];
            }
        }

        return $engeller;
    }

    /**
     * Adedi stoğa ve üst sınıra kırpar.
     *
     * ⚠️ Sepet REZERVE ETMİYOR: burada gördüğümüz stok, ödeme anına kadar
     * düşebilir. Bu yüzden bu kontrol yardımcıdır, bağlayıcı değil —
     * bağlayıcı olan `engeller()` ve 1D'nin rezervasyonu.
     */
    private function adediKirp(int $adet, ProductVariant $varyant): int
    {
        return max(1, min($adet, $varyant->stock, self::MAKS_ADET));
    }

    private function dokunuldu(?Cart $sepet): void
    {
        if ($sepet === null) {
            return;
        }

        /*
        | ⚠️ `update([...])` DEĞİL: `Cart::$fillable` bilerek BOŞ (sahiplik
        | ve durum alanlarını yalnızca servis yazsın diye), bu yüzden kütle
        | atama kapalı. Alanı doğrudan atıyoruz — kuralın kendisi doğru,
        | sadece kütle atama yolunu kullanamıyoruz.
        */
        $sepet->last_activity_at = now();
        $sepet->save();

        // Terk edilmiş sepet hatırlatması bu damgayı tarayacak (Faz 3).
    }
}
