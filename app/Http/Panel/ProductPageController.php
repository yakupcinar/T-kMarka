<?php

namespace App\Http\Panel;

use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\ProductRequest;
use App\Http\Panel\Requests\VariantRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Katalog yönetimi — markanın ürün eklediği ekran. (4D)
 *
 * ★ 4-K3: API controller'ı çağrılmıyor, aynı `app/Domain/` servisleri
 * kullanılıyor. Doğrulama da kopyalanmıyor — `ProductRequest` ve
 * `VariantRequest` panel API'siyle ORTAK.
 *
 * ⚠️ Kuralları kopyalamak en kolay yoldu ve bir gün ikisinden biri
 * güncellenmezdi: aynı ürünü API'den eklerken geçen bir başlık, panelden
 * eklerken reddedilirdi (ya da tersi).
 */
class ProductPageController extends Controller
{
    /** Listede tek sayfada gösterilecek ürün sayısı. */
    public const SAYFA = 20;

    public function __construct(
        private readonly ProductService $urunler,
        private readonly VariantService $varyantlar,
        private readonly ProductQuery $sorgu,
    ) {}

    public function index(Request $istek): Response
    {
        $kelime = trim((string) $istek->query('q', ''));

        /*
        | ⚠️ `forPanel()` — vitrin sorgusu DEĞİL. Panelde taslak ve arşiv
        | ürünler de GÖRÜNMELİ; marka kendi taslağını göremezse onu
        | düzenleyemez (1B-K10 ayrımının panel tarafı).
        */
        $sorgu = $this->sorgu->forPanel();

        if ($kelime !== '') {
            /*
            | ⚠️ Panelde ARAMA MOTORU (2C) kullanılmıyor, düz `ILIKE`.
            | Arama motoru vitrin sorgusundan geçiyor ve taslakları
            | elerdi — marka yeni eklediği taslağı arayamazdı.
            */
            $sorgu->where('title', 'ILIKE', '%'.$kelime.'%');
        }

        $urunler = $sorgu->orderByDesc('id')->paginate(self::SAYFA)->withQueryString();

        return Inertia::render('Urunler/Liste', [
            'urunler' => $urunler->through(fn (Product $urun) => $this->satir($urun)),
            'arama' => $kelime === '' ? null : $kelime,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Urunler/Form', [
            'urun' => null,
            'kategoriler' => $this->kategoriler(),
            'durumlar' => $this->durumlar(),
        ]);
    }

    public function store(ProductRequest $istek): RedirectResponse
    {
        $urun = $this->urunler->olustur(
            $istek->safe()->except('category_uuid'),
            $this->kategoriyiBul($istek->validated('category_uuid')),
        );

        /*
        | ⚠️ Yeni ürün TASLAK doğuyor (ProductService) ve düzenleme
        | sayfasına gidiliyor: varyantı olmayan ürün satılamaz, yani
        | listeye dönmek markayı yarım bir kayıtla baş başa bırakırdı.
        */
        return redirect()
            ->route('panel.urun.duzenle', $urun->uuid)
            ->with('mesaj', 'Ürün oluşturuldu. Şimdi varyant ekleyin.');
    }

    public function edit(Product $urun): Response
    {
        $urun->load(['variants', 'images', 'category', 'options']);

        return Inertia::render('Urunler/Form', [
            'urun' => $this->detay($urun),
            'kategoriler' => $this->kategoriler(),
            'durumlar' => $this->durumlar(),
        ]);
    }

    public function update(ProductRequest $istek, Product $urun): RedirectResponse
    {
        $this->urunler->guncelle(
            $urun,
            $istek->safe()->except('category_uuid'),
            $this->kategoriyiBul($istek->validated('category_uuid')),
        );

        return back()->with('mesaj', 'Ürün güncellendi.');
    }

