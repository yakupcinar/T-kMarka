<?php

namespace App\Enums;

/**
 * Siparişin SEVKİYAT ekseni. (docs/domain-model.md §7)
 *
 * ⚠️ `fulfillments` tablosundan TÜRETİLİR, önbelleklenir. Elle yazılan bir
 * alan olsaydı kısmi sevkiyatta gerçekle uyuşmayan bir durum kalırdı ve
 * kimse fark etmezdi.
 */
enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Partial = 'partial';
    case Fulfilled = 'fulfilled';
}
