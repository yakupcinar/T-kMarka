<?php

namespace App\Domain\Catalog;

/**
 * Aynı seçenek birleşimiyle ikinci bir varyant açılmak isteniyor.
 *
 * ★ Veritabanında `(product_id, options)` benzersiz — kısıt Faz 1'den beri
 * var ve DOĞRU: aynı "Kırmızı / M" iki kez olsaydı müşteri hangisini
 * seçtiğini bilemez, stok ikiye bölünürdü.
 *
 * ⚠️ Ama kısıt tek başına YETMİYORDU: yakalanmayınca `QueryException`
 * çıkıyor ve panelde ham **500** görünüyordu. 4.5L'de ölçüldü — eksen
 * tanımlanamadığı için her varyantın `options` alanı `[]` oluyordu ve
 * markanın açtığı **İKİNCİ varyant her zaman** bu hataya düşüyordu.
 * Yani en sık karşılaşılan durum, en anlaşılmaz hatayı veriyordu.
 */
class DuplicateVariantException extends CatalogRuleException
{
    /** @param  array<string, string>  $secenekler */
    public function __construct(public readonly array $secenekler)
    {
        parent::__construct($secenekler === []
            ? 'Bu ürünün seçeneksiz bir varyantı zaten var. İkinci varyant için önce ürüne eksen tanımlayın (Renk, Beden…).'
            : 'Bu seçenek birleşiminde zaten bir varyant var: '.implode(' / ', $secenekler));
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['options' => [$this->getMessage()]];
    }
}
