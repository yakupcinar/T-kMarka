<?php

namespace App\Models;

use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KVKK veri talebi. (2G)
 *
 * @property DataRequestType $type
 * @property DataRequestStatus $status
 * @property CarbonInterface $expires_at
 */
class DataRequest extends Model
{
    use HasUuids;

    /** ⚠️ Yalnızca servis yazıyor — kütle atama yok. */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DataRequestType::class,
            'status' => DataRequestStatus::class,
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function suresiDoldu(): bool
    {
        return $this->expires_at->isPast();
    }
}
