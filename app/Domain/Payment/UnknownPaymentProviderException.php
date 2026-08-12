<?php

namespace App\Domain\Payment;

use RuntimeException;

/**
 * `settings`'te tanınmayan bir sağlayıcı adı yazılı.
 *
 * ⚠️ Bu bir İŞ KURALI ihlali değil YAPILANDIRMA hatası — müşteriye
 * gösterilecek bir şey yok, markanın düzeltmesi gereken bir ayar var.
 * Bu yüzden 4xx eşlemesi yok; 500 olarak patlıyor ve günlüğe düşüyor.
 */
class UnknownPaymentProviderException extends RuntimeException
{
    public function __construct(public readonly string $ad)
    {
        parent::__construct("Tanınmayan ödeme sağlayıcısı: {$ad}");
    }
}
