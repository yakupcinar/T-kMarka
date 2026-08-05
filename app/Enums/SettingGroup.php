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

    /** KVKK aydınlatma, mesafeli satış sözleşmesi, iade politikası. */
    case Legal = 'legal';
}
