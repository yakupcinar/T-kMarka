<?php

namespace App\Domain\Catalog;

/**
 * Kategori kendi torununun altına taşınmak istendi.
 *
 * Engellenmeseydi ağaç kendi kuyruğunu yutar, `path` sonsuza gider ve alt
 * ağaç sorgusu asla dönmezdi.
 */
class CategoryCycleException extends CatalogConflictException
{
    public function __construct(public readonly string $tasinan, public readonly string $hedef)
    {
        parent::__construct("'{$tasinan}' kendi altındaki '{$hedef}' kategorisinin içine taşınamaz.");
    }

    public function cozum(): string
    {
        return 'Önce hedef kategoriyi bu dalın dışına taşıyın.';
    }
}
