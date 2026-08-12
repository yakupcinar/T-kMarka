<?php

namespace App\Http\Storefront;

use App\Domain\Analytics\EventRecorder;
use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\ProductQuery;
use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VİTRİN kataloğu — müşterinin gördüğü uçlar.
 *
 * ⚠️ `magaza-acik` middleware'i arkasında: mağaza kapalıysa 503 + Retry-After
 * (1A.4). Panel bu kapının dışında kalıyor, yoksa marka mağazasını kapatınca
 * kendini de dışarıda bırakırdı.
 *
 * ⚠️ Kimlik doğrulama YOK — katalog herkese açık. Sepet ve sipariş uçları
 * 1C/1D'de kendi kapılarıyla gelecek.
 *
 * ⚠️ Sorgu DOĞRUDAN yazılmıyor; hepsi [ProductQuery] üzerinden. Maliyet ve
 * taslak sızıntısının ikisi de sessiz olurdu (1B-K10).
 */
class CatalogController extends Controller
{
    public function __construct(
        private readonly ProductQuery $sorgu,
        private readonly CategoryService $kategoriler,
        private readonly EventRecorder $olaylar,
    ) {}

    /** Ürün listesi — kategoriye göre daraltılabilir. */
    public function index(Request $istek): JsonResponse
    {
        $sorgu = $this->sorgu->forStorefront();

        $kategoriSlug = $istek->query('category');

        if (is_string($kategoriSlug) && $kategoriSlug !== '') {
            $kategori = Category::where('slug', $kategoriSlug)->first();

            if ($kategori === null) {
                return response()->json(['message' => 'Kategori bulunamadı.'], 404);
            }

            $sorgu = $this->sorgu->kategoriyeGore($sorgu, $kategori);
        }

        $sayfa = $sorgu->orderByDesc('id')->paginate(24);

        return response()->json([
            'products' => collect($sayfa->items())->map(fn (Product $u) => $this->listeGoster($u)),
            'meta' => [
                'page' => $sayfa->currentPage(),
                'per_page' => $sayfa->perPage(),
                'total' => $sayfa->total(),
                'last_page' => $sayfa->lastPage(),
            ],
        ]);
    }

    /**
     * Ürün detayı.
     *
     * ⚠️ Aynı `forStorefront` sorgusundan geçiyor: taslak, arşiv ve
     * satılamayan ürün burada da 404. "Listede yoksa hiç yok" (1B-K8).
     */
    public function show(Request $istek, string $slug): JsonResponse
    {
        $urun = $this->sorgu->vitrindeBul($slug);

        if ($urun === null) {
            return response()->json(['message' => 'Ürün bulunamadı.'], 404);
        }

        /*
        | ★ 1F-K1'in TEK İSTİSNASI — olay controller'da doğuyor.
        |
        | Kural "iş kuralı domain'e girer" diyor; ama burada iş kuralı
        | YOK, saf bir görüntüleme var. Domain'e taşımak "ürüne bakıldı"
        | diye bir iş kuralı uydurmak olurdu.
        |
        | ⚠️ Bulunamayan üründe olay YAZILMIYOR: 404 alan bir istek
        | görüntüleme sayılmaz, yoksa bozuk bağlantılar raporu şişirirdi.
        */
        $this->olaylar->kaydet(EventType::ProductViewed, [
            'product_id' => $urun->id,
            'slug' => $urun->slug,
        ], $istek->user() instanceof Customer ? $istek->user() : null);

        return response()->json(['product' => $this->detayGoster($urun)]);
    }

    /** Menü için kategori ağacı — düz liste, `path` sırasında. */
    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => $this->kategoriler->listele()->map(fn (Category $k) => [
                'name' => $k->name,
                'slug' => $k->slug,
                'level' => $k->level,
                'parent_slug' => $k->parent?->slug,
            ]),
        ]);
    }

    /**
     * Liste görünümü — kart için gereken en az bilgi.
     *
     * @return array<string, mixed>
     */
    private function listeGoster(Product $urun): array
    {
        return [
            'slug' => $urun->slug,
            'title' => $urun->title,
            'brand' => $urun->brand,

            // "1.299 TL'den başlayan fiyatlarla" — ürünün fiyatı yok,
            // satılabilir varyantların en düşüğünden TÜRETİLİYOR (1B-K2).
            'from_price' => $urun->enDusukFiyat(),

            'image' => $urun->images->first()?->url(),
            'category_slug' => $urun->category?->slug,
        ];
    }

    /** @return array<string, mixed> */
    private function detayGoster(Product $urun): array
    {
        return [
            'slug' => $urun->slug,
            'title' => $urun->title,
            'description' => $urun->description,
            'brand' => $urun->brand,
            'model' => $urun->model,
            'attributes' => $urun->attributes,
            'from_price' => $urun->enDusukFiyat(),
            'category_slug' => $urun->category?->slug,

            // Ekmek kırıntısı `path`'ten çıkıyor, ek sorgu yok (1B-K6).
            'breadcrumb' => $this->kirinti($urun),

            // Seçiciler: hangi eksen, hangi değerler, hangi sırada.
            'options' => $urun->options->map(fn (Option $e) => [
                'name' => $e->name,
                'slug' => $e->slug,
                'values' => $e->values->map(fn (OptionValue $d) => [
                    'value' => $d->value,
                    'slug' => $d->slug,
                    'swatch' => $d->swatch,
                ]),
            ]),

            'variants' => $urun->variants->map(fn (ProductVariant $v) => [
                /*
                | ⚠️ `uuid` ZORUNLU: sepete ekleme ucu `variant_uuid` istiyor.
                | Yokken vitrinden sepete geçilemiyordu — testler uuid'yi
                | modelden aldığı için hiçbir test bunu yakalamadı; iki
                | kiracıda gerçek HTTP doğrulaması yakaladı (1D.6).
                |
                | `id` DEĞİL: sıralı sayı marka kataloğunun büyüklüğünü
                | dışarıya sızdırır.
                */
                'uuid' => $v->uuid,
                'sku' => $v->sku,
                'options' => $v->options,
                'price' => $v->price,
                'compare_at_price' => $v->compare_at_price,

                // ⚠️ `cost_price` YOK — sorgu onu hiç SEÇMİYOR bile
                // (ProductQuery::VITRIN_VARYANT_KOLONLARI).
                'in_stock' => $v->satinAlinabilirMi(),
            ]),

            'images' => $urun->images->map(fn (ProductImage $g) => [
                'url' => $g->url(),
                'alt' => $g->alt ?? $urun->title,
            ]),
        ];
    }

    /** @return list<array{name: string, slug: string}> */
    private function kirinti(Product $urun): array
    {
        if ($urun->category === null) {
            return [];
        }

        $atalar = Category::whereIn('id', $urun->category->ataIdleri())->orderBy('path')->get();

        $kirinti = $atalar->push($urun->category)
            ->map(fn (Category $k) => ['name' => $k->name, 'slug' => $k->slug])
            ->values()
            ->all();

        // `array_values` — Collection::all() dizinin liste olduğunu statik
        // analize kanıtlamıyor.
        return array_values($kirinti);
    }
}
