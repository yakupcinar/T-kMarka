<?php

namespace App\Domain\Catalog;

/**
 * Alt kategorisi olan kategori silinmek istendi.
 *
 * Cascade olsaydı marka "Giyim"i silince altındaki bütün dal sessizce
 * giderdi. Rol silmedeki kararla aynı: sessiz yeniden yapılandırma yerine
 * bilinçli hamle.
 */
class CategoryHasChildrenException extends CatalogConflictException
{
    public function __construct(public readonly string $ad, public readonly int $altSayisi)
    {
        parent::__construct("'{$ad}' kategorisinin {$altSayisi} alt kategorisi var, silinemez.");
    }

    public function cozum(): string
    {
        return 'Önce alt kategorileri taşıyın veya silin.';
    }

    /** @return array<string, mixed> */
    public function ayrintilar(): array
    {
        return ['children_count' => $this->altSayisi];
    }
}
