<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori — ağaç. (1B-K6)
 *
 * `path` ve `level` TÜRETİLMİŞ alanlar: `CategoryService` yazıyor, elle
 * dokunulmuyor. Bu yüzden ikisi de `$fillable` dışında — dışarıdan
 * yazılabilseydi ağaç sessizce tutarsız hâle gelirdi ("Giyim'in altındaki
 * her şey" sorgusu eksik sonuç dönerdi ve kimse fark etmezdi).
 */
class Category extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /**
     * Bu kategori ve ALTINDAKİ HER ŞEY — tek ön ek taraması.
     *
     * Kendisini de kapsıyor çünkü `path` kendi id'siyle bitiyor.
     * "Giyim'deki ürünler" sorgusunun temeli bu.
     *
     * @param  Builder<Category>  $sorgu
     * @return Builder<Category>
     */
    public function scopeAltAgac($sorgu, self $kategori)
    {
        return $sorgu->where('path', 'like', $kategori->path.'%');
    }

    /**
     * Ekmek kırıntısı için ata id'leri — kendisi HARİÇ.
     *
     * `path` zaten zinciri taşıdığı için ayrı sorguya gerek yok:
     * "/1/5/12/" → [1, 5]
     *
     * @return list<int>
     */
    public function ataIdleri(): array
    {
        $parcalar = array_filter(explode('/', $this->path), fn (string $p) => $p !== '');
        array_pop($parcalar);   // sondaki kendisi

        return array_values(array_map('intval', $parcalar));
    }
}
