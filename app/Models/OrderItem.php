<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Donmuş sipariş satırı. (docs/domain-model.md §7)
 *
 * ★ `product_title`, `variant_options`, `sku`, `unit_price` ve `tax_rate`
 * KOPYADIR. Ürüne join'lenip fiyat oradan okunsaydı, marka yarın fiyatı
 * değiştirdiğinde geçmiş siparişlerin tutarı da değişirdi.
 *
 * `variant_id` yalnızca referans ve NULL olabilir — varyant silinse bile
 * bu satır siparişin ne olduğunu tek başına anlatıyor.
 *
 * @property array<string, string> $variant_options
 * @property int $quantity
 */
class OrderItem extends Model
{
    /** ⚠️ Satır yalnızca servis tarafından yazılıyor. */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'variant_options' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
