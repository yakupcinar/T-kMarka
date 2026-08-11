<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stok rezervasyonu — istekler ARASINDA yaşayan kilit. (1D-K1/K3)
 *
 * ⚠️ Satır kilidiyle karıştırılmamalı. İkisi farklı iş yapıyor:
 *   satır kilidi (FOR UPDATE)  mikrosaniye — sayacın okunup yazılması arası
 *   rezervasyon (15 dk)        istekler arası — müşteri ödeme sayfasındayken
 *
 * @property ReservationStatus $status
 * @property int $quantity
 */
class StockReservation extends Model
{
    use HasUuids;

    /** ⚠️ Hepsi servis tarafından yazılıyor — kütle atama yok. */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'held',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Rezervasyonu tüketen sipariş.
     *
     * Sipariş oluştuktan sonra sepet `converted` oluyor; ödemenin sonucuna
     * göre rezervasyonu kesinleştirmek/serbest bırakmak gerektiğinde
     * elimizde sepet değil SİPARİŞ oluyor.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
