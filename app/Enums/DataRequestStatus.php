<?php

namespace App\Enums;

/**
 * Veri talebinin durumu. (2G)
 *
 * ⚠️ `Pending` ile `Verified` arasındaki fark KRİTİK: doğrulanmamış
 * talep işlenmiyor. Olmasaydı sipariş numarası tahmin eden biri
 * (numaralar ardışık, 1D-K4) başkasının verisini sildirebilirdi.
 */
enum DataRequestStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Completed = 'completed';
    case Expired = 'expired';
}
