<?php

namespace App\Models;

use App\Enums\CollectionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Koleksiyon — "nerede göstereyim" sorusu. (2D)
 *
 * ⚠️ SINIF ADI `Collection` DEĞİL: Laravel'in `Illuminate\Support\Collection`
 * ve `Eloquent\Collection` sınıfları her dosyada import edili. Aynı adı
 * kullansaydık her `use` satırında takma ad gerekirdi ve bir gün biri
 * yanlış olanı import edip statik analizin yakalamadığı bir hata yazardı.
 * Tablo adı `collections` kalıyor, yalnızca sınıf adı ayrık.
 *
 * @property CollectionType $type
 * @property array{match: string, conditions: list<array<string, string>>}|null $rules
 */
class ProductCollection extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'collections';

    /**
     * ⚠️ `type` ve `rules` listede YOK — ikisi birlikte tutarlı olmak
     * zorunda (`manual` ise kural yok, `rule` ise kural şart) ve bu kontrol
     * `CollectionService`'te. Toplu atamayla girseydi tip `rule`, kural
     * `null` olan bir koleksiyon yaratılabilirdi: vitrin onu açtığında ne
     * ürün gösterirdi ne hata verirdi.
     *
     * ⚠️ `slug` de yok — başlıktan üretiliyor (1B deseni).
     */
    protected $fillable = [
        'title',
        'description',
        'position',
        'is_active',
    ];

    /**
     * ⚠️ Kolon varsayılanı modele ULAŞMAZ (CLAUDE.md). `is_active` üç kez
     * ısırdı; burada baştan yazılı.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'position' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'rules' => 'array',
            'is_active' => 'boolean',
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

    /**
     * Elle seçilmiş üyeler.
     *
     * ⚠️ Yalnızca `manual` koleksiyonda anlamlı. Kurallı koleksiyonda bu
     * ilişki BOŞ olmak zorunda — dolu olsaydı "bu ürün neden burada"
     * sorusunun iki farklı cevabı olurdu.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product', 'collection_id', 'product_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position')
            ->orderByPivot('id');
    }
}
