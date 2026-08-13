<?php

namespace App\Enums;

/**
 * PARA İADESİNİN durumu. (2B-K1)
 *
 * ⚠️ Ödeme tarafındaki `PaymentAttemptStatus`'ün aynası: orada para
 * geliyordu, burada gidiyor. Aynı tuzaklar geçerli — özellikle
 * idempotanslık (2B-K7).
 */
enum RefundStatus: string
{
    /** Sağlayıcıya gönderildi, sonuç bilinmiyor. */
    case Pending = 'pending';

    /** Para gerçekten geri gitti. */
    case Completed = 'completed';

    case Failed = 'failed';
}
