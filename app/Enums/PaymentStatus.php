<?php

namespace App\Enums;

/**
 * Siparişin ÖDEME ekseni. (docs/domain-model.md §7)
 *
 * ⚠️ Sevkiyat ekseninden AYRI. Tek `status` alanına sıkıştırılsaydı
 * `paid_shipped`, `paid_partially_shipped_partially_refunded` gibi
 * kombinasyon patlaması başlardı. İki bağımsız eksen, iki bağımsız alan.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
