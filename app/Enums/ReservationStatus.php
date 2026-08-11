<?php

namespace App\Enums;

/**
 * Stok rezervasyonunun durumu. (1D-K1)
 *
 * ⚠️ Rezervasyon bir KİLİT — ama satır kilidi değil, istekler ARASINDA
 * yaşayan kalıcı bir kilit. Müşteri ödeme sayfasındayken stoğun
 * kapılmamasını sağlıyor.
 */
enum ReservationStatus: string
{
    /** Tutuldu — `committed` sayacına dâhil, 15 dakika geçerli. */
    case Held = 'held';

    /** Ödeme başarılı: stok gerçekten düştü. */
    case Committed = 'committed';

    /** Ödeme başarısız ya da süre doldu: stok geri verildi. */
    case Released = 'released';
}
