<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * İade TALEBİ — ürünün geri yolculuğu. (2B-K1)
 *
 * ⚠️ Sınıf adı `Return` OLAMAZ: PHP'nin ayrılmış kelimesi. Tablo adı
 * `returns`, model `OrderReturn`.
 *
 * @property ReturnStatus $status
 */
class OrderReturn extends Model
{
    use HasUuids;

    protected $table = 'returns';

    /** ⚠️ Yalnızca servis yazıyor. */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'requested',
        'is_withdrawal' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'is_withdrawal' => 'boolean',
            'decided_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * ⚠️ Rota bağlaması UUID ile: sıralı `id` adres satırında görünseydi
     * markanın kaç iade aldığı dışarıdan sayılabilirdi.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<ReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
