<?php

namespace App\Domain\Catalog;

use RuntimeException;

/**
 * Alt kategorisi olan kategori silinmek istendi.
 *
 * Cascade olsaydı marka "Giyim"i silince altındaki bütün dal sessizce
 * giderdi. Rol silmedeki kararla aynı: sessiz yeniden yapılandırma yerine
 * bilinçli hamle.
 */
class CategoryHasChildrenException extends RuntimeException
{
    public function __construct(public readonly string $ad, public readonly int $altSayisi)
    {
        parent::__construct("'{$ad}' kategorisinin {$altSayisi} alt kategorisi var, silinemez.");
    }
}
