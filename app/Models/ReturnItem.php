<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * İade talebindeki satır. (2B-K3)
 *
 * ⚠️ İade SATIR BAZLI — tutar yazarak değil. Tutar bazlı olsaydı verginin
 * hangi satırdan düştüğü bilinemez, KDV hesabı tutmazdı (Magento da satır
 * seçtiriyor).
 *
 * @property int $quantity
 */
class ReturnItem extends Model
{
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /** @return BelongsTo<OrderReturn, $this> */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
