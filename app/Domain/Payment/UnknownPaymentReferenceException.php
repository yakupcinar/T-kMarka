<?php

namespace App\Domain\Payment;

use RuntimeException;

/**
 * Bildirimdeki referans bizde yok.
 *
 * ⚠️ 404 dönüyoruz ki sağlayıcı TEKRAR DENESİN. 200 dönseydi "işlendi"
 * sanıp bir daha aramazdı; gerçekte hiçbir şey olmamış olurdu ve ödeme
 * sonsuza kadar kayıp giderdi.
 *
 * Gerçek sebebi genelde geçici: ödeme başlatma isteği henüz commit
 * olmamış ya da bildirim yanlış markaya düşmüş olabilir.
 */
class UnknownPaymentReferenceException extends RuntimeException
{
    public function __construct(public readonly string $referans)
    {
        parent::__construct('Bildirimdeki ödeme referansı bulunamadı.');
    }
}
