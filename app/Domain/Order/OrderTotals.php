<?php

namespace App\Domain\Order;

/**
 * Tutar ve vergi hesabı — TEK YER. (docs/domain-model.md §8.2)
 *
 * ⚠️ Bu hesap başka hiçbir yerde yapılmayacak. Dağıtılsaydı Faz 2'de kupon
 * geldiğinde formül değişir ve her çağrı yerinin tek tek güncellenmesi
 * gerekirdi; biri unutulursa fatura ile tahsilat tutmaz.
 *
 * ★ ÜÇ KURAL, ÜÇÜ DE SESSİZ HATA ÜRETİR:
 *
 *   1. FİYAT KDV DÂHİLDİR. Vergi tutarın İÇİNDEN ayrıştırılıyor:
 *        tax = line_total × oran / (100 + oran)
 *      120,00 ₺ · %20 → net 100,00 · vergi 20,00
 *
 *   2. `tax_total` `grand_total`'a EKLENMEZ. Fiyatlar zaten vergi dâhil;
 *      bu alan faturada gösterilen bilgi. Eklenseydi her siparişte
 *      müşteriden FAZLADAN KDV tahsil edilirdi — vergi dâhil modelde en
 *      sık yapılan hata.
 *
 *   3. VERGİ İNDİRİMDEN SONRA hesaplanır: `unit_price × quantity`'den
 *      değil `line_total`'dan. Sıra korunmazsa iade tutarları faturayla
 *      tutmaz (Faz 2 kuponları).
 *
 * ⚠️ Bütün hesap `bcmath` ile — float YASAK (§0). `0.1 + 0.2 !== 0.3`
 * hatası para tutarında kuruş kaydırır ve toplamlar tutmaz.
 */
class OrderTotals
{
    /** Para alanlarının ondalık basamağı. */
    private const BASAMAK = 2;

    /**
     * Tek satırın tutarları.
     *
     * @return array{line_total: numeric-string, tax_amount: numeric-string}
     */
    public function satir(string $birimFiyat, int $adet, string $vergiOrani, string $indirim = '0'): array
    {
        $brut = bcmul($this->sayi($birimFiyat), (string) $adet, self::BASAMAK);

        // Kural 3: indirim ÖNCE düşülüyor.
        $satirToplami = bcsub($brut, $this->sayi($indirim), self::BASAMAK);

        return [
            'line_total' => $satirToplami,
            'tax_amount' => $this->vergiyiAyristir($satirToplami, $vergiOrani),
        ];
    }

    /**
     * Vergiyi tutarın İÇİNDEN ayrıştırır (§8.1).
     *
     * ⚠️ `tutar × oran/100` DEĞİL — o, vergi HARİÇ fiyat için geçerlidir ve
     * %20'de vergiyi olduğundan fazla gösterirdi.
     *
     * ⚠️ `tutar − (tutar / (1 + oran/100))` de DEĞİL. Test bunu yakaladı:
     * `bcdiv` yuvarlamaz, KIRPAR. 200 / 1.20 = 166.666… → 166.66 ve vergi
     * 33.34 çıkıyor; muhasebe kuralı ise 33.33. Tek kuruş, ama her satırda
     * tekrarlanıyor ve faturayla tutmuyor.
     *
     * Doğrusu standart "iç yüzde" formülü:
     *
     *     vergi = tutar × oran / (100 + oran)
     *
     * 200 × 20 / 120 = 33,3333 → 33,33 ✓
     *
     * Ayrıca net'i buradan türetince (`net = tutar − vergi`) net + vergi
     * TAM OLARAK tutara eşit kalıyor — çift yuvarlama sorunu doğmuyor.
     *
     * @return numeric-string
     */
    public function vergiyiAyristir(string $tutar, string $vergiOrani): string
    {
        $oran = $this->sayi($vergiOrani);
        $tutar = $this->sayi($tutar);

        if (bccomp($oran, '0', 4) === 0) {
            return '0.00';
        }

        $pay = bcmul($tutar, $oran, 6);
        $payda = bcadd('100', $oran, 6);

        return $this->yuvarla(bcdiv($pay, $payda, 6), self::BASAMAK);
    }

    /**
     * `bcmath` YUVARLAMAZ, KIRPAR — bu yüzden elle yuvarlıyoruz.
     *
     * ⚠️ Kırpma para hesabında sistematik sapma üretir: her satırda birkaç
     * kuruş eksiğe düşer ve toplam faturayla tutmaz. Yarım yukarı
     * yuvarlama muhasebenin beklediği davranış.
     *
     * @return numeric-string
     */
    private function yuvarla(string $deger, int $basamak): string
    {
        $sayi = $this->sayi($deger);
        $yarim = bcdiv('5', bcpow('10', (string) ($basamak + 1)), $basamak + 1);

        return bccomp($sayi, '0', $basamak + 1) >= 0
            ? bcadd($sayi, $yarim, $basamak)
            : bcsub($sayi, $yarim, $basamak);
    }

    /**
     * Sipariş seviyesi toplamlar.
     *
     * @param  list<array{line_total: string, tax_amount: string}>  $satirlar
     * @return array{items_total: numeric-string, tax_total: numeric-string, grand_total: numeric-string}
     */
    public function siparis(array $satirlar, string $kargo = '0', string $kargoVergiOrani = '0', string $indirim = '0'): array
    {
        $satirToplami = '0.00';
        $vergiToplami = '0.00';

        foreach ($satirlar as $satir) {
            $satirToplami = bcadd($satirToplami, $this->sayi($satir['line_total']), self::BASAMAK);
            $vergiToplami = bcadd($vergiToplami, $this->sayi($satir['tax_amount']), self::BASAMAK);
        }

        // Kargo bedelinin vergisi de toplama giriyor (§8.2).
        $vergiToplami = bcadd($vergiToplami, $this->vergiyiAyristir($kargo, $kargoVergiOrani), self::BASAMAK);

        /*
        | ⚠️ Kural 2: `tax_total` BURAYA EKLENMİYOR.
        | grand_total = items_total − discount_total + shipping_total
        */
        $genelToplam = bcadd(
            bcsub($satirToplami, $this->sayi($indirim), self::BASAMAK),
            $this->sayi($kargo),
            self::BASAMAK,
        );

        return [
            'items_total' => $satirToplami,
            'tax_total' => $vergiToplami,
            'grand_total' => $genelToplam,
        ];
    }

    /**
     * Kargo bedeli — mağaza ayarından (§7.1).
     *
     * ⚠️ Eşik dâhil: tam eşiğe ulaşan sipariş ücretsiz. "500 TL üzeri
     * kargo bedava" diyen markanın müşterisi tam 500 TL'de ücret görmemeli.
     *
     * @return numeric-string
     */
    public function kargo(string $urunToplami, string $sabitUcret, string $ucretsizEsik): string
    {
        if (bccomp($this->sayi($ucretsizEsik), '0', self::BASAMAK) > 0
            && bccomp($this->sayi($urunToplami), $this->sayi($ucretsizEsik), self::BASAMAK) >= 0) {
            return '0.00';
        }

        return $this->sayi($sabitUcret);
    }

    /**
     * Girdiyi güvenli sayısal metne çevirir.
     *
     * Bozuk veri geldiğinde `bcmath` uyarı verip 0 kabul ediyor; burada
     * açıkça 0'a çeviriyoruz ki davranış belirsiz kalmasın.
     *
     * @return numeric-string
     */
    private function sayi(string $deger): string
    {
        return is_numeric($deger) ? $deger : '0';
    }
}
