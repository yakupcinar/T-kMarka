<?php

namespace App\Domain\Legal;

use RuntimeException;

/**
 * Yayınlanmak istenen metinde doldurulamayan yer tutucu kaldı.
 *
 * Sebebi ya mağaza bilgisinin girilmemiş olması ya da tanınmayan bir yer
 * tutucu yazılmış olması. İkisinde de metin yayınlanmıyor: müşteriye
 * `{{unvan}}` gitmesindense hata iyidir.
 */
class UnfilledPlaceholderException extends RuntimeException
{
    /** @param list<string> $yerTutucular */
    public function __construct(public readonly array $yerTutucular)
    {
        parent::__construct(
            'Metinde doldurulamayan yer tutucu var: '.
            implode(', ', array_map(fn (string $y) => '{{'.$y.'}}', $yerTutucular))
        );
    }
}
