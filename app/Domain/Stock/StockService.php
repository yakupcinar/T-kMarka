<?php

namespace App\Domain\Stock;

use App\Enums\ReservationStatus;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stok rezervasyonu — aşırı satışın engellendiği yer. (1D-K1/K5/K6)
 *
 * ★ İki kilit birlikte çalışıyor:
 *
 *   SATIR KİLİDİ (FOR UPDATE)   mikrosaniye · sayacın okunup yazılması arası
 *   REZERVASYON (15 dk)         istekler arası · ödeme sayfasındaki süre
 *
 * ⚠️ Satır kilidi PHP'de değil PostgreSQL'de yaşıyor: kaç uygulama kopyası
 * olursa olsun hepsi aynı satıra gidip orada sıraya giriyor. M-2'nin "tek
 * veritabanı" kararı sayesinde dağıtık transaction sorunu hiç doğmuyor.
 */
class StockService
{
    /** Sepet aşamasındaki rezervasyon ömrü (1D-K3). */
    public const REZERVASYON_DAKIKA = 15;

    /**
     * Ödeme başladıktan sonraki ömür (1D-K3 güncellemesi · 1E.2).
     *
     * ⚠️ 60 dakika keyfi değil: iyzico bildirimi 10-15 saniye sonra atıyor,
     * 2xx alamazsa 15 dakikada bir 3 kez daha deniyor — son deneme 45.
     * dakikada. 15 dakikalık pencere ikinci denemeye bile yetmiyordu.
     * WooCommerce'in varsayılanı da 60.
     */
    public const ODEME_DAKIKA = 60;

    /** Kilit bekleme sınırı (1D-K6). */
    public const KILIT_ZAMAN_ASIMI = '3s';

    /**
     * Sepetin tamamı için stok rezerve eder.
     *
     * @return Collection<int, StockReservation>
     *
     * @throws InsufficientStockException
     * @throws StockLockTimeoutException
     */
    public function sepetiRezerveEt(Cart $sepet): Collection
    {
        $sepet->load('items.variant');

        /*
        | ⚠️ KİLİT SIRASI SABİT — id'ye göre artan.
        |
        | Sıra sabit olmasaydı KİLİTLENME (deadlock) olurdu:
        |   A: varyant 1'i kilitler, 2'yi ister
        |   B: varyant 2'yi kilitler, 1'i ister
        |   → ikisi de sonsuza kadar bekler
        | Herkes aynı sırada kilitlerse bu döngü kurulamıyor.
        */
        $satirlar = $sepet->items
            ->filter(fn ($satir) => $satir->variant !== null)
            ->sortBy(fn ($satir) => $satir->variant_id)
            ->values();

        return DB::transaction(function () use ($satirlar, $sepet) {
            $this->kilitZamanAsimiKur();

            $rezervasyonlar = collect();

            foreach ($satirlar as $satir) {
                $rezervasyonlar->push(
                    $this->tekVaryantiRezerveEt((int) $satir->variant_id, $satir->quantity, $sepet)
                );
            }

            return $rezervasyonlar;
        });
    }

    /**
     * Rezervasyonu KESİNLEŞTİRİR: stok gerçekten düşer.
     *
     * Ödeme başarılı olduğunda çağrılıyor. `committed` azalıyor çünkü artık
     * "bağlanmış" değil, "satılmış".
     */
    public function kesinlestir(StockReservation $rezervasyon): void
    {
        /*
        | ⚠️ `Held` VE `Paying` — ikisi de kabul.
        |
        | Yalnızca `Held` denseydi 1E.2'den sonra webhook geldiğinde
        | rezervasyon `Paying` olurdu, bu metot sessizce geri döner ve
        | STOK HİÇ DÜŞMEZDİ. Ödeme başarılı, sipariş ödendi, envanter
        | yanlış — hata da yok.
        */
        if (! $rezervasyon->status->aktifMi()) {
            return;   // zaten kesinleşmiş ya da bırakılmış
        }

        DB::transaction(function () use ($rezervasyon) {
            $this->kilitZamanAsimiKur();

            $varyant = $this->kilitle((int) $rezervasyon->variant_id, silinmisDahil: true);

            // stock ↓ ve committed ↓ — ikisi BİRLİKTE, aynı transaction'da.
            $varyant->stock -= $rezervasyon->quantity;
            $varyant->committed -= $rezervasyon->quantity;
            $varyant->save();

            $rezervasyon->status = ReservationStatus::Committed;
            $rezervasyon->save();
        });
    }

