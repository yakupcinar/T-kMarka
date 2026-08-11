<?php

namespace App\Domain\Order;

use RuntimeException;

/**
 * Sepet sipariş verilebilir durumda değil.
 *
 * ⚠️ Sepette YUMUŞAK kontrol vardı (1C-K3): ölü satır silinmiyor,
 * işaretleniyor. Burası BAĞLAYICI olan — engel varsa sipariş HİÇ
 * başlamıyor, rezervasyon da yapılmıyor.
 */
class CartNotOrderableException extends RuntimeException
{
    /** @param  list<array{sku: string, sorun: string}>  $engeller */
    public function __construct(public readonly array $engeller)
    {
        parent::__construct($engeller === []
            ? 'Sepet boş.'
            : 'Sepette sipariş vermeyi engelleyen satırlar var.');
    }
}
