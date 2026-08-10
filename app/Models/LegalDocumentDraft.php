<?php

namespace App\Models;

use App\Enums\LegalDocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Yasal metnin ÜZERİNDE ÇALIŞILAN hâli. Tür başına tek satır.
 *
 * Serbestçe güncellenir ve yarım kalabilir — marka metni birkaç oturumda
 * yazar. Dışarıya (vitrine, müşteriye) asla çıkmaz; dışarı çıkan şey
 * yayınlanmış sürümdür (bkz. [LegalDocumentVersion]).
 */
class LegalDocumentDraft extends Model
{
    protected $fillable = [
        'type',
        'content',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
        ];
    }
}
