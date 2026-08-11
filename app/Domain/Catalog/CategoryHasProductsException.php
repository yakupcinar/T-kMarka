<?php

namespace App\Domain\Catalog;

/**
 * İçinde ürün olan kategori silinmek istendi.
 *
 * ⚠️ `nullOnDelete` seçilseydi marka kategoriyi silince 300 ürün sessizce
 * kategorisiz kalır, menüden ve kategori sayfalarından düşer, panelde
 * "neden kimse bu ürünleri görmüyor" sorusu doğardı.
 */
class CategoryHasProductsException extends CatalogConflictException
{
    public function __construct(public readonly string $ad, public readonly int $urunSayisi)
    {
        parent::__construct("'{$ad}' kategorisinde {$urunSayisi} ürün var, silinemez.");
    }

    public function cozum(): string
    {
        return 'Önce ürünleri başka bir kategoriye taşıyın.';
    }

    /** @return array<string, mixed> */
    public function ayrintilar(): array
    {
        return ['product_count' => $this->urunSayisi];
    }
}
