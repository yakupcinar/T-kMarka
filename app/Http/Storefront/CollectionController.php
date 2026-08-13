<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\CollectionQuery;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Http\JsonResponse;

/**
 * VİTRİN koleksiyonları. (2D)
 *
 * ⚠️ `magaza-acik` middleware'i arkasında, katalogla aynı kapıda.
 *
 * ⚠️ Ürünler [CollectionQuery] üzerinden — yani `forStorefront()`'tan
 * geçiyor (1B-K10). Koleksiyon kendi sorgusunu yazsaydı taslak ürün
 * sızardı ve bu manuel koleksiyonda ÇOK KOLAY olurdu: marka ürünü listeye
 * ekler, sonra taslağa alır, ürün koleksiyonda kalırdı.
 */
class CollectionController extends Controller
{
    public function __construct(private readonly CollectionQuery $sorgu) {}

    /** Menü için koleksiyon listesi — pasif olanlar YOK. */
    public function index(): JsonResponse
    {
        $koleksiyonlar = ProductCollection::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json([
            'collections' => $koleksiyonlar->map(fn (ProductCollection $k) => [
                'title' => $k->title,
                'slug' => $k->slug,
                'description' => $k->description,
            ]),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        /*
        | ⚠️ Pasif koleksiyon 404 — listede olmayan bağlantıyla da
        | açılamıyor. "Listede yoksa hiç yok" (1B-K8).
        */
        $koleksiyon = ProductCollection::where('slug', $slug)->where('is_active', true)->first();

        if ($koleksiyon === null) {
            return response()->json(['message' => 'Koleksiyon bulunamadı.'], 404);
        }

        $sayfa = $this->sorgu->urunler($koleksiyon)->paginate(24);

        return response()->json([
            'collection' => [
                'title' => $koleksiyon->title,
                'slug' => $koleksiyon->slug,
                'description' => $koleksiyon->description,
            ],
            'products' => collect($sayfa->items())->map(fn (Product $u) => [
                'slug' => $u->slug,
                'title' => $u->title,
                'brand' => $u->brand,
                'from_price' => $u->enDusukFiyat(),
                'image' => $u->images->first()?->url(),
            ]),
            'meta' => [
                'page' => $sayfa->currentPage(),
                'per_page' => $sayfa->perPage(),
                'total' => $sayfa->total(),
                'last_page' => $sayfa->lastPage(),
            ],
        ]);
    }
}
