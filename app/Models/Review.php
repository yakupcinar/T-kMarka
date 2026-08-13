<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ürün yorumu. (2E)
 *
 * ⚠️ `@property` notları şart: statik analiz `casts()` metodundan enum ve
 * tarih dönüşümünü çıkaramıyor, kolonu varchar gördüğü için `moderated_at`'i
 * metin sanıyor (Product'taki `status` ile aynı durum).
 *
 * @property ReviewStatus $status
 * @property CarbonInterface|null $moderated_at
 */
class Review extends Model
{
    use HasUuids;
    use SoftDeletes;

    /**
     * ⚠️ `status`, `customer_id`, `product_id` ve `order_item_id` LİSTEDE
     * YOK. Dördü de sahiplik/yetki alanı ($fillable = "neyi asla dışarıdan
     * almam"). `status` girseydi müşteri kendi yorumunu onaylardı;
     * `customer_id` girseydi başkasının adına yorum yazılırdı.
     */
    protected $fillable = [
        'rating',
        'title',
        'body',
    ];

    /**
     * ⚠️ Kolon varsayılanı modele ULAŞMAZ (CLAUDE.md, üç kez ısırdı).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ReviewStatus::Pending->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'moderated_at' => 'datetime',
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
