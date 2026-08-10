<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Varyant ekseni — "Renk", "Beden". MAĞAZA seviyesinde tanımlı (1B-K3).
 *
 * Bütün ürünler aynı listeden seçer; böylece kategori sayfasındaki
 * "Renk: Kırmızı" filtresi tek bir değere karşılık gelir.
 */
class Option extends Model
{
    use HasUuids;

    /**
     * ⚠️ `slug` listede YOK — dışarıdan gelmez, `name`'den ÜRETİLİR.
     * Gelseydi marka "Renk" adına "beden" slug'ı verip filtreyi bozabilirdi.
     */
    protected $fillable = [
        'name',
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

    /** @return HasMany<OptionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->orderBy('position')->orderBy('id');
    }
}
