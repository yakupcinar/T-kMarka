<?php

namespace App\Domain\Catalog;

use App\Enums\CollectionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Koleksiyonun ürünleri — SORGU ANINDA. (2D-K2)
 *
 * ★ Kurallı koleksiyonun üyeliği hiçbir yere YAZILMIYOR. Yazılsaydı fiyat
 * değişince bayatlar ve kimse fark etmezdi: "250₺ altı" koleksiyonunda
 * 400₺'lik ürün dururdu.
 *
 * ⚠️ Her iki tip de `ProductQuery::forStorefront()` üzerinden geçiyor
 * (1B-K10). Kendi sorgusunu yazsaydı koleksiyon, vitrinin göstermediği
 * taslak ürünleri gösterirdi — ve manuel koleksiyonda bu ÇOK KOLAY olurdu:
 * marka ürünü listeye ekler, sonra taslağa alır, ürün koleksiyonda kalırdı.
 */
class CollectionQuery
{
    public function __construct(private readonly ProductQuery $sorgu) {}

    /**
     * Koleksiyonun vitrinde görünen ürünleri.
     *
     * @return Builder<Product>
     */
    public function urunler(ProductCollection $koleksiyon): Builder
    {
        $sorgu = $this->sorgu->forStorefront();

        if ($koleksiyon->type === CollectionType::Manual) {
            /*
            | ⚠️ Sıralama PIVOT'tan geliyor: manuel koleksiyonda sıra
            | markanın kararı. `orderByDesc('id')` yazılsaydı "öne
            | çıkanlar" koleksiyonunda marka sırayı hiç etkileyemezdi.
            */
            return $sorgu
                ->join('collection_product', 'collection_product.product_id', '=', 'products.id')
                ->where('collection_product.collection_id', $koleksiyon->id)
                ->select('products.*')
                ->orderBy('collection_product.position')
                ->orderBy('collection_product.id');
        }

        return $this->kuraliUygula($sorgu, $koleksiyon->rules)->orderByDesc('products.id');
    }

    /**
     * Kuralı sorguya çevirir.
     *
     * @param  Builder<Product>  $sorgu
     * @param  array<string, mixed>|null  $kural
     * @return Builder<Product>
     *
     * @throws CollectionRuleException
     */
    public function kuraliUygula(Builder $sorgu, ?array $kural): Builder
    {
        /*
        | ⚠️ Kayıtlı kural BURADA DA doğrulanıyor. "Yazarken doğruladık"
        | yetmez: kural veritabanına elle, seed'le ya da eski bir sürümle
        | girmiş olabilir. Doğrulanmadan çalıştırılsaydı bilinmeyen bir
        | alan sessizce atlanır ve koleksiyon fazla ürün gösterirdi.
        */
        $temiz = CollectionRules::dogrula($kural);

        $eslesme = $temiz['match'];

        return $sorgu->where(function (Builder $grup) use ($temiz, $eslesme): void {
            foreach ($temiz['conditions'] as $kosul) {
                /*
                | ⚠️ `all` → `where`, `any` → `orWhere`. Hepsi `where`
                | olsaydı "any" koleksiyonu sessizce "all" gibi davranır ve
                | çoğu zaman BOŞ dönerdi — hata yok, sadece boş sayfa.
                */
                $yontem = $eslesme === 'all' ? 'where' : 'orWhere';

                $grup->{$yontem}(function (Builder $alt) use ($kosul): void {
                    $this->kosuluUygula($alt, $kosul['field'], $kosul['op'], $kosul['value']);
                });
            }
        });
    }

    /**
     * @param  Builder<Product>  $sorgu
     */
    private function kosuluUygula(Builder $sorgu, string $alan, string $islec, string $deger): void
    {
        match ($alan) {
            'brand' => $islec === 'eq'
                ? $sorgu->where('brand', $deger)
                : $sorgu->where('brand', 'ilike', '%'.$this->kacir($deger).'%'),

            'title' => $sorgu->where('title', 'ilike', '%'.$this->kacir($deger).'%'),

            /*
            | ⚠️ ALT AĞAÇ DÂHİL — kategoriyle aynı davranış (1B-K6).
            | Yalnızca birebir kategori bakılsaydı "Giyim" koleksiyonu
            | "Giyim > Tişört"teki ürünleri kaçırırdı.
            */
            'category' => $sorgu->whereIn(
                'category_id',
                Category::query()->altAgac(Category::where('slug', $deger)->firstOrFail())->select('id'),
            ),

            /*
            | ⚠️ Fiyat VARYANTTA. `whereHas` içindeki `satinAlinabilir()`
            | zorunlu: pasif ya da tükenmiş varyantın fiyatı koleksiyona
            | ürün sokmamalı — vitrin o ürünü zaten göstermiyor.
            */
            'price' => $sorgu->whereHas('variants', function (Builder $varyant) use ($islec, $deger): void {
                /** @var Builder<ProductVariant> $varyant */
                $varyant->satinAlinabilir()->where('price', $islec === 'lte' ? '<=' : '>=', $deger);
            }),

            // CollectionRules kapalı liste tuttuğu için buraya düşülmez.
            default => throw new CollectionRuleException(sprintf('Bilinmeyen kural alanı: %s', $alan)),
        };
    }

    /**
     * `LIKE` özel karakterlerini kaçırır.
     *
     * ⚠️ Kaçırılmasaydı `%` yazan bir kural TÜM kataloğu eşleştirirdi —
     * yine sessizce, hata vermeden.
     */
    private function kacir(string $deger): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $deger);
    }
}
