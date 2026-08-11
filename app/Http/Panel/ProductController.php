<?php

namespace App\Http\Panel;

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\ProductRequest;
use App\Http\Panel\Requests\VariantRequest;
use App\Models\Category;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ürün ve varyantlar — panel ucu. `izin:product.write` arkasında.
 *
 * ⚠️ Bu uçlar PANEL tarafı: `cost_price` (maliyet) burada GÖRÜNÜR.
 * Vitrin tarafı 1B.5'te `ProductQuery::forStorefront` üzerinden gelecek ve
 * maliyeti asla döndürmeyecek.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $urunler,
        private readonly VariantService $varyantlar,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'products' => $this->urunler->listele()->map(fn (Product $u) => $this->goster($u)),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['product' => $this->goster($product->load(['category', 'options.values', 'variants']))]);
    }

    public function store(ProductRequest $istek): JsonResponse
    {
        $urun = $this->urunler->olustur(
            $istek->safe()->except('category_uuid'),
            $this->kategoriyiBul($istek->validated('category_uuid')),
        );

        return response()->json(['product' => $this->goster($urun)], 201);
    }

    public function update(ProductRequest $istek, Product $product): JsonResponse
    {
        $urun = $this->urunler->guncelle(
            $product,
            $istek->safe()->except('category_uuid'),
            $this->kategoriyiBul($istek->validated('category_uuid')),
        );

        return response()->json(['product' => $this->goster($urun)]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->urunler->sil($product);

        return response()->json(['message' => 'Ürün silindi.']);
    }

    /** Ürünün kullanacağı eksenler — sıra dizideki sıradır. */
    public function setOptions(Request $istek, Product $product): JsonResponse
    {
        $veri = $istek->validate([
            'option_uuids' => ['present', 'array'],
            'option_uuids.*' => ['uuid', 'exists:options,uuid'],
        ]);

        /** @var list<Option> $eksenler */
        $eksenler = array_map(
            fn (string $uuid) => Option::where('uuid', $uuid)->firstOrFail(),
            $veri['option_uuids'],
        );

        $urun = $this->urunler->eksenleriAyarla($product, $eksenler);

        return response()->json(['product' => $this->goster($urun)]);
    }

    /** Durum değişikliği ayrı uçta: satışa almanın kendi şartı var. */
    public function setStatus(Request $istek, Product $product): JsonResponse
    {
        $veri = $istek->validate(['status' => ['required', Rule::enum(ProductStatus::class)]]);

        $urun = $this->urunler->durumDegistir($product, ProductStatus::from($veri['status']));

        return response()->json(['product' => $this->goster($urun)]);
    }

    public function storeVariant(VariantRequest $istek, Product $product): JsonResponse
    {
        /** @var array<string, string> $secenekler */
        $secenekler = $istek->validated('options');

        $varyant = $this->varyantlar->ekle($product, $istek->safe()->except('options'), $secenekler);

        return response()->json(['variant' => $this->varyantGoster($varyant)], 201);
    }

    /** Eksenlerin tüm kombinasyonlarını tek istekte üretir. */
    public function generateVariants(Request $istek, Product $product): JsonResponse
    {
        $veri = $istek->validate([
            'sku_prefix' => ['required', 'string', 'max:32'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $uretilen = $this->varyantlar->tumKombinasyonlariUret(
            $product,
            ['price' => $veri['price'], 'stock' => $veri['stock'] ?? 0],
            $veri['sku_prefix'],
        );

        return response()->json([
            'created' => count($uretilen),
            'variants' => array_map(fn (ProductVariant $v) => $this->varyantGoster($v), $uretilen),
        ], 201);
    }

    public function updateVariant(VariantRequest $istek, Product $product, string $variant): JsonResponse
    {
        /** @var array<string, string> $secenekler */
        $secenekler = $istek->validated('options');

        $varyant = $this->varyantlar->guncelle(
            $this->varyantiBul($product, $variant),
            $istek->safe()->except('options'),
            $secenekler,
        );

        return response()->json(['variant' => $this->varyantGoster($varyant)]);
    }

    public function destroyVariant(Product $product, string $variant): JsonResponse
    {
        $this->varyantlar->sil($this->varyantiBul($product, $variant));

        return response()->json(['message' => 'Varyant silindi.']);
    }

    /**
     * ⚠️ Varyant ÜRÜNE DARALTILMIŞ sorgudan çözülüyor — 1A.5 deseni.
     * Düz `ProductVariant::where('uuid', …)` kullanılsaydı
     * `/products/A/variants/{B-nin-varyanti}` isteği 200 dönerdi.
     */
    private function varyantiBul(Product $urun, string $uuid): ProductVariant
    {
        /** @var ProductVariant $varyant */
        $varyant = $urun->variants()->where('uuid', $uuid)->firstOrFail();

        return $varyant;
    }

    private function kategoriyiBul(mixed $uuid): ?Category
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return Category::where('uuid', $uuid)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function goster(Product $urun): array
    {
        $urun->loadMissing(['category', 'options', 'variants']);

        return [
            'uuid' => $urun->uuid,
            'title' => $urun->title,
            'slug' => $urun->slug,
            'description' => $urun->description,
            'brand' => $urun->brand,
            'model' => $urun->model,
            'attributes' => $urun->attributes,
            'tax_rate' => $urun->tax_rate,
            'status' => $urun->status->value,
            'category_uuid' => $urun->category?->uuid,
            'options' => $urun->options->map(fn (Option $e) => ['uuid' => $e->uuid, 'name' => $e->name, 'slug' => $e->slug]),
            'variants' => $urun->variants->map(fn (ProductVariant $v) => $this->varyantGoster($v)),
        ];
    }

    /** @return array<string, mixed> */
    private function varyantGoster(ProductVariant $varyant): array
    {
        return [
            'uuid' => $varyant->uuid,
            'sku' => $varyant->sku,
            'barcode' => $varyant->barcode,
            'options' => $varyant->options,
            'price' => $varyant->price,
            'compare_at_price' => $varyant->compare_at_price,

            // ⚠️ Yalnızca PANEL cevabında. Vitrin sorgusu bunu hiç seçmiyor.
            'cost_price' => $varyant->cost_price,

            'stock' => $varyant->stock,
            'is_active' => $varyant->is_active,
            'purchasable' => $varyant->satinAlinabilirMi(),
        ];
    }
}
