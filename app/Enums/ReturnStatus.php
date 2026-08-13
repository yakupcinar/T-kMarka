<?php

namespace App\Enums;

/**
 * İade TALEBİNİN durumu. (2B-K1)
 *
 * ⚠️ `RefundStatus` ile karıştırılmamalı — ikisi ayrı akış:
 *
 *   ReturnStatus   ürün nerede?      müşteri → marka
 *   RefundStatus   para nerede?      marka → müşteri
 *
 * Karıştırılırsa ya para ürün gelmeden gider ya stok gelmeden açılır.
 */
enum ReturnStatus: string
{
    /** Müşteri talep etti, marka henüz bakmadı. */
    case Requested = 'requested';

    /** Marka onayladı, ürün yolda. */
    case Approved = 'approved';

    /** Marka reddetti (süre dolmuş, ürün iadeye uygun değil). */
    case Rejected = 'rejected';

    /** Ürün ELDE. Stok geri girebilir, para iadesi açılabilir. */
    case Received = 'received';

    case Completed = 'completed';

    public function acikMi(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Received], strict: true);
    }
}
