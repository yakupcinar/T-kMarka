<?php

namespace App\Domain\Quota;

use DomainException;

/**
 * Plan sınırı aşıldı. (3F)
 *
 * ⚠️ Bu istisna olmasaydı sınır SESSİZCE aşılırdı: plan satmanın anlamı
 * kalmaz, marka "sınırsız" kullanır ve fark edilmezdi.
 */
class QuotaExceededException extends DomainException
{
    public function __construct(
        string $mesaj,
        public readonly string $tur,
        public readonly ?int $limit = null,
    ) {
        parent::__construct($mesaj);
    }
}
