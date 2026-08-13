<?php

namespace App\Models;

use App\Enums\CouponType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kupon. (2A)
 *
 * @property CouponType $type
 * @property string $value
 * @property string $min_subtotal
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
class Coupon extends Model
{
    use HasUuids;

    /** ⚠️ Yalnızca servis yazıyor — `used_count` dışarıdan asla. */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'used_count' => 0,
        'value' => 0,
        'min_subtotal' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'is_active' => 'boolean',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Takvim ve kota AÇIK mı? (Sepet tutarı ayrı kontrol.)
     *
     * ⚠️ `used_count` burada okunuyor ama KARAR BURADA VERİLMİYOR:
     * yarışa karşı asıl kontrol satır kilidiyle (2A-K3).
     */
    public function yururlukteMi(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }
}
