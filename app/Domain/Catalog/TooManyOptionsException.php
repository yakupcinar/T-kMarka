<?php

namespace App\Domain\Catalog;

/**
 * Ürüne 3'ten fazla eksen bağlanmak istendi. (1B-K4)
 *
 * ⚠️ Sınır veritabanında değil burada. Shopify 10+ yıl "3 eksen · 100
 * varyant" ile yaşadı; sebebi kombinatorik patlama (6×5×4×3 = 360 varyant
 * panelde de sorguda da boğulur). DB'ye koymak sonradan gevşetmeyi
 * migration'a çevirirdi, doğrulamada tutmak tek satırlık değişiklik.
 */
class TooManyOptionsException extends CatalogRuleException
{
    public function __construct(public readonly int $sayi)
    {
        parent::__construct("Bir üründe en fazla 3 eksen olabilir; {$sayi} gönderildi.");
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['option_uuids' => [$this->getMessage()]];
    }
}
