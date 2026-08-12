<?php

namespace App\Domain\Payment;

use App\Enums\PaymentStatus;
use RuntimeException;

/**
 * Sipariş ödemeye uygun durumda değil.
 *
 * ⚠️ 409 — ZAMAN/DURUM sorunu. Yetki var, gönderilen veri geçerli;
 * yanlış olan siparişin şu anki hâli (zaten ödenmiş, iptal edilmiş…).
 */
class OrderNotPayableException extends RuntimeException
{
    public function __construct(
        public readonly string $siparisNumarasi,
        public readonly PaymentStatus $odemeDurumu,
    ) {
        parent::__construct("{$siparisNumarasi} numaralı sipariş ödemeye uygun değil.");
    }
}
