<?php

namespace App\Domain\Returns;

use App\Enums\ReturnStatus;
use RuntimeException;

/**
 * Talep para iadesine hazır değil. (2B-K1)
 *
 * ⚠️ ★ BLOĞUN EN ÖNEMLİ KORUMASI: ürün ELE GEÇMEDEN para gitmiyor.
 * Yalnızca `received` durumundaki talebin iadesi açılabiliyor.
 *
 * Olmasaydı: müşteri talep açar, marka onaylar, para gider — ürün hiç
 * gelmez. Ve bu bir hata olarak görünmez.
 */
class ReturnNotRefundableException extends RuntimeException
{
    public function __construct(public readonly ReturnStatus $durum)
    {
        parent::__construct('İade talebi para iadesine uygun durumda değil.');
    }
}
