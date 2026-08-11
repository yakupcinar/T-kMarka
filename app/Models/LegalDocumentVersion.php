<?php

namespace App\Models;

use App\Enums\LegalDocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Yayınlanmış yasal metin — DEĞİŞMEZ.
 *
 * Yayınlamak metni değiştirmez, yeni satır doğurur. Eski satır olduğu yerde
 * kalır çünkü ona bağlı siparişler var: 15 Mart'ta verilen sipariş, 20 Mart'ta
 * yayınlanan sürüme değil kendi günündeki sürüme bağlıdır.
 *
 * ⚠️ Bu modelde `update()` ve `delete()` KULLANILMAZ. Kural yalnızca burada
 * yazılı değil — veritabanında tetikle zorlanıyor, deneyen `RAISE EXCEPTION`
 * alır (migration'a bakılabilir).
 *
 * ⚠️ `@property` notu şart: statik analiz `casts()`'ten enum'u çıkaramıyor,
 * kolonu varchar gördüğü için `type`'ı metin sanıyor.
 *
 * @property LegalDocumentType $type
 * @property int $version_no
 */
class LegalDocumentVersion extends Model
{
    /**
     * ⚠️ Laravel'in `created_at`/`updated_at` yönetimi kapalı.
     *
     * Bu tabloda `updated_at` yok — hiç güncellenmeyen bir satırda
     * "güncellenme zamanı" tutmak, güncellenebileceğini ima eder. Zaman
     * bilgisi `published_at` kolonunda ve onu servis yazıyor.
     */
    public $timestamps = false;

    protected $fillable = [
        'type',
        'version_no',
        'content',
        'published_at',
        'published_by_uuid',
        'published_by_name',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'version_no' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
