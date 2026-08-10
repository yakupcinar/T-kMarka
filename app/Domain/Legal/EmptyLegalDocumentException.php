<?php

namespace App\Domain\Legal;

use App\Enums\LegalDocumentType;
use RuntimeException;

/**
 * Boş taslak yayınlanmaya çalışıldı.
 *
 * Sürüm satırının varlığı "bu metin yürürlükte" demektir; boş bir sürüm,
 * sözleşmesi olmayan bir sipariş üretirdi.
 */
class EmptyLegalDocumentException extends RuntimeException
{
    public function __construct(public readonly LegalDocumentType $tur)
    {
        parent::__construct("{$tur->etiket()} boş, yayınlanamaz.");
    }
}
