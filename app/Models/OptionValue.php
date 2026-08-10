<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eksen değeri — "Kırmızı", "M", "42".
 *
 * `swatch` (renk kodu) burada duruyor: eksen mağaza seviyesinde olduğu için
 * renk BİR KEZ tanımlanıyor, her üründe tekrar girilmiyor.
 */
class OptionValue extends Model
{
    use HasUuids;

    /**
     * ⚠️ `slug` ve `option_id` listede YOK.
     * `slug` → `value`'dan üretilir · `option_id` → ilişki üzerinden konur
     * (1A.5'te adres için kurduğumuz desenin aynısı).
     */
    protected $fillable = [
        'value',
        'swatch',
        'position',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Option, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }
}
