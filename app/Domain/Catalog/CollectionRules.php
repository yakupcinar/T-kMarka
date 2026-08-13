<?php

namespace App\Domain\Catalog;

/**
 * Koleksiyon kuralı — DOĞRULAMA. (2D)
 *
 * ★ NEDEN AYRI SINIF: kural dışarıdan gelen serbest JSON. Doğrulanmadan
 * saklansaydı hatanın bedeli SESSİZ olurdu — koleksiyon açılır, hata
 * vermez, sadece YANLIŞ ürünleri gösterir.
 *
 * Kabul edilen biçim:
 *
 * ```json
 * {"match": "all",
 *  "conditions": [{"field": "price", "op": "lte", "value": "250.00"},
 *                 {"field": "brand", "op": "eq",  "value": "Nike"}]}
 * ```
 */
class CollectionRules
{
    /**
     * ⚠️ ALAN LİSTESİ KAPALI. Açık bırakılsaydı `{"field":"cost_price"}`
     * yazan bir kural maliyet üzerinden koleksiyon kurabilir, hatta hata
     * mesajıyla maliyeti sızdırabilirdi.
     *
     * @var array<string, list<string>>
     */
    public const ALANLAR = [
        'brand' => ['eq', 'contains'],
        'title' => ['contains'],
        'category' => ['in_tree'],

        /*
        | ⚠️ `price` ürünün değil VARYANTIN alanı. "En az bir satılabilir
        | varyant bu koşulu sağlıyor" diye okunuyor — "250₺ altı"
        | koleksiyonuna 250₺'lik bedeni olan ürün girer. Ürünün en düşük
        | fiyatı üzerinden okunsaydı `gte` anlamsızlaşırdı.
        */
        'price' => ['lte', 'gte'],
    ];

    /** @var list<string> */
    public const ESLESMELER = ['all', 'any'];

    /**
     * Ham kuralı doğrular ve normalleştirilmiş hâlini döndürür.
     *
     * @return array{match: string, conditions: list<array{field: string, op: string, value: string}>}
     *
     * @throws CollectionRuleException
     */
    public static function dogrula(mixed $ham): array
    {
        if (! is_array($ham)) {
            throw new CollectionRuleException('Kural bir nesne olmalı.');
        }

        $eslesme = $ham['match'] ?? 'all';

        if (! is_string($eslesme) || ! in_array($eslesme, self::ESLESMELER, true)) {
            throw new CollectionRuleException('Geçersiz eşleşme türü: yalnızca "all" veya "any".');
        }

        $kosullar = $ham['conditions'] ?? null;

        if (! is_array($kosullar) || $kosullar === []) {
            /*
            | ⚠️ BOŞ KURAL YASAK. İzin verilseydi koleksiyon TÜM KATALOĞU
            | gösterirdi — hata vermeden, sessizce. Marka "kampanya
            | koleksiyonu" sanır, vitrinde her ürün çıkardı.
            */
            throw new CollectionRuleException('Kurallı koleksiyon en az bir koşul içermeli.');
        }

        $sonuc = [];

        foreach ($kosullar as $kosul) {
            $sonuc[] = self::kosulDogrula($kosul);
        }

        return ['match' => $eslesme, 'conditions' => $sonuc];
    }

    /**
     * @return array{field: string, op: string, value: string}
     *
     * @throws CollectionRuleException
     */
    private static function kosulDogrula(mixed $kosul): array
    {
        if (! is_array($kosul)) {
            throw new CollectionRuleException('Her koşul bir nesne olmalı.');
        }

        $alan = $kosul['field'] ?? null;
        $islec = $kosul['op'] ?? null;
        $deger = $kosul['value'] ?? null;

        if (! is_string($alan) || ! array_key_exists($alan, self::ALANLAR)) {
            /*
            | ⚠️ Bilinmeyen alan SESSİZCE ATLANMIYOR, istisna fırlıyor.
            | Atlansaydı üç koşullu bir kuralın ikisi uygulanır, koleksiyon
            | FAZLA ürün gösterir ve kimse fark etmezdi.
            */
            throw new CollectionRuleException(sprintf('Bilinmeyen kural alanı: %s', is_string($alan) ? $alan : '?'));
        }

        if (! is_string($islec) || ! in_array($islec, self::ALANLAR[$alan], true)) {
            throw new CollectionRuleException(sprintf('"%s" alanı "%s" işlecini desteklemiyor.', $alan, is_string($islec) ? $islec : '?'));
        }

        if (! is_string($deger) && ! is_numeric($deger)) {
            throw new CollectionRuleException('Koşul değeri metin ya da sayı olmalı.');
        }

        $deger = (string) $deger;

        if (trim($deger) === '') {
            throw new CollectionRuleException('Koşul değeri boş olamaz.');
        }

        if ($alan === 'price' && ! is_numeric($deger)) {
            throw new CollectionRuleException('Fiyat koşulu sayı olmalı.');
        }

        return ['field' => $alan, 'op' => $islec, 'value' => $deger];
    }
}
