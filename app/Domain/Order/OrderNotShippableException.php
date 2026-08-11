<?php

namespace App\Domain\Order;

use App\Enums\PaymentStatus;
use RuntimeException;

/**
 * Ödenmemiş sipariş sevk edilmek istendi.
 *
 * ⚠️ Kapıda ödeme (COD) olsaydı bu kural gevşerdi — Faz 1'de yok.
 * Olmasaydı ödemesi başarısız bir sipariş kargoya verilebilir ve para hiç
 * tahsil edilmezdi.
 */
class OrderNotShippableException extends RuntimeException
{
    public function __construct(
        public readonly string $siparisNo,
        public readonly PaymentStatus $odemeDurumu,
    ) {
        parent::__construct("{$siparisNo} sevk edilemez: ödeme durumu '{$odemeDurumu->value}'.");
    }
}