    /**
     * Rezervasyonu SERBEST BIRAKIR: bağlanmış adet geri veriliyor.
     *
     * Ödeme başarısız olduğunda ya da süre dolduğunda çağrılıyor.
     */
    public function serbestBirak(StockReservation $rezervasyon): void
    {
        if (! $rezervasyon->status->aktifMi()) {
            return;
        }

        DB::transaction(function () use ($rezervasyon) {
            $this->kilitZamanAsimiKur();

            $varyant = $this->kilitle((int) $rezervasyon->variant_id, silinmisDahil: true);

            // ⚠️ `stock` DEĞİŞMİYOR — hiç düşmemişti. Yalnızca bağ çözülüyor.
            $varyant->committed = max(0, $varyant->committed - $rezervasyon->quantity);
            $varyant->save();

            $rezervasyon->status = ReservationStatus::Released;
            $rezervasyon->save();
        });
    }

    /**
     * ★ Rezervasyonu ÖDEME AŞAMASINA alır: `Held` → `Paying`, süre 60 dk.
     *
     * Ödeme sağlayıcısına yönlendirmeden HEMEN ÖNCE çağrılıyor.
     *
     * ⚠️ Süre yönlendirmeden SONRA uzatılamaz — müşteri o an bizden
     * çıkmış oluyor ve geri döneceğinin garantisi yok. Uzatma yönlendirme
     * anında yapılmazsa hiç yapılamaz.
     *
     * ⚠️ Stok BURADA DÜŞMÜYOR. Yalnızca bağlı kalma süresi uzuyor;
     * `committed` zaten dâhildi, değişen tek şey `expires_at`.
     */
    public function odemeyeAl(StockReservation $rezervasyon): void
    {
        if ($rezervasyon->status !== ReservationStatus::Held) {
            return;   // ödemeye zaten alınmış ya da kapanmış
        }

        $rezervasyon->status = ReservationStatus::Paying;
        $rezervasyon->expires_at = now()->addMinutes(self::ODEME_DAKIKA);
        $rezervasyon->save();
    }

    /**
     * Süresi dolan rezervasyonları düşürür. (1D.5'teki zamanlanmış görev
     * bunu çağıracak.)
     *
     * ⚠️ O görev `tenants:run` ile sarılmak ZORUNDA (0.5, 5. tuzak).
     * Doğrudan yazılırsa merkez bağlamda koşar, hiçbir şey yapmaz ve hata
     * da vermez — stok sonsuza kadar bağlı kalır.
     *
     * @return int düşürülen rezervasyon sayısı
     */
    public function suresiDolanlariDusur(): int
    {
        /*
        | ⚠️ Durum değil SÜRE bakılıyor.
        |
        | `Paying` de buraya dâhil ama 15. dakikada değil 60. dakikada
        | düşüyor — çünkü `expires_at` ödeme başlarken uzatıldı. Liste
        | `Held` ile sınırlansaydı ödemesi yarıda kalan rezervasyonlar
        | SONSUZA KADAR yaşar, o stok bir daha hiç satılamazdı.
        */
        $dolanlar = StockReservation::whereIn('status', ReservationStatus::aktifDegerler())
            ->where('expires_at', '<', now())
            ->get();

        foreach ($dolanlar as $rezervasyon) {
            $this->serbestBirak($rezervasyon);
        }

        return $dolanlar->count();
    }

    /**
     * ★ TUTARLILIK DENETİMİ — materyalleştirilmiş sayacın bedeli.
     *
     * `committed` kolonu ile aktif rezervasyonların toplamı eşit olmalı.
     * Eşit değilse sayaç bozulmuş demektir: ya bir rezervasyon serbest
     * bırakılırken sayaç güncellenmemiş, ya da tersi.
     *
     * ⚠️ Shopify'ın "her konumda TUTMASI GEREKEN özdeşlik" dediği şeyin
     * bizdeki denetimi. Sayıyı materyalleştirmenin bedeli bu ve ödenmesi
     * gerekiyor — 1B.5'teki "SQL ikizini testle bağlama" fikrinin çalışma
     * anındaki hâli.
     *
     * @return list<array{sku: string, committed: int, rezervasyon_toplami: int}>
     */
    public function tutarsizliklar(): array
    {
        $satirlar = DB::table('product_variants as v')
            ->leftJoin('stock_reservations as r', function ($birlestir) {
                $birlestir->on('r.variant_id', '=', 'v.id')
                    // ⚠️ `Paying` de `committed`'a dâhil — dışarıda kalsaydı
                    // ödeme süren her sipariş "tutarsızlık" olarak raporlanır,
                    // gece denetimi her sabah yalancı alarm verirdi.
                    ->whereIn('r.status', ReservationStatus::aktifDegerler());
            })
            ->groupBy('v.id', 'v.sku', 'v.committed')
            ->havingRaw('v.committed <> COALESCE(SUM(r.quantity), 0)')
            ->select('v.sku', 'v.committed', DB::raw('COALESCE(SUM(r.quantity), 0) AS toplam'))
            ->get();

        /** @var list<array{sku: string, committed: int, rezervasyon_toplami: int}> $sonuc */
        $sonuc = [];

        foreach ($satirlar as $satir) {
            $sonuc[] = [
                'sku' => (string) $satir->sku,
                'committed' => (int) $satir->committed,
                'rezervasyon_toplami' => (int) $satir->toplam,
            ];
        }

        return $sonuc;
    }

