<?php

namespace App\Domain\Catalog;

/**
 * Ürün görsel sınırını aştı.
 *
 * Sınırın sebebi depolama değil, ürün sayfası: 40 görselli bir sayfa hem
 * müşteriyi hem tarayıcıyı yoruyor. Doğrulamada tutuluyor ki gevşetmek
 * tek satır olsun.
 */
class TooManyImagesException extends CatalogRuleException
{
    public function __construct(public readonly int $mevcut, public readonly int $sinir)
    {
        parent::__construct("Bir üründe en fazla {$sinir} görsel olabilir (şu an {$mevcut}).");
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['image' => [$this->getMessage()]];
    }
}
