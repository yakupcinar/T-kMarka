<?php

namespace App\Domain\Stock;

use RuntimeException;

/**
 * Rezervasyon anında stok yetmedi.
 *
 * ⚠️ Sepette YUMUŞAK kontrol vardı (kırpma, 1C-K3); burası BAĞLAYICI olan.
 * Arada başkası aynı ürünü almış olabilir — sepet rezerve etmiyor.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly int $istenen,
        public readonly int $mevcut,
    ) {
        parent::__construct("'{$sku}' için yeterli stok yok: {$istenen} istendi, {$mevcut} kaldı.");
    }
}