    /**
     * @throws InsufficientStockException
     */
    private function tekVaryantiRezerveEt(int $varyantId, int $adet, Cart $sepet): StockReservation
    {
        $varyant = $this->kilitle($varyantId);

        /*
        | ⚠️ BAĞLAYICI kontrol burada. Sepette yumuşak kontrol vardı
        | (kırpma, 1C-K3) çünkü sepet rezerve etmiyor; arada başkası aynı
        | ürünü almış olabilir.
        */
        $satilabilir = $varyant->satilabilirAdet();

        if (! $varyant->is_active || $satilabilir < $adet) {
            throw new InsufficientStockException($varyant->sku, $adet, $satilabilir);
        }

        $varyant->committed += $adet;
        $varyant->save();

        $rezervasyon = new StockReservation;
        $rezervasyon->variant()->associate($varyant);
        $rezervasyon->cart()->associate($sepet);
        $rezervasyon->quantity = $adet;
        $rezervasyon->status = ReservationStatus::Held;
        $rezervasyon->expires_at = now()->addMinutes(self::REZERVASYON_DAKIKA);
        $rezervasyon->save();

        return $rezervasyon;
    }

    /**
     * Varyant satırını KİLİTLER ve güncel hâlini döndürür.
     *
     * ⚠️ `FOR UPDATE` olmadan: iki istek de stoğu 1 okur, ikisi de geçer,
     * 1 adet ürün 2 kez satılır — ve hata vermez. Kilitle ikincisi birinci
     * COMMIT edene kadar bekliyor, sonra GÜNCEL değeri okuyor.
     */
    private function kilitle(int $varyantId, bool $silinmisDahil = false): ProductVariant
    {
        $sorgu = ProductVariant::where('id', $varyantId);

        /*
        | ★ SİLİNMİŞ VARYANT DA KİLİTLENEBİLMELİ — ama yalnızca KAPANIŞ
        | yollarında (kesinleştirme / serbest bırakma).
        |
        | ⚠️ 1E.6'da test yakaladı: varyant `SoftDeletes` kullanıyor ve
        | varsayılan sorgu silinmişleri görmüyor. Marka, ödemesi yolda olan
        | bir siparişin varyantını katalogdan kaldırdığında `firstOrFail()`
        | patlıyordu — webhook 404 dönüyor, sağlayıcı üç kez deniyor, üçü de
        | düşüyor ve TAHSİLAT HİÇ KAYDEDİLMİYORDU. Para çekilmiş, sistemde iz
        | yok.
        |
        | Katalogdan kaldırmak bir VİTRİN kararı; yolda olan siparişin
        | muhasebesini bozmamalı.
        |
        | ⚠️ Rezervasyon AÇMA yolunda bayrak `false` kalıyor: silinmiş
        | varyant satın alınamaz.
        */
        if ($silinmisDahil) {
            $sorgu->withTrashed();
        }

        /** @var ProductVariant $varyant */
        $varyant = $sorgu->lockForUpdate()->firstOrFail();

        return $varyant;
    }

    /**
     * Kilit beklemesini sınırlar. (1D-K6)
     *
     * ⚠️ `SET LOCAL` — yalnızca bu transaction için. `SET` olsaydı ayar
     * bağlantıda kalır ve havuzdan gelen bir sonraki isteği de etkilerdi.
     */
    private function kilitZamanAsimiKur(): void
    {
        DB::statement("SET LOCAL lock_timeout = '".self::KILIT_ZAMAN_ASIMI."'");
    }
}
