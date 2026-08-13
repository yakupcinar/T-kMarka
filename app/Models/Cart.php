<?php

namespace App\Models;

use App\Enums\CartStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sepet — misafirin ya da müşterinin. (1C-K1)
 *
 * ⚠️ SEPETTE FİYAT YOK. Sepet canlıdır: fiyatı her okuyuşta varyanttan
 * alır. Marka fiyatı değiştirirse sepette de değişir — doğrusu bu. Fiyat
 * ancak SİPARİŞ anında donuyor (1D, "sipariş bir fotoğraftır").
 *
 * @property CartStatus $status
 */
class Cart extends Model
{
    use HasUuids;

    /**
     * ⚠️ `customer_id`, `session_token` ve `status` listede YOK.
     * Üçü de sahiplik/durum alanı; servis yazıyor (1A.1 deseni).
     */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * ⚠️ `coupon_code` doğrudan yazılıyor: `$fillable` boş (1C'de kütle
     * atama kapatıldı) ve `update()` de kapalı.
     */
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

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function misafirMi(): bool
    {
        return $this->customer_id === null;
    }
}
