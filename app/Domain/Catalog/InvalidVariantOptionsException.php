<?php

namespace App\Domain\Catalog;

/**
 * Varyantın eksen değerleri ürünün tanımıyla uyuşmuyor.
 *
 * Üç ayrı hatayı birden kapsıyor, çünkü üçünün de sonucu aynı: ürün
 * sayfasında SEÇİLEMEYEN bir varyant.
 *
 *   eksik anahtar  → "Beden" tanımlı ama varyantta yok
 *   fazla anahtar  → varyantta "Boy" var ama ürün onu kullanmıyor
 *   tanımsız değer → {"renk":"turuncu"} ama Renk ekseninde turuncu yok
 *
 * ⚠️ Hepsi engellenmeseydi kayıt başarılı olur, stok o varyanta yazılır ve
 * müşteri onu hiçbir zaman seçemezdi — hata vermeden.
 */
class InvalidVariantOptionsException extends CatalogRuleException
{
    /** @param  list<string>  $sorunlar */
    public function __construct(public readonly array $sorunlar)
    {
        parent::__construct('Varyant seçenekleri ürünün eksenleriyle uyuşmuyor: '.implode(' · ', $sorunlar));
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['options' => $this->sorunlar];
    }
}
