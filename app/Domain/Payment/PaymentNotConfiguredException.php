<?php

namespace App\Domain\Payment;

use RuntimeException;

/**
 * Sağlayıcının anahtarları eksik — ödeme alınamaz. (1E-K11)
 *
 * ⚠️ 503 — GEÇİCİ. Müşterinin yaptığı bir hata yok, gönderdiği veri de
 * geçerli; markanın tamamlaması gereken bir yapılandırma var. 500 olsaydı
 * "sistem bozuldu" derdi; 422 olsaydı müşteriyi suçlardı.
 *
 * ⚠️ Eksik anahtar ADLARI müşteriye DÖNMÜYOR — markanın altyapısı hakkında
 * bilgi sızdırmanın anlamı yok. Marka onları kendi panelinde görüyor.
 */
class PaymentNotConfiguredException extends RuntimeException
{
    /** @param  list<string>  $eksikler */
    public function __construct(public readonly string $saglayici, public readonly array $eksikler)
    {
        parent::__construct('Ödeme şu an alınamıyor.');
    }
}
