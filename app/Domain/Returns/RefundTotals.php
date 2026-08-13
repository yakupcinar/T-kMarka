<?php

namespace App\Domain\Returns;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderReturn;

/**
 * İade tutarının hesaplandığı TEK yer. (2B-K4 · 2B-K5)
 *
 * ★ `OrderTotals`'ın aynası: orada para geliyordu, burada gidiyor.
 * Aynı kural geçerli — **float YASAK**, hepsi `bcmath` (§0).
 *
 * ⚠️ VERGİ YENİDEN HESAPLANMIYOR. Seçilen satırın KDV'si, siparişte
 * DONMUŞ hâliyle geri dönüyor. Magento da böyle yapıyor: kredi notunda
 * vergi yeniden hesaplanmaz, satırın vergisi iade edilir.
 *
 * Yeniden hesaplansaydı ne olurdu: KDV oranı yarın değişse (kanunla
 * değişebiliyor, `default_rate` bu yüzden kilitli değil) eski siparişin
 * iadesi yeni oranla hesaplanır ve tutar tutmazdı.
 */
class RefundTotals
{
    private const BASAMAK = 2;

    /**
     * İade talebinin karşılığı olan tutarlar.
     *
     * @return array{items: numeric-string, tax: numeric-string, shipping: numeric-string, total: numeric-string}
     */
    public function hesapla(OrderReturn $talep): array
    {
        $talep->load('items.orderItem', 'order');

        $urun = '0.00';
        $vergi = '0.00';
        $iadeEdilenAdet = 0;
        $siparisAdedi = 0;

        foreach ($talep->items as $satir) {
            $kalem = $satir->orderItem;

            if ($kalem === null) {
                continue;
            }

            /*
            | ⚠️ Satırın BİRİM fiyatından gidiliyor, `line_total`'dan
            | değil: kısmi iade olabiliyor (3 adetin 1'i).
            |
            | ⚠️ `unit_price` yerine `line_total / quantity` denseydi
            | bölme kalanı kuruş kaydırırdı.
            */
            $urun = bcadd($urun, bcmul($this->sayi($kalem->unit_price), (string) $satir->quantity, self::BASAMAK), self::BASAMAK);

            /*
            | Verginin satır içindeki payı, iade edilen adet oranında.
            | `tax_amount` satırın TAMAMININ vergisi.
            */
            $vergi = bcadd(
                $vergi,
                $this->pay($this->sayi($kalem->tax_amount), $satir->quantity, $kalem->quantity),
                self::BASAMAK,
            );

            $iadeEdilenAdet += $satir->quantity;
        }

        $siparis = $talep->order;

        foreach ($siparis === null ? [] : $siparis->items as $kalem) {
            $siparisAdedi += $kalem->quantity;
        }

        /*
        | ★ TAM CAYMA, TEK TALEPTE OLMAK ZORUNDA DEĞİL.
        |
        | ⚠️ Testin bulduğu eksik: müşteri siparişin tamamını iki adımda
        | iade ederse bu da tam caymadır ve kargo geri verilmelidir.
        | Yalnızca bu talebe bakılsaydı hiçbiri "tam" sayılmaz, kargo hiç
        | iade edilmez ve sipariş asla `refunded` olmazdı.
        */
        $kargo = $this->kargo($talep, $iadeEdilenAdet + $this->oncekiIadeAdedi($talep), $siparisAdedi);

        return [
            'items' => $urun,
            'tax' => $vergi,
            'shipping' => $kargo,
            'total' => bcadd($urun, $kargo, self::BASAMAK),
        ];
    }

