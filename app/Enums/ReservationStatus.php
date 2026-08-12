<?php

namespace App\Enums;

/**
 * Stok rezervasyonunun durumu. (1D-K1 · 1D-K3)
 *
 * ⚠️ Rezervasyon bir KİLİT — ama satır kilidi değil, istekler ARASINDA
 * yaşayan kalıcı bir kilit. Müşteri ödeme sayfasındayken stoğun
 * kapılmamasını sağlıyor.
 *
 * ★ İKİ AKTİF DURUM VAR ve ayrımın sebebi SÜRE:
 *
 *   Held    15 dk   süreç BİZDE    — müşteri sepette/ödeme formunda
 *   Paying  60 dk   süreç DIŞARIDA — müşteri bankada, geri alamayız
 *
 * ⚠️ Tek durumla idare edilemiyordu. 15 dakika "müşteri oyalanıyor"
 * varsayımıyla seçilmişti; oysa ödeme başladıktan sonra süreyi sağlayıcı
 * belirliyor: iyzico bildirimi 15 dakikada bir, 3 kez tekrar ediyor —
 * yani ikinci deneme rezervasyonun öldüğü dakikaya denk geliyor.
 *
 * Süreyi topluca 60'a çıkarmak da yanlıştı: ödemeye hiç başlamamış terk
 * edilmiş sepet, stoğu bir saat rehin tutardı.
 */
enum ReservationStatus: string
{
    /** Tutuldu — `committed` sayacına dâhil, 15 dakika geçerli. */
    case Held = 'held';

    /**
     * Ödeme BAŞLADI, sonuç bekleniyor — `committed`'a hâlâ dâhil, 60 dakika.
     *
     * ⚠️ Bu durumdayken temizlik görevi rezervasyona 15. dakikada
     * DOKUNMUYOR. Dokunsaydı: müşteri bankada SMS kodunu girerken stok
     * serbest kalır, başkası son ürünü alır, sonra ödeme başarılı gelir —
     * para çekilmiş, mal yok.
     */
    case Paying = 'paying';

    /** Ödeme başarılı: stok gerçekten düştü. */
    case Committed = 'committed';

    /** Ödeme başarısız ya da süre doldu: stok geri verildi. */
    case Released = 'released';

    /**
     * ★ Stoğu HÂLÂ BAĞLI TUTAN durumlar.
     *
     * ⚠️ Bu liste tek bir yerde duruyor çünkü kullanıldığı YER ÇOK:
     * kesinleştirme, serbest bırakma, süre temizliği, sayaç denetimi ve
     * siparişin rezervasyonlarını bulma. Her birinde ayrı ayrı
     * `'held'` yazılsaydı `Paying` eklendiği gün biri unutulur ve o yol
     * sessizce hiçbir rezervasyon bulamazdı — ödeme başarılı olur, stok
     * hiç düşmezdi.
     *
     * @return list<self>
     */
    public static function aktifler(): array
    {
        return [self::Held, self::Paying];
    }

    /** @return list<string> */
    public static function aktifDegerler(): array
    {
        return array_map(fn (self $durum) => $durum->value, self::aktifler());
    }

    public function aktifMi(): bool
    {
        return in_array($this, self::aktifler(), strict: true);
    }
}
