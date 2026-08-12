<?php

namespace App\Domain\Payment;

use RuntimeException;

/**
 * Sağlayıcı anahtarı `settings`'te yok.
 *
 * ⚠️ Bu istisna 1E.1'de TESTİN BULDUĞU bir sessiz arızayı kapatıyor:
 * anahtar boşken `hash_hmac(..., '')` hâlâ geçerli bir imza üretiyordu.
 * Yani imza doğrulaması "çalışıyor" görünürdü, ama anahtarı olmayan bir
 * markada algoritmayı bilen herkes geçerli bildirim üretebilirdi —
 * bedava sipariş, hatasız.
 *
 * Boş anahtar bir DURUM değil, YAPILANDIRMA eksiği: gürültülü patlıyor.
 */
class MissingPaymentCredentialsException extends RuntimeException
{
    public function __construct(public readonly string $saglayici, public readonly string $anahtar)
    {
        parent::__construct("`{$saglayici}` sağlayıcısının `{$anahtar}` anahtarı ayarlarda yok.");
    }
}
