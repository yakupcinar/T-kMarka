<?php

namespace App\Domain\Order;

use RuntimeException;

/**
 * Sipariş adedinden fazla sevk edilmek istendi.
 *
 * ⚠️ Kısmi sevkiyatın TEK doğrulama kuralı bu. Engellenmeseydi marka aynı
 * ürünü iki kez gönderir, stok gerçekle uyuşmaz ve iade hesabı tutmazdı —
 * hiçbiri hata vermeden.
 */
class OverShipmentException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly int $siparisAdedi,
        public readonly int $istenenToplam,
    ) {
        parent::__construct($siparisAdedi === 0
            ? 'Sevk edilecek satır seçilmedi.'
            : "'{$sku}' için sipariş adedi {$siparisAdedi}; toplam {$istenenToplam} sevk edilemez.");
    }
}
