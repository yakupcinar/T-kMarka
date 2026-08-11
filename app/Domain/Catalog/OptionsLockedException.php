<?php

namespace App\Domain\Catalog;

/**
 * Varyantı olan ürünün eksenleri değiştirilmek istendi.
 *
 * ⚠️ İzin verilseydi mevcut varyantlar ANINDA geçersizleşirdi: "Beden"
 * ekseni eklenince eldeki `{"renk":"kirmizi"}` varyantı eksik anahtarlı
 * olur, ürün sayfasında seçilemez hâle gelir ve stok orada asılı kalırdı —
 * hata vermeden.
 *
 * Marka önce varyantları silmek zorunda; bilinçli hamle.
 */
class OptionsLockedException extends CatalogConflictException
{
    public function __construct(public readonly int $varyantSayisi)
    {
        parent::__construct("Ürünün {$varyantSayisi} varyantı var; eksenleri değiştirilemez.");
    }

    public function cozum(): string
    {
        return 'Önce varyantları silin, sonra eksenleri düzenleyin.';
    }

    /** @return array<string, mixed> */
    public function ayrintilar(): array
    {
        return ['variant_count' => $this->varyantSayisi];
    }
}
