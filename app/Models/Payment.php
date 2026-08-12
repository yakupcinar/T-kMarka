<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tek bir ödeme denemesi. (docs/domain-model.md §10)
 *
 * @property PaymentAttemptStatus $status
 * @property string $amount
 * @property array<string, mixed>|null $raw_response
 */
class Payment extends Model
{
    use HasUuids;

    /**
     * ⚠️ BOŞ — ödeme kaydı yalnızca `PaymentService` tarafından yazılıyor.
     * `status` ve `amount` dışarıdan alınabilseydi bir uç yanlışlıkla
     * "captured" yazabilir, sipariş ödenmemişken ödenmiş sayılırdı.
     */
    protected $fillable = [];

    /**
     * ⚠️ Kolon varsayılanı modele ULAŞMIYOR (CLAUDE.md). Beşinci kez —
     * bu sefer yazmadan önce hatırlandı.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount' => 'decimal:2',
            'raw_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Para gerçekten çekildi mi? Siparişi `paid` yapan tek durum. */
    public function tahsilEdildiMi(): bool
    {
        return $this->status === PaymentAttemptStatus::Captured;
    }
}
