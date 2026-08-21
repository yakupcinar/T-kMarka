<?php

namespace App\Http\Panel;

use App\Domain\Catalog\CatalogRuleException;
use App\Domain\Catalog\CollectionRuleException;
use App\Domain\Catalog\CollectionService;
use App\Domain\Catalog\OptionsLockedException;
use App\Domain\Catalog\ProductImageService;
use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\TooManyImagesException;
use App\Domain\Catalog\TooManyOptionsException;
use App\Domain\Catalog\UnsupportedImageTypeException;
use App\Domain\Catalog\VariantService;
use App\Domain\Search\WordPrefixPattern;
use App\Enums\CollectionType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\ProductImageRequest;
use App\Http\Panel\Requests\ProductRequest;
use App\Http\Panel\Requests\VariantRequest;
use App\Models\Category;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductImage;
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
        private readonly ProductImageService $gorseller,
        private readonly ProductService $urunler,
        private readonly VariantService $varyantlar,
        private readonly ProductQuery $sorgu,
        private readonly CollectionService $koleksiyonlar,
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
            | ⚠️ Panelde ARAMA MOTORU (2C) kullanılmıyor, düz desen
            | eşleşmesi. Arama motoru vitrin sorgusundan geçiyor ve
            | taslakları elerdi — marka yeni eklediği taslağı arayamazdı.
            |
            | ★ KELİME BAŞINDAN EŞLEŞME (4.5P). Önce `ILIKE '%kelime%'`
            | vardı ve KELİME ORTASINDAN eşleşiyordu: "iş" araması
            | "Tişört"ü getiriyordu. Gerçek kullanımda bildirildi —
            | *"nerden başladığına bakmıyor, normalde kelime başına
            | bakması lazım."*
            |
            | `\m` POSIX'te KELİME BAŞI sınırı; `~*` büyük/küçük harf
            | ayrımı yapmıyor. Böylece "cüz" → "Deri Cüzdan" eşleşiyor
            | ama "üzd" eşleşmiyor.
            |
            | ⚠️ Desen KAÇIRILIYOR: kullanıcının yazdığı metin doğrudan
            | düzenli ifadeye giriyor. Kaçırılmasaydı `.*` yazan biri tüm
            | kataloğu döndürür, hatalı bir desen ise sorguyu patlatırdı.
            */
            $sorgu->whereRaw('title ~* ?', [WordPrefixPattern::olustur($kelime)]);

            /*
            | ⚠️ BAŞLIKLA BAŞLAYANLAR ÖNCE. Sıralanmasaydı "Deri" araması
            | "Kahverengi Deri Çanta"yı "Deri Cüzdan"dan önce
            | getirebilirdi — marka aradığını listenin ortasında arardı.
            */
            $sorgu->orderByRaw('CASE WHEN title ILIKE ? THEN 0 ELSE 1 END', [$kelime.'%']);
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
            'eksenler' => $this->eksenler(),
            'maksEksen' => ProductService::MAKS_EKSEN,
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
        $urun->load(['variants', 'images', 'category', 'options.values', 'collections']);

        return Inertia::render('Urunler/Form', [
            'urun' => $this->detay($urun),
            'kategoriler' => $this->kategoriler(),
            'durumlar' => $this->durumlar(),
            'eksenler' => $this->eksenler(),

            /*
            | ⚠️ SINIR EKRANA GİDİYOR (4.5S). Marka tanımlı 5 eksenin
            | hepsini birden kaydetmeye çalıştı; istek doğrulaması
            | reddetti ama ekranda HİÇBİR ŞEY GÖRÜNMEDİ — "kaydettim ama
            | seçenekler gelmiyor" bildirimi buydu.
            |
            | ⚠️ Sayı arayüzde sabit yazılsaydı sınır değiştiğinde iki
            | taraf ayrışırdı (4.5L'deki "deneme_gun" kararının aynısı).
            */
            'maksEksen' => ProductService::MAKS_EKSEN,

            'manuelKoleksiyonlar' => $this->manuelKoleksiyonlar(),
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

        /*
        | ★ TARAYICIYA OTURUM HATASI, API'YE JSON. (4.5L)
        |
        | ⚠️ `CatalogRuleException` için genel işleyici JSON döndürüyor —
        | `api/*` için doğru. Ama burası bir PANEL SAYFASI: yakalanmazsa
        | marka Inertia'nın hata kutusunda ham JSON görüyor ya da sayfa
        | hiç yanıt vermiyor gibi duruyor. 4A · 4B · 4.5G'de kapatılan
        | hatanın aynı ailesi.
        */
        try {
            $this->varyantlar->ekle($urun, $istek->safe()->except('options'), $secenekler);
        } catch (CatalogRuleException $hata) {
            return back()->withErrors($hata->alanHatalari())->withInput();
        }

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

        try {
            $this->varyantlar->guncelle($varyant, $istek->safe()->except('options'), $secenekler);
        } catch (CatalogRuleException $hata) {
            return back()->withErrors($hata->alanHatalari())->withInput();
        }

        return back()->with('mesaj', 'Varyant güncellendi.');
    }

    public function varyantSil(Product $urun, ProductVariant $varyant): RedirectResponse
    {
        abort_unless($varyant->product_id === $urun->id, 404);

        $this->varyantlar->sil($varyant);

        return back()->with('mesaj', 'Varyant silindi.');
    }

    public function gorselYukle(ProductImageRequest $istek, Product $urun): RedirectResponse
    {
        try {
            $this->gorseller->yukle($urun, $istek->file('image'), null, $istek->validated('alt'));
        } catch (TooManyImagesException|UnsupportedImageTypeException $hata) {
            /*
            | ⚠️ Sayı sınırı ve desteklenmeyen tür 500 DEĞİL: marka bir
            | şeyi yanlış denedi, sebebi ekranda yazmalı.
            |
            | ⚠️ `UnsupportedImageTypeException` İÇERİK tabanlı kontrolden
            | geliyor (4G): adı `.png` olan bir PHP dosyası doğrulamayı
            | geçebilir, içerik kontrolü geçemez.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Görsel yüklendi.');
    }

    public function gorselSirala(Request $istek, Product $urun): RedirectResponse
    {
        $veri = $istek->validate([
            'uuids' => ['present', 'array'],
            'uuids.*' => ['uuid'],
        ]);

        /** @var list<string> $sira */
        $sira = $veri['uuids'];

        /*
        | ⚠️ TAM LİSTE gönderiliyor, tek tek "yukarı/aşağı" değil: kısmi
        | güncelleme iki isteğin arasında sıralamayı tutarsız bırakabilir.
        | Kural servisin içinde — burada yalnızca çevriliyor.
        */
        $this->gorseller->sirala($urun, $sira);

        return back()->with('mesaj', 'Görsel sırası güncellendi.');
    }

    public function gorselSil(Product $urun, string $gorsel): RedirectResponse
    {
        /*
        | ⚠️ Görsel ÜRÜNE DARALTILMIŞ sorgudan çözülüyor (1A.5 deseni):
        | başka ürünün görseli sonuç kümesine hiç girmiyor → 404.
        */
        $kayit = $urun->images()->where('uuid', $gorsel)->firstOrFail();

        $this->gorseller->sil($kayit);

        return back()->with('mesaj', 'Görsel silindi.');
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

            /*
            | VARYANT EKSENLERİ (4.5L) — uçları 1B'de vardı, ekranı yoktu.
            |
            | ⚠️ Bedeli ağırdı: eksen tanımlanamayınca her varyantın
            | `options` alanı `[]` oluyor ve `(product_id, options)`
            | benzersiz kısıtı yüzünden İKİNCİ varyant her zaman
            | patlıyordu — üstelik ham 500 ile.
            |
            | ⚠️ `values` de gidiyor: eksenin değerleri olmadan ekran
            | seçici çizemez ve marka eksen tanımlayıp değer seçemezdi.
            */
            'options' => $urun->options->map(fn (Option $e) => [
                'uuid' => $e->uuid,
                'slug' => $e->slug,
                'name' => $e->name,
                'values' => $e->values->map(fn (OptionValue $d) => [
                    'slug' => $d->slug,
                    'value' => $d->value,
                ])->values()->all(),
            ])->values()->all(),

            /*
            | ⚠️ Eksen KİLİTLİ Mİ bilgisi ekrana gidiyor: varyant varken
            | eksen değiştirilemiyor (1B — değiştirilseydi eldeki
            | varyantlar anında geçersizleşirdi). Ekran bunu bilmezse
            | marka düğmeye basar ve anlamadığı bir hata alır.
            */
            'eksen_kilitli' => $urun->variants->isNotEmpty(),

            /*
            | KOLEKSİYONLAR (4.5L) — gerçek kullanımda bulundu: marka elle
            | seçilen koleksiyon açıyor ama ürünü nereden ekleyeceğini
            | bulamıyordu. Seçici koleksiyon AYRINTISINDA vardı; marka onu
            | ürün tarafından arıyordu.
            |
            | ⚠️ Yalnızca MANUEL koleksiyonlar: kurallıda üyelik sorgu
            | anında hesaplanıyor (2D) ve elle ekleme yanlış beklenti
            | yaratırdı — "bu ürün neden burada" sorusunun iki cevabı
            | olurdu.
            */
            'koleksiyonlar' => $urun->collections
                ->where('type', CollectionType::Manual)
                ->map(fn (ProductCollection $k) => ['uuid' => $k->uuid, 'title' => $k->title])
                ->values()
                ->all(),
            /*
            | GÖRSELLER (4.5E) — uçları 1B'de vardı, ekranı yoktu:
            | ürünler görselsiz kalıyordu.
            |
            | ⚠️ Adres `tenant_asset()` ile HTTP katmanında kuruluyor;
            | Domain yalnızca yolu biliyor (M-2.7).
            */
            'images' => $urun->images->map(fn (ProductImage $g) => [
                'uuid' => $g->uuid,
                'url' => $g->url(),
                'alt' => $g->alt,
            ])->values()->all(),

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

    /**
     * Elle seçilen koleksiyonlar — ürünün eklenebileceği listeler. (4.5L)
     *
     * @return list<array<string, mixed>>
     */
    private function manuelKoleksiyonlar(): array
    {
        /** @var list<array<string, mixed>> $liste */
        $liste = ProductCollection::query()
            ->where('type', CollectionType::Manual->value)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductCollection $k) => ['uuid' => $k->uuid, 'title' => $k->title])
            ->values()
            ->all();

        return $liste;
    }

    /**
     * Ürünü elle seçilen bir koleksiyona ekler / çıkarır. (4.5L)
     *
     * ⚠️ İŞ KURALI SERVİSTE: "kurallı koleksiyona elle ürün eklenemez"
     * kontrolü `CollectionService::manuelOlmali` içinde. Burada
     * tekrarlansaydı iki yerde tutulur ve biri güncellenmeden kalırdı —
     * 4.5E'de bu tam olarak yaşandı ve ölü koruma silindi.
     */
    public function koleksiyonaEkle(Request $istek, Product $urun): RedirectResponse
    {
        $veri = $istek->validate([
            'collection_uuid' => ['required', 'uuid'],
            'ekle' => ['required', 'boolean'],
        ]);

        $koleksiyon = ProductCollection::where('uuid', $veri['collection_uuid'])->firstOrFail();

        try {
            $veri['ekle']
                ? $this->koleksiyonlar->urunEkle($koleksiyon, $urun)
                : $this->koleksiyonlar->urunCikar($koleksiyon, $urun);
        } catch (CollectionRuleException $hata) {
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', $veri['ekle'] ? 'Koleksiyona eklendi.' : 'Koleksiyondan çıkarıldı.');
    }

    /**
     * Markanın tanımlı varyant eksenleri — ürüne atanabilecekler. (4.5L)
     *
     * ⚠️ Eksenler KATALOG AYARLARINDA tanımlanıyor (4.5E); burada
     * yalnızca listeleniyor. Ürün ekranından eksen yaratılabilseydi aynı
     * eksen ("Renk") her üründe yeniden açılır ve vitrindeki filtreler
     * birbirini tutmazdı.
     *
     * @return list<array<string, mixed>>
     */
    private function eksenler(): array
    {
        /** @var list<array<string, mixed>> $liste */
        $liste = Option::query()
            ->with('values')
            ->orderBy('name')
            ->get()
            ->map(fn (Option $e) => [
                'uuid' => $e->uuid,
                'slug' => $e->slug,
                'name' => $e->name,
                'values' => $e->values->map(fn (OptionValue $d) => [
                    'slug' => $d->slug,
                    'value' => $d->value,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return $liste;
    }

    /**
     * Ürünün kullanacağı eksenleri ayarlar. (4.5L)
     *
     * ⚠️ Sıra ÖNEMLİ ve dizideki sıra korunuyor: "Renk / Beden" ile
     * "Beden / Renk" vitrinde farklı görünür.
     */
    public function eksenleriAyarla(Request $istek, Product $urun): RedirectResponse
    {
        /*
        | ⚠️ MESAJ ELLE YAZILIYOR. Varsayılanı `validation.max.array`
        | çeviri anahtarını OLDUĞU GİBİ basıyordu (Türkçe dil dosyası
        | yok) — marka ekranda "validation.max.array" görüyordu.
        | Gerçek koşuda ölçüldü.
        */
        $veri = $istek->validate([
            'option_uuids' => ['present', 'array', 'max:'.ProductService::MAKS_EKSEN],
            'option_uuids.*' => ['uuid', 'exists:options,uuid'],
        ], [
            'option_uuids.max' => sprintf(
                'Bir üründe en fazla %d eksen olabilir. Daha fazlası varyant sayısını katlanarak büyütür (1B-K4).',
                ProductService::MAKS_EKSEN,
            ),
        ]);

        /** @var list<Option> $eksenler */
        $eksenler = array_map(
            fn (string $uuid) => Option::where('uuid', $uuid)->firstOrFail(),
            $veri['option_uuids'],
        );

        try {
            $this->urunler->eksenleriAyarla($urun, $eksenler);
        } catch (OptionsLockedException|TooManyOptionsException $hata) {
            /*
            | ⚠️ Varyant varken eksen değiştirilemiyor (1B). Ekran düğmeyi
            | zaten gizliyor ama bu bir KOLAYLIK; gerçek koruma burada.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Eksenler ayarlandı. Şimdi varyantları ekleyebilirsiniz.');
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
