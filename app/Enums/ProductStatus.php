<?php

namespace App\Enums;

/**
 * Ürünün marka tarafındaki durumu. (1B-K8)
 *
 * ⚠️ "Tükendi" BURADA YOK ve olmayacak. O bir durum değil, stoktan
 * türetilen bir SONUÇ. Saklansaydı "müşteri son adedi alınca kim
 * çevirecek, marka stok girince kim geri çevirecek, iade gelince kim?"
 * sorularının her biri ayrı bir kod yolu olurdu; biri unutulunca "stokta
 * var ama sayfada tükendi yazıyor" olur ve hata vermezdi.
 *
 * Buradaki üç değer markanın KARARI; stok ise sistemin ölçtüğü şey.
 */
enum ProductStatus: string
{
    /** Üzerinde çalışılıyor. Vitrinde yok. */
    case Draft = 'draft';

    /** Satışta — ama satılabilir varyantı yoksa yine vitrinde görünmez. */
    case Active = 'active';

    /** Kaldırıldı. Vitrinde yok, panelde duruyor. Geçmiş siparişler bozulmaz. */
    case Archived = 'archived';

    public function etiket(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Active => 'Satışta',
            self::Archived => 'Arşivlendi',
        };
    }
}
