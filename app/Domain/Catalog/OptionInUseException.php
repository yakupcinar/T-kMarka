<?php

namespace App\Domain\Catalog;

/**
 * Ürünlerde kullanılan eksen silinmek istendi.
 *
 * ⚠️ Silinebilseydi o ürünlerin varyantları anında geçersizleşirdi:
 * `{"renk":"kirmizi"}` diyen varyant, artık var olmayan bir ekseni işaret
 * eder; ürün sayfası seçici çizemez ve stok orada asılı kalır.
 *
 * Veritabanında da `restrictOnDelete` var — bu kontrol onun anlaşılır
 * yüzü. İkisi birlikte: biri unutulsa diğeri tutuyor.
 */
class OptionInUseException extends CatalogConflictException
{
    public function __construct(public readonly string $ad, public readonly int $urunSayisi)
    {
        parent::__construct("'{$ad}' ekseni {$urunSayisi} üründe kullanılıyor, silinemez.");
    }

    public function cozum(): string
    {
        return 'Önce bu ekseni kullanan ürünlerden kaldırın.';
    }

    /** @return array<string, mixed> */
    public function ayrintilar(): array
    {
        return ['product_count' => $this->urunSayisi];
    }
}
