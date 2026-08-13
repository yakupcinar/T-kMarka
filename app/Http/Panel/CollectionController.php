<?php

namespace App\Http\Panel;

use App\Domain\Catalog\CollectionQuery;
use App\Domain\Catalog\CollectionService;
use App\Enums\CollectionType;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\CollectionRequest;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Koleksiyonlar — panel ucu. `izin:product.write` arkasında. (2D)
 *
 * ⚠️ İş kuralları [App\Domain\Catalog\CollectionService] ve
 * [App\Domain\Catalog\CollectionRules]'ta. Tip/kural tutarlılığı ve kapalı
 * alan listesi burada YAZILI DEĞİL — tohumlayıcıdan gelen kural da aynı
 * kapıdan geçmek zorunda.
 */
class CollectionController extends Controller
{
    public function __construct(
        private readonly CollectionService $koleksiyonlar,
        private readonly CollectionQuery $sorgu,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'collections' => $this->koleksiyonlar->listele()->map(fn (ProductCollection $k) => $this->goster($k)),
        ]);
    }

    public function store(CollectionRequest $istek): JsonResponse
    {
        $koleksiyon = $this->koleksiyonlar->olustur(
            $this->alanlar($istek),
            CollectionType::from((string) $istek->validated('type')),
            $this->kural($istek),
        );

        return response()->json(['collection' => $this->goster($koleksiyon)], 201);
    }

    public function update(CollectionRequest $istek, ProductCollection $collection): JsonResponse
    {
        $koleksiyon = $this->koleksiyonlar->guncelle(
            $collection,
            $this->alanlar($istek),
            CollectionType::from((string) $istek->validated('type')),
            $this->kural($istek),
        );

        return response()->json(['collection' => $this->goster($koleksiyon)]);
    }

    public function destroy(ProductCollection $collection): JsonResponse
    {
        $this->koleksiyonlar->sil($collection);

        return response()->json(status: 204);
    }

    /** Manuel koleksiyona ürün ekler. */
    public function attach(Request $istek, ProductCollection $collection): JsonResponse
    {
        $veri = $istek->validate([
            'product_uuid' => ['required', 'uuid', 'exists:products,uuid'],
            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        $urun = Product::where('uuid', $veri['product_uuid'])->firstOrFail();

        $this->koleksiyonlar->urunEkle($collection, $urun, (int) ($veri['position'] ?? 0));

        return response()->json(['collection' => $this->goster($collection->refresh())]);
    }

    public function detach(ProductCollection $collection, string $urun): JsonResponse
    {
        $hedef = Product::where('uuid', $urun)->firstOrFail();

        $this->koleksiyonlar->urunCikar($collection, $hedef);

        return response()->json(status: 204);
    }

    /** Manuel koleksiyonun sırasını baştan yazar. */
    public function reorder(Request $istek, ProductCollection $collection): JsonResponse
    {
        $veri = $istek->validate([
            'product_uuids' => ['required', 'array', 'min:1'],
            'product_uuids.*' => ['uuid', 'exists:products,uuid'],
        ]);

        /** @var list<string> $uuidler */
        $uuidler = array_values($veri['product_uuids']);

        /*
        | ⚠️ Sıra GÖNDERİLEN DİZİNİN sırası; `whereIn` sonucunun sırası
        | değil. `pluck` doğrudan kullanılsaydı PostgreSQL'in döndürdüğü
        | sıra geçerli olur ve panel sürüklediği sırayı geri alamazdı.
        */
        $idler = Product::whereIn('uuid', $uuidler)->pluck('id', 'uuid');

        $sirali = [];

        foreach ($uuidler as $uuid) {
            $id = $idler[$uuid] ?? null;

            if (is_int($id)) {
                $sirali[] = $id;
            }
        }

        $this->koleksiyonlar->sirala($collection, $sirali);

        return response()->json(['collection' => $this->goster($collection->refresh())]);
    }

    /**
     * Koleksiyonun ŞU ANDA içerdiği ürünler — kurallı olanda hesaplanarak.
     *
     * ⚠️ Panelin bu ucu olmasaydı marka kuralını yazar ama sonucunu
     * ancak vitrinde görebilirdi. Kuralın ne getirdiğini kaydetmeden
     * görmek gerekiyor.
     */
    public function products(ProductCollection $collection): JsonResponse
    {
        $urunler = $this->sorgu->urunler($collection)->get();

        return response()->json([
            'products' => $urunler->map(fn (Product $u) => [
                'uuid' => $u->uuid,
                'slug' => $u->slug,
                'title' => $u->title,
                'brand' => $u->brand,
                'from_price' => $u->enDusukFiyat(),
            ]),
            'total' => $urunler->count(),
        ]);
    }

    /** @return array<string, mixed> */
    private function alanlar(CollectionRequest $istek): array
    {
        return array_filter(
            $istek->safe()->only(['title', 'description', 'position', 'is_active']),
            fn ($deger) => $deger !== null,
        );
    }

    /** @return array<string, mixed>|null */
    private function kural(CollectionRequest $istek): ?array
    {
        $kural = $istek->validated('rules');

        return is_array($kural) ? $kural : null;
    }

    /** @return array<string, mixed> */
    private function goster(ProductCollection $koleksiyon): array
    {
        return [
            'uuid' => $koleksiyon->uuid,
            'title' => $koleksiyon->title,
            'slug' => $koleksiyon->slug,
            'description' => $koleksiyon->description,
            'type' => $koleksiyon->type->value,
            'rules' => $koleksiyon->rules,
            'position' => $koleksiyon->position,
            'is_active' => $koleksiyon->is_active,
        ];
    }
}
