<?php

namespace App\Domain\Catalog;

/**
 * Ürün varyant sınırını aştı. (1B-K4)
 *
 * ⚠️ Sınır kombinatorik patlamayı durduruyor: 6×5×4 = 120 daha yönetilebilir
 * ama 6×5×4×3 = 360 varyantta panel de sorgu da boğulur. Shopify 10+ yıl
 * 100 ile yaşadı; biz 200 diyoruz.
 *
 * Veritabanında değil doğrulamada: gevşetmek gerekirse tek satır değişecek,
 * migration yazılmayacak.
 */
class TooManyVariantsException extends CatalogRuleException
{
    public function __construct(public readonly int $mevcut, public readonly int $sinir)
    {
        parent::__construct("Bir üründe en fazla {$sinir} varyant olabilir (şu an {$mevcut}).");
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['variants' => [$this->getMessage()]];
    }
}
