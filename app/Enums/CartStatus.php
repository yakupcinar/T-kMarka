<?php

namespace App\Enums;

/**
 * Sepetin yaşam döngüsü. (docs/domain-model.md §6)
 */
enum CartStatus: string
{
    /** Üzerinde çalışılıyor — müşteri ekleyip çıkarıyor. */
    case Active = 'active';

    /** Siparişe dönüştü (1D). Bir daha değiştirilmez. */
    case Converted = 'converted';

    /** Terk edildi. Hatırlatma işi bunları tarayacak (Faz 3). */
    case Abandoned = 'abandoned';
}
