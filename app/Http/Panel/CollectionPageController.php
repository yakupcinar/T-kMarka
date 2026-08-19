<?php

namespace App\Http\Panel;

use App\Domain\Catalog\CollectionQuery;
use App\Domain\Catalog\CollectionRuleException;
use App\Domain\Catalog\CollectionService;
use App\Domain\Quota\QuotaExceededException;
use App\Enums\CollectionType;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\CollectionRequest;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Koleksiyonlar. (4.5E)
 *
 * ★ 2D'nin ekranı: uçları vardı, marka koleksiyon kuramıyordu.
 *
 * ⚠️ İKİ TÜR var ve farkları ekranda görünür olmalı:
 *   manuel   → ürünleri marka tek tek ekliyor
 *   kurallı  → üyeler SORGU ANINDA hesaplanıyor; fiyat değişince liste
 *              kendiliğinden güncelleniyor (2D)
 */
class CollectionPageController extends Controller
{
    public function __construct(
        private readonly CollectionService $koleksiyonlar,
        private readonly CollectionQuery $sorgu,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Koleksiyonlar', [
            'koleksiyonlar' => $this->koleksiyonlar->listele()->map(fn (ProductCollection $k) => [
                'uuid' => $k->uuid,
                'title' => $k->title,
                'slug' => $k->slug,
                'type' => $k->type->value,
                'is_active' => (bool) $k->is_active,

                /*
                | ⚠️ ÜYE SAYISI iki türde iki farklı yerden geliyor:
                | manuelde tablodan, kurallıda SORGUDAN. Tek kaynağa
                | bakılsaydı kurallı koleksiyon hep "0 ürün" görünürdü.
                */
                'urun_sayisi' => $this->sorgu->urunler($k)->count(),
            ])->values()->all(),

            /*
            | ⚠️ Tür adları GÖRÜNÜM için burada: enum'da `etiket()` yok ve
            | eklemek iş mantığına sunum kararı sokmak olurdu.
            */
            'turler' => [
                ['deger' => CollectionType::Manual->value, 'ad' => 'Elle seçilen'],
                ['deger' => CollectionType::Rule->value, 'ad' => 'Kurallı (otomatik)'],
            ],
        ]);
    }

    public function goster(ProductCollection $koleksiyon): Response
    {
        return Inertia::render('KoleksiyonAyrinti', [
            'koleksiyon' => [
                'uuid' => $koleksiyon->uuid,
                'title' => $koleksiyon->title,
                'description' => $koleksiyon->description,
                'type' => $koleksiyon->type->value,
                'is_active' => (bool) $koleksiyon->is_active,
                'rules' => $koleksiyon->rules,
            ],

            'uyeler' => $this->sorgu->urunler($koleksiyon)->get()->map(fn (Product $u) => [
                'uuid' => $u->uuid,
                'title' => $u->title,
                'status' => $u->status->value,
            ])->values()->all(),

            /*
            | ⚠️ Manuel koleksiyona eklenebilecek ürünler ayrı gönderiliyor.
            | Kurallıda gönderilmiyor: orada üyelik SORGUYLA belirleniyor
            | ve elle ekleme yanlış beklenti yaratırdı.
            */
            'eklenebilir' => $koleksiyon->type === CollectionType::Manual
                ? Product::query()->orderBy('title')->limit(200)
                    ->get()->map(fn (Product $u) => ['uuid' => $u->uuid, 'title' => $u->title])
                    ->values()->all()
                : [],
        ]);
    }

    public function ekle(CollectionRequest $istek): RedirectResponse
    {
        /** @var array<string, mixed>|null $kural */
        $kural = $istek->validated('rules');

        try {
            $this->koleksiyonlar->olustur(
                $istek->safe()->except(['type', 'rules']),
                CollectionType::from((string) $istek->validated('type')),
                $kural,
            );
        } catch (CollectionRuleException|QuotaExceededException $hata) {
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Koleksiyon oluşturuldu.');
    }

    public function sil(ProductCollection $koleksiyon): RedirectResponse
    {
        $koleksiyon->delete();

        return redirect()->route('panel.koleksiyonlar')->with('mesaj', 'Koleksiyon silindi.');
    }

    public function urunEkle(Request $istek, ProductCollection $koleksiyon): RedirectResponse
    {
        $veri = $istek->validate(['product_uuid' => ['required', 'uuid']]);

        /*
        | ⚠️ "KURALLI KOLEKSİYONA ELLE ÜRÜN EKLENEMEZ" KONTROLÜ BURADA YOK
        | ve bu bilinçli: kural `CollectionService::urunEkle()` içinde
        | (`manuelOlmali`) ve oradan 422 dönüyor.
        |
        | ★ Burada da yazmıştım; KIRMA DENEMESİ ölü olduğunu gösterdi —
        | kaldırdığımda hiçbir test düşmedi. 2F ve 3E'deki kararın aynısı:
        | gerçek koruma başka yerdeyse ikinci kopya yalnızca "iki yerden
        | biri güncellenmeden kalır" riski üretir.
        */
        $urun = Product::where('uuid', $veri['product_uuid'])->firstOrFail();

        $this->koleksiyonlar->urunEkle($koleksiyon, $urun);

        return back()->with('mesaj', 'Ürün koleksiyona eklendi.');
    }

    public function urunCikar(ProductCollection $koleksiyon, string $urun): RedirectResponse
    {
        // ⚠️ Kural yine serviste (`urunCikar` → `manuelOlmali`).
        $kayit = Product::where('uuid', $urun)->firstOrFail();

        $this->koleksiyonlar->urunCikar($koleksiyon, $kayit);

        return back()->with('mesaj', 'Ürün koleksiyondan çıkarıldı.');
    }
}
