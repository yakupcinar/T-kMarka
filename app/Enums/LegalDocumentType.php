<?php

namespace App\Enums;

/**
 * Markanın yayınlamak zorunda olduğu yasal metinler.
 *
 * ⚠️ Bunlar `settings` tablosunda DURMAZ. Sebep: ayar "şu an geçerli değer"
 * demektir ve geçmişi yoktur; yasal metnin ise geçmişi olmak ZORUNDA — her
 * sipariş, verildiği andaki metne bağlı kalır. Sürümlenmesi gereken bir şeyi
 * ayara koymak hata vermez, sadece bir gün eski siparişin dayanağını sessizce
 * değiştirir.
 *
 * Liste kodda sabit: yenisi eklenirse yayın denetimi de onu arar (bkz.
 * StoreReadiness). Serbest metin olsaydı yazım hatası "bu belge tanımlı
 * değil" diye sessizce geçilirdi.
 */
enum LegalDocumentType: string
{
    /**
     * Mesafeli satış sözleşmesi.
     *
     * Müşterinin ödeme adımında onayladığı metin — siparişin hukuki dayanağı.
     */
    case DistanceSales = 'distance_sales';

    /** KVKK aydınlatma metni: kişisel verinin hangi amaçla işlendiği. */
    case Privacy = 'privacy';

    /** İade ve cayma koşulları: süre, kargo bedeli kimde, istisnalar. */
    case Returns = 'returns';

    /** Panelde ve vitrinde görünecek ad. */
    public function etiket(): string
    {
        return match ($this) {
            self::DistanceSales => 'Mesafeli Satış Sözleşmesi',
            self::Privacy => 'KVKK Aydınlatma Metni',
            self::Returns => 'İade ve Cayma Koşulları',
        };
    }
}
