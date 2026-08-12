<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Olay kaydı. (docs/domain-model.md §11)
 *
 * @property EventType $type
 * @property array<string, mixed>|null $payload
 */
class Event extends Model
{
    /** ⚠️ Yalnızca `RecordEvent` işi yazıyor — kütle atama yok. */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