    /**
     * ★ 2B-K5 — TAM CAYMADA KARGO BEDELİ DE GERİ VERİLİR.
     *
     * ⚠️ Bu bir tasarım tercihi DEĞİL, yasal zorunluluk: satıcı
     * "teslim masrafları dâhil tahsil edilen tüm ödemeleri" iade etmekle
     * yükümlü. İlk tasarımda "kısmi iadede kargo geri verilmez, tam
     * iptalde verilir" denmişti; araştırma bunu düzeltti.
     *
     * ⚠️ KISMİ iadede kargo geri VERİLMİYOR: müşteri ürünlerin bir
     * kısmını tutuyor, teslimat gerçekten yapıldı. Magento'da da bu
     * operatörün girdiği ayrı bir alan.
     *
     * @return numeric-string
     */
    private function kargo(OrderReturn $talep, int $iadeEdilen, int $siparisAdedi): string
    {
        $siparis = $talep->order;

        if ($siparis === null || ! $talep->is_withdrawal) {
            /*
            | ⚠️ Kusurlu ürün iadesi cayma DEĞİL: kargo kuralı ona göre
            | işlemiyor, marka karar veriyor.
            */
            return '0.00';
        }

        // Tam cayma: siparişin bütün adetleri (birikimli) iade ediliyor.
        if ($iadeEdilen <= 0 || $iadeEdilen < $siparisAdedi) {
            return '0.00';
        }

        /*
        | ⚠️ KARGO BİR KEZ İADE EDİLİR. Kontrol olmasaydı, tam caymadan
        | sonra açılan (ve reddedilmeyen) her talep kargoyu tekrar geri
        | verirdi.
        */
        foreach ($siparis->refunds()->where('status', '!=', 'failed')->get() as $onceki) {
            if (bccomp($this->sayi($onceki->shipping_amount), '0', self::BASAMAK) > 0) {
                return '0.00';
            }
        }

        return $this->sayi($siparis->shipping_total);
    }

    /**
     * Bu talep DIŞINDA, reddedilmemiş taleplerde iade edilen adet.
     *
     * ⚠️ Reddedilenler sayılmıyor — `ReturnService::asimiDogrula()`
     * ile aynı kural; iki yerde ayrı yazılsaydı biri değişince diğeri
     * unutulurdu.
     */
    private function oncekiIadeAdedi(OrderReturn $talep): int
    {
        $siparis = $talep->order;

        if ($siparis === null) {
            return 0;
        }

        $toplam = 0;

        foreach ($siparis->returns()->where('id', '!=', $talep->id)->with('items')->get() as $diger) {
            if ($diger->status === ReturnStatus::Rejected) {
                continue;
            }

            foreach ($diger->items as $satir) {
                $toplam += $satir->quantity;
            }
        }

        return $toplam;
    }

    /**
     * Oransal pay — kalanı kaybetmeden.
     *
     * ⚠️ `bcdiv` KESİYOR, yuvarlamıyor (1D.3'te bir kuruş hatası buradan
     * çıkmıştı). Yarım yukarı yuvarlama elle yapılıyor.
     *
     * @param  numeric-string  $tutar
     * @return numeric-string
     */
    private function pay(string $tutar, int $adet, int $toplamAdet): string
    {
        if ($toplamAdet <= 0) {
            return '0.00';
        }

        $ham = bcdiv(bcmul($tutar, (string) $adet, 6), (string) $toplamAdet, 6);

        return $this->yuvarla($ham);
    }

    /** @return numeric-string */
    private function yuvarla(string $deger): string
    {
        /*
        | ⚠️ Yarım yukarı yuvarlama ELLE: `bcadd` son basamağı keserek
        | yuvarlıyor, yani 0.005 eklemek "yarım yukarı" anlamına geliyor.
        | 1D.3'te bir kuruş hatası tam buradan çıkmıştı.
        */
        /** @var numeric-string $ham */
        $ham = $deger;

        /** @var numeric-string $sonuc */
        $sonuc = bcadd($ham, '0.005', self::BASAMAK);

        return $sonuc;
    }

    /** @return numeric-string */
    private function sayi(mixed $deger): string
    {
        return is_numeric($deger) ? (string) $deger : '0';
    }

    /**
     * Bu sipariş için daha önce iade edilen toplam — aşırı iade kontrolü.
     *
     * ⚠️ Olmasaydı aynı satır iki talepte iade edilir ve müşteriye ürün
     * bedelinin iki katı geri giderdi.
     *
     * @return numeric-string
     */
    public function iadeEdilenToplam(Order $siparis): string
    {
        $toplam = '0.00';

        foreach ($siparis->refunds()->where('status', '!=', 'failed')->get() as $iade) {
            $toplam = bcadd($toplam, $this->sayi($iade->amount), self::BASAMAK);
        }

        return $toplam;
    }
}
