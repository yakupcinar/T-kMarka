<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ürün görseli.
 *
 * ⚠️ `url()` için `Storage::disk('public')->url()` KULLANILMIYOR. Ölçüldü:
 * paket disk KÖKÜNÜ kiracıya çeviriyor ama URL'yi çevirmiyor —
 * `http://localhost/storage/a.jpg` hem yanlış alan adını hem merkez yolu
 * gösteriyor ve hata vermiyor.
 *
 * Doğrusu paketin `tenant_asset()` yardımcısı: adresi markanın kendi alan
 * adında üretiyor, dosyayı da o markanın klasöründen okuyor. İzolasyon
 * ADRES üzerinden sağlanıyor.
 */
class ProductImage extends Model
{
    use HasUuids;

    /** ⚠️ `path`, `product_id`, `variant_id` listede YOK — servis yazıyor. */
    protected $fillable = [
        'alt',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** Markanın kendi alan adında, kiracı bağlamlı görsel adresi. */
    public function url(): string
    {
        return tenant_asset($this->path);
    }
}