    public function durum(Request $istek, Product $urun): RedirectResponse
    {
        $veri = $istek->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(ProductStatus::cases(), 'value'))],
        ]);

        /*
        | ⚠️ Durum değişimi ProductService'ten geçiyor: "varyantsız ürün
        | yayınlanamaz" gibi kurallar orada. Doğrudan `update()` yazılsaydı
        | marka satılamayan bir ürünü vitrine çıkarabilirdi.
        */
        $this->urunler->durumDegistir($urun, ProductStatus::from((string) $veri['status']));

        return back()->with('mesaj', 'Ürün durumu güncellendi.');
    }

    public function destroy(Product $urun): RedirectResponse
    {
        $this->urunler->sil($urun);

        return redirect()->route('panel.urunler')->with('mesaj', 'Ürün silindi.');
    }

    public function varyantEkle(VariantRequest $istek, Product $urun): RedirectResponse
    {
        /** @var array<string, string> $secenekler */
        $secenekler = $istek->validated('options');

        $this->varyantlar->ekle($urun, $istek->safe()->except('options'), $secenekler);

        return back()->with('mesaj', 'Varyant eklendi.');
    }

    public function varyantGuncelle(VariantRequest $istek, Product $urun, ProductVariant $varyant): RedirectResponse
    {
        /*
        | ⚠️ Varyant ÜRÜNE DARALTILMIŞ sorgudan doğrulanıyor (1A.5 deseni):
        | başka ürünün varyantının kimliği gönderilirse 404 dönüyor.
        | Yalnızca `ProductVariant` bağlansaydı bir marka personeli,
        | yetkisi olan bir ürün üzerinden başka ürünü değiştirebilirdi.
        */
        abort_unless($varyant->product_id === $urun->id, 404);

        /** @var array<string, string> $secenekler */
        $secenekler = $istek->validated('options');

        $this->varyantlar->guncelle($varyant, $istek->safe()->except('options'), $secenekler);

        return back()->with('mesaj', 'Varyant güncellendi.');
    }

    public function varyantSil(Product $urun, ProductVariant $varyant): RedirectResponse
    {
        abort_unless($varyant->product_id === $urun->id, 404);

        $this->varyantlar->sil($varyant);

        return back()->with('mesaj', 'Varyant silindi.');
    }

    /** @return array<string, mixed> */
    private function satir(Product $urun): array
    {
        return [
            'uuid' => $urun->uuid,
            'title' => $urun->title,
            'status' => $urun->status->value,
            'variant_count' => $urun->variants->count(),
            'stock' => $urun->variants->sum('stock'),
            'min_price' => $urun->variants->min('price'),
            'image' => $urun->images->first()?->url(),
        ];
    }

    /** @return array<string, mixed> */
    private function detay(Product $urun): array
    {
        return [
            'uuid' => $urun->uuid,
            'title' => $urun->title,
            'description' => $urun->description,
            'brand' => $urun->brand,
            'model' => $urun->model,
            'tax_rate' => $urun->tax_rate,
            'status' => $urun->status->value,
            'slug' => $urun->slug,
            'category_uuid' => $urun->category?->uuid,
            'variants' => $urun->variants->map(fn (ProductVariant $v) => [
                'uuid' => $v->uuid,
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'price' => $v->price,
                'stock' => $v->stock,
                'is_active' => (bool) $v->is_active,
                'options' => $v->options,

                /*
                | ⚠️ BAĞLI STOK gösteriliyor: ödemesi süren siparişlerin
                | rezerve ettiği adet. Yalnızca `stock` gösterilseydi marka
                | "stok var" sanıp satamadığı ürünü anlamazdı (1D).
                */
                'committed' => $v->committed,
            ])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function kategoriler(): array
    {
        /** @var list<array<string, mixed>> $liste */
        $liste = Category::query()
            ->orderBy('path')
            ->get()
            ->map(fn (Category $k) => ['uuid' => $k->uuid, 'name' => $k->name])
            ->values()
            ->all();

        return $liste;
    }

    /** @return list<array<string, string>> */
    private function durumlar(): array
    {
        return array_map(
            fn (ProductStatus $d) => ['deger' => $d->value, 'ad' => $d->etiket()],
            ProductStatus::cases(),
        );
    }

    private function kategoriyiBul(?string $uuid): ?Category
    {
        return $uuid === null ? null : Category::where('uuid', $uuid)->first();
    }
}
