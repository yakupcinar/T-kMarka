<?php

namespace App\Domain\Payment;

use RuntimeException;

/**
 * Sağlayıcı çağrısı başarısız — ağ hatası, eksik cevap ya da iş hatası.
 *
 * ⚠️ İstisna YUTULMUYOR. Webhook işlerken yükselirse 2xx dönmüyoruz ve
 * sağlayıcı 15 dakika sonra tekrar deniyor — geçici bir sorunsa ikinci
 * deneme kurtarıyor. Yutulsaydı "işlendi" der, hiçbir şey yapmazdık.
 *
 * ⚠️ `$ayrintilar` MASKELENMİŞ geliyor; ham sağlayıcı cevabı olduğu gibi
 * taşınmıyor.
 */
class PaymentProviderException extends RuntimeException
{
    /** @param  array<string, mixed>  $ayrintilar */
    public function __construct(
        public readonly string $saglayici,
        string $mesaj,
        public readonly array $ayrintilar = [],
    ) {
        parent::__construct("[{$saglayici}] {$mesaj}");
    }
}
