<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bir PAKET. Bir siparişin birden çok paketi olabilir (§7).
 *
 * @property ShipmentStatus $status
 */
class Fulfillment extends Model
{
    use HasUuids;

    /** ⚠️ Durum ve zaman damgaları yalnızca servis tarafından yazılıyor. */
    protected $fillable = [
        'carrier',
        'tracking_number',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<FulfillmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }

    /** İptal edilen paket "sevk edilmiş" sayılmıyor. */
    public function sayilirMi(): bool
    {
        return $this->status !== ShipmentStatus::Cancelled;
    }
}
