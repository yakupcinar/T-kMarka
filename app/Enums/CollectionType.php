<?php

namespace App\Enums;

/** Koleksiyon türü. (2D) */
enum CollectionType: string
{
    /**
     * Elle seçilmiş liste — üyeler `collection_product`'ta.
     *
     * ⚠️ Sıra MARKANIN kararı; vitrin `position`'ı koruyor. "Öne çıkanlar"
     * gibi koleksiyonlarda sıra içeriğin kendisi kadar önemli.
     */
    case Manual = 'manual';

    /**
     * Kuralla belirlenen liste — üyeler SORGU ANINDA hesaplanıyor (2D-K2).
     *
     * ⚠️ Üyelik hiçbir yere YAZILMIYOR. Yazılsaydı fiyat değişince
     * bayatlar ve kimse fark etmezdi: "250₺ altı" koleksiyonunda 400₺'lik
     * ürün dururdu.
     */
    case Rule = 'rule';
}
