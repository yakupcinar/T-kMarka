<?php

namespace App\Domain\Cart;

use RuntimeException;

/**
 * Satın alınamayan varyant sepete eklenmek istendi.
 *
 * ⚠️ Sepete GİRİŞTE sert davranıyoruz; sepetTEYKEN satılamaz hâle gelen
 * satır ise SİLİNMİYOR, işaretleniyor (1C-K2). İkisi farklı durum:
 *   girişte  → kullanıcı zaten satın alamayacağı bir şeyi seçmiş
 *   sonradan → kullanıcının seçtiği şey elinden alınmış, bunu görmeli
 */
class VariantNotPurchasableException extends RuntimeException
{
    public function __construct(public readonly string $sku)
    {
        parent::__construct("'{$sku}' şu anda satın alınamıyor.");
    }
}
