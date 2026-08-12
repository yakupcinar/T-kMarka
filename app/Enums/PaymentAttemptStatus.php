<?php

namespace App\Enums;

/**
 * Tek bir ödeme DENEMESİNİN durumu. (docs/domain-model.md §10)
 *
 * ⚠️ `PaymentStatus` ile karıştırılmamalı — ikisi farklı seviyeler:
 *
 *   PaymentStatus         siparişin ÖZETİ      orders.payment_status
 *   PaymentAttemptStatus  tek denemenin hâli   payments.status
 *
 * Bir siparişin birden çok denemesi olur: kart reddedilir (`failed`),
 * müşteri başka kartla tekrar dener (`captured`). Sipariş özeti `paid`
 * olur ama başarısız deneme kaydı **durur** — "neden ödeme alınamadı"
 * sorusunun cevabı orada.
 */
enum PaymentAttemptStatus: string
{
    /** Sağlayıcıya gönderildi, sonuç henüz bilinmiyor. */
    case Pending = 'pending';

    /**
     * Banka tutarı BLOKE etti ama para henüz çekilmedi (ön provizyon).
     *
     * ⚠️ Faz 1'de kullanılmıyor — tek adımlı tahsilat yapıyoruz. Enum'da
     * durmasının sebebi: ön provizyon eklendiğinde `payments.status`
     * kolonuna yeni değer sığdırmak yerine akış buraya bağlanacak.
     */
    case Authorized = 'authorized';

    /** Para çekildi. Siparişi `paid` yapan tek durum budur. */
    case Captured = 'captured';

    case Failed = 'failed';
}
