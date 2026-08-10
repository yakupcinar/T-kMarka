<?php

namespace App\Enums;

/**
 * `settings.group` kolonunun alabileceği değerler. (docs/domain-model.md §4)
 *
 * Neden enum: grup adı serbest metin olsaydı `'payment'` yerine `'payments'`
 * yazılan tek satır, ödeme ayarlarının panelde görünmemesine yol açardı —
 * hata da vermezdi, sadece boş liste dönerdi. Enum bunu yazım anında
 * yakalatıyor; Larastan da olmayan bir durumu derlemeden önce bildiriyor.
 *
 * `string` destekli (backed) çünkü veritabanında metin olarak duruyor.
 */
enum SettingGroup: string
{
    /** Marka kimliği: ad, logo, iletişim bilgileri. */
    case Store = 'store';

    /** Vitrin görünümü: renkler, yazı tipleri, ana sayfa blokları (Faz 4). */
    case Theme = 'theme';

    /** Ödeme adımı davranışı: misafir alışverişi açık mı, alt limit var mı. */
    case Checkout = 'checkout';

    /** Kargo ücreti ve ücretsiz kargo eşiği. */
    case Shipping = 'shipping';

    /** KDV oranı ve fiyatların vergi dâhil olup olmadığı. */
    case Tax = 'tax';

    /**
     * Ödeme sağlayıcı bilgileri.
     *
     * ⚠️ Bu gruptaki anahtarlar `is_encrypted = true` ile saklanır — her
     * markanın kendi hesabı var ve `.env`'e yazılamaz (M-1, M-2).
     */
    case Payment = 'payment';

    /*
    | ⚠️ `Legal` grubu BİLEREK YOK.
    |
    | KVKK aydınlatma, mesafeli satış sözleşmesi ve iade koşulları buraya
    | yazılabilirdi ve hata da vermezdi — ama ayar "şu an geçerli değer"
    | demektir, geçmişi yoktur. Yasal metnin geçmişi olmak ZORUNDA: her
    | sipariş, verildiği andaki metne bağlı kalır.
    |
    | Metin ayara konsaydı marka bir virgül düzeltince geçmiş siparişlerin
    | dayanağı da sessizce değişirdi. Bu yüzden yasal metinler kendi
    | sürümlü tablolarında: App\Enums\LegalDocumentType.
    |
    | Grup burada dursaydı birinin bir gün oraya yazması an meselesiydi.
    */
}
