<?php

namespace App\Domain\Catalog;

use RuntimeException;

/**
 * Kategori kendi torununun altına taşınmak istendi.
 *
 * Engellenmeseydi ağaç kendi kuyruğunu yutar, `path` sonsuza gider ve alt
 * ağaç sorgusu asla dönmezdi.
 */
class CategoryCycleException extends RuntimeException
{
    public function __construct(public readonly string $tasinan, public readonly string $hedef)
    {
        parent::__construct("'{$tasinan}' kendi altındaki '{$hedef}' kategorisinin içine taşınamaz.");
    }
}
