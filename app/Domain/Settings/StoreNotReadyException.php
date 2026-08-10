<?php

namespace App\Domain\Settings;

use RuntimeException;

/**
 * Mağaza yayına alınmak istendi ama zorunlu alanlar eksik.
 *
 * Eksiklerin TAMAMI taşınıyor: marka altı kez "yayınla → eksik" turu
 * atmasın diye.
 */
class StoreNotReadyException extends RuntimeException
{
    /** @param list<string> $eksikler */
    public function __construct(public readonly array $eksikler)
    {
        parent::__construct('Mağaza yayına hazır değil: '.implode(', ', $eksikler));
    }
}
