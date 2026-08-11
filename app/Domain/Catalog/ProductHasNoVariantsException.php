<?php

namespace App\Domain\Catalog;

/**
 * Varyantsız ürün satışa alınmak istendi.
 *
 * ⚠️ 1B-K1'in ("her ürünün en az bir varyantı olur") uygulamadaki yeri
 * burası. Taslakta varyantsız durabilmesi kasıtlı: marka ürünü birkaç
 * oturumda hazırlıyor, fiyatı henüz bilmiyor olabilir. Ama SATIŞA alınan
 * ürünün satılacak bir şeyi olmak zorunda.
 *
 * Aynı asimetri 1A.4'te de vardı: taslağa yazmak serbest, yayınlamak
 * denetimli.
 */
class ProductHasNoVariantsException extends CatalogRuleException
{
    public function __construct(public readonly string $baslik)
    {
        parent::__construct("'{$baslik}' satışa alınamaz: hiç varyantı yok.");
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['status' => [$this->getMessage()]];
    }
}
