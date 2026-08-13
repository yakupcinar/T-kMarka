<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ürün — müşterinin ARADIĞI şey. Satın aldığı şey varyant (1B-K1/K2).
 *
 * ⚠️ Ürünün FİYATI YOK. Fiyat varyantta; listede gösterilen "şu fiyattan
 * başlayan" tutarı aktif varyantların en düşüğünden TÜRETİLİYOR.
 *
 * ⚠️ `@property` notu şart: statik analiz `casts()` metodundan enum'u
 * çıkaramıyor, kolonu varchar gördüğü için `status`'ü metin sanıyor ve
 * enum karşılaştırmasını "her zaman false" diye işaretliyor.
 *
 * @property ProductStatus $status
 * @property string $tax_rate `decimal:2` cast'i METİN döndürüyor (float değil)
 * @property array<string, mixed>|null $attributes
 */
class Product extends Model
{
    use HasUuids;
    use SoftDeletes;

    /**
     * ⚠️ `slug` listede YOK — başlıktan üretiliyor.
     * `category_id` de yok — ilişki üzerinden konuyor (1A.5 deseni).
     */
    protected $fillable = [
        'title',
        'description',
        'brand',
        'model',
        'attributes',
        'tax_rate',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'attributes' => 'array',

            // ⚠️ `decimal:2` — float DEĞİL. Para float'ta tutulmaz
            // (domain-model §0): 0.1 + 0.2 ≠ 0.3.
            'tax_rate' => 'decimal:2',
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
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Bu ürünün kullandığı eksenler, ürüne özel sırasıyla.
     *
     * @return BelongsToMany<Option, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'product_options')
            ->withPivot('position')
            ->orderBy('product_options.position')
            ->orderBy('options.id');
    }

    /**
     * Ürünün elle eklendiği koleksiyonlar. (2D)
     *
     * ⚠️ Yalnızca MANUEL koleksiyonlar. Kurallı koleksiyonun üyeliği hiçbir
     * yere yazılmıyor (2D-K2), bu ilişkide de görünmez — "ürün hangi
     * koleksiyonlarda" sorusunun tam cevabı değil, elle eklendiklerinin
     * cevabı.
     *
     * @return BelongsToMany<ProductCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id')
            ->withPivot('position')
            ->withTimestamps();
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Vitrinde gösterilecek "şu fiyattan başlayan" tutarı.
     *
     * ⚠️ Yalnızca SATIN ALINABİLİR varyantlara bakıyor: tükenmiş bir
     * varyantın ucuz fiyatını göstermek, müşteriyi seçemeyeceği bir
     * fiyatla çağırmak olurdu.
     */
    public function enDusukFiyat(): ?string
    {
        $fiyatlar = $this->variants
            ->filter(fn (ProductVariant $v) => $v->satinAlinabilirMi())
            ->pluck('price');

        return $fiyatlar->isEmpty() ? null : (string) $fiyatlar->min();
    }

    /**
     * Vitrinde görünmeli mi? (1B-K8)
     *
     * Üç kaynağın TEK cevabı: markanın kararı (status) + markanın seçenek
     * kararı (is_active) + sistemin ölçtüğü stok.
     */
    public function vitrindeGorunurMu(): bool
    {
        return $this->status === ProductStatus::Active
            && $this->variants->contains(fn (ProductVariant $v) => $v->satinAlinabilirMi());
    }
}
