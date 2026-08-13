<?php

namespace App\Domain\Returns;

use RuntimeException;

/**
 * Cayma süresi dolmuş. (2B-K2)
 *
 * ⚠️ 409 — ZAMAN sorunu. Yetki var, veri geçerli; geçen şey süre.
 * ⚠️ KUSURLU ÜRÜN iadesi bu istisnayı almaz: cayma değil, süresi yok.
 */
class ReturnWindowClosedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cayma hakkı süresi dolmuş.');
    }
}
