<?php

namespace App\Domain\Returns;

use RuntimeException;

/**
 * Sipariş edilenden fazla iade denemesi.
 *
 * ★ `OverShipmentException`'ın (1D.4) aynası: orada fazla GÖNDERİM,
 * burada fazla İADE.
 *
 * ⚠️ Engellenmeseydi müşteri aynı satırı iki talepte iade eder ve ürün
 * bedelinin iki katını geri alırdı — hatasız.
 */
class OverReturnException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly int $siparisAdedi,
        public readonly int $istenen,
    ) {
        parent::__construct("{$sku}: sipariş edilenden fazla iade edilemez.");
    }
}
