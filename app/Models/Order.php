<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sipariş — ödeme ve fatura seviyesi. (docs/domain-model.md §7)
 *
 * ★ SİPARİŞ BİR FOTOĞRAFTIR: adres, fiyat, başlık ve sözleşme sürümü
 * satın alma anındaki hâliyle donuyor.
 *
 * ⚠️ İki bağımsız durum ekseni var. Tek alana sıkıştırılsaydı
 * `paid_partially_shipped_partially_refunded` gibi kombinasyon patlaması
 * başlardı.
 *
 * ⚠️ Para alanları `decimal:2` cast'i yüzünden METİN döndürüyor; statik
 * analiz kolonu gördüğü için float sanıyor.
 *
 * @property PaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string $items_total
 * @property string $discount_total
 * @property string $shipping_total
 * @property string $tax_total
 * @property string $grand_total
 */
class Order extends Model
{
    use HasUuids;

    /** ⚠️ Sipariş yalnızca servis tarafından yazılıyor — kütle atama yok. */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'terms_accepted_at' => 'datetime',
            'placed_at' => 'datetime',
            'items_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * Onaylanan sözleşme SÜRÜMÜ (1D-K2).
     *
     * ⚠️ "Güncel sözleşme" değil — müşterinin O AN gördüğü metin.
     *
     * @return BelongsTo<LegalDocumentVersion, $this>
     */
    public function legalVersion(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_version_id');
    }

    public function misafirSiparisiMi(): bool
    {
        return $this->customer_id === null;
    }
}
