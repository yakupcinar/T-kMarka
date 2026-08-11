<?php

namespace App\Domain\Catalog;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ürün listeleme — TEK KAPI. (1B-K10)
 *
 * ⚠️ `Product::query()` doğrudan kullanılmayacak. Kategori, arama, öneri,
 * sepet ve panel — hepsi buradan geçer, yalnızca modu değişir.
 *
 * İki sızıntı riski var ve ikisi de SESSİZ:
 *   `cost_price` vitrine çıkarsa marka rakibine kârını gösterir
 *   taslak ürün vitrine çıkarsa yayınlanmamış kampanya sızar
 *
 * Her uçta ayrı sorgu yazılsaydı 1B'de doğru yazılır, 1C'de sepet ürünü
 * çekerken unutulurdu.
 */
class ProductQuery
{
    /**
     * ⚠️ Vitrinin göreceği varyant kolonları — `cost_price` YOK.
     *
     * Sunum katmanında gizlemek yetmezdi: biri modeli doğrudan JSON'a
     * çevirdiğinde maliyet sızardı. Kolon hiç SEÇİLMEZSE nesnede de
     * bulunmuyor.
     *
     * @var list<string>
     */
    public const VITRIN_VARYANT_KOLONLARI = [
        'id', 'uuid', 'product_id', 'sku', 'barcode', 'options',
        'price', 'compare_at_price', 'stock', 'is_active',
    ];

    /**
     * MÜŞTERİNİN göreceği ürünler.
     *
     * @return Builder<Product>
     */
    public function forStorefront(): Builder
    {
        return Product::query()
            ->where('status', ProductStatus::Active)

            /*
            | ⚠️ Satılabilir varyantı olmayan ürün LİSTEDE YOK (1B-K8).
            | Doğrudan bağlantıyla da erişilemiyor — `bul()` aynı sorguyu
            | kullanıyor, yani 404. "Listede yoksa hiç yok."
            */
            ->whereHas('variants', function (Builder $sorgu): void {
                /** @var Builder<ProductVariant> $sorgu */
                $sorgu->satinAlinabilir();
            })

            /*
            | ⚠️ Varyantlar ve görseller DAİMA birlikte yükleniyor.
            | Liste, fiyatı türetmek için varyantlara bakmak zorunda; tek
            | tek çekilseydi 50 ürünlük sayfada 100 ekstra sorgu olurdu.
            */
            ->with([
                'variants' => function ($sorgu): void {
                    /** @var Builder<ProductVariant> $sorgu */
                    $sorgu->select(self::VITRIN_VARYANT_KOLONLARI)->satinAlinabilir();
                },
                'images',
                'options.values',
                'category',
            ]);
    }

    /**
     * PANELİN göreceği ürünler — taslak ve arşiv dâhil, maliyet dâhil.
     *
     * @return Builder<Product>
     */
    public function forPanel(): Builder
    {
        return Product::query()->with(['variants', 'images', 'options', 'category']);
    }

    /**
     * Kategoriye göre daraltır — ALT AĞAÇ DÂHİL.
     *
     * "Giyim" seçilince altındaki "Tişört"teki ürünler de geliyor; müşteri
     * üst kategoriye tıklayınca boş sayfa görmemeli. `path` ön ek taraması
     * bunu tek sorguda yapıyor (1B-K6).
     *
     * @param  Builder<Product>  $sorgu
     * @return Builder<Product>
     */
    public function kategoriyeGore(Builder $sorgu, Category $kategori): Builder
    {
        return $sorgu->whereIn(
            'category_id',
            Category::query()->altAgac($kategori)->select('id'),
        );
    }

    /**
     * Vitrinde tek ürün — bulunamazsa null.
     *
     * ⚠️ Aynı `forStorefront` sorgusundan geçiyor. Ayrı bir sorgu
     * yazılsaydı liste ile detay farklı davranır, tükenmiş ürün listede
     * görünmezken bağlantıyla açılabilirdi.
     */
    public function vitrindeBul(string $slug): ?Product
    {
        return $this->forStorefront()->where('slug', $slug)->first();
    }
}
