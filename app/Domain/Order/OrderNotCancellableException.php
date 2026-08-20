<?php

namespace App\Domain\Order;

use App\Enums\PaymentStatus;
use RuntimeException;

/**
 * Sipariş bu ödeme durumundayken iptal edilemez. (4.5J)
 *
 * ⚠️ Yalnızca `pending` sipariş müşteri tarafından iptal edilebiliyor.
 * Ödenmiş siparişin yolu İADE (2B) ve para iadesi zinciri; buradan
 * iptale izin verilseydi müşteri parasını geri almadan siparişini
 * kapatır, marka da göndermeyeceği bir siparişi tahsil etmiş olurdu.
 */
class OrderNotCancellableException extends RuntimeException
{
    public function __construct(
        public readonly string $siparisNo,
        public readonly PaymentStatus $durum,
    ) {
        parent::__construct(sprintf(
            'Sipariş %s bu durumda iptal edilemez (%s).',
            $siparisNo,
            $durum->value,
        ));
    }
}
