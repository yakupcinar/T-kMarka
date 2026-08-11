<?php

namespace App\Enums;

/**
 * Tek bir SEVKİYATIN (paketin) durumu. (docs/domain-model.md §7)
 *
 * ⚠️ `Order::$fulfillment_status` ile karıştırılmamalı. O, siparişin
 * TAMAMININ ne kadarının gittiğini söylüyor ve buradan TÜRETİLİYOR:
 *
 *   fulfillments.status  → bu paket nerede
 *   orders.fulfillment_status → siparişin kaçta kaçı gitti
 */
enum ShipmentStatus: string
{
    /** Paket hazırlanıyor — henüz kargoya verilmedi. */
    case Pending = 'pending';

    case Shipped = 'shipped';
    case Delivered = 'delivered';

    /**
     * İptal edildi.
     *
     * ⚠️ İptal edilen paketin adetleri "sevk edilmiş" SAYILMAZ — o satırlar
     * yeniden sevk edilebilir olmalı.
     */
    case Cancelled = 'cancelled';
}
