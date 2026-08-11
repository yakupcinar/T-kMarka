<?php

namespace App\Domain\Stock;

use RuntimeException;

/**
 * Satır kilidi zaman aşımına uğradı. (1D-K6)
 *
 * ⚠️ PostgreSQL varsayılanda kilit için SONSUZA KADAR bekler. Takılan tek
 * bir işlem, arkasındaki bütün ödeme isteklerini asar ve mağaza donmuş
 * görünür. `lock_timeout` bunu kesiyor.
 *
 * Zaman aşımında sipariş OLUŞMUYOR — kilit kurulamadan hiçbir şey
 * yazılmadığı için aşırı satış riski de yok. Müşteri tekrar deniyor.
 */
class StockLockTimeoutException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Stok şu anda başka bir işlem tarafından güncelleniyor, lütfen tekrar deneyin.');
    }
}
