<?php

namespace App\Http\Panel;

use App\Domain\Catalog\CollectionQuery;
use App\Domain\Catalog\CollectionRuleException;
use App\Domain\Catalog\CollectionRules;
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
            | ⚠️ Kural seçenekleri LİSTE ekranına da gidiyor — yalnızca
            | ayrıntıya değil. Sebep: kurallı koleksiyon KURAL OLMADAN
            | oluşturulamıyor (2D: boş kural tüm kataloğu gösterirdi), yani
            | kural düzenleyici OLUŞTURMA FORMUNDA olmak zorunda.
            |
            | ⚠️ İlk hâlde "önce oluştur, sonra kuralını yaz" akışı
            | yazılmıştı ve HİÇ ÇALIŞMIYORDU: her deneme "Kural bir nesne
            | olmalı" ile düşüyordu. Gerçek kullanımda bulundu.
            */
            'kuralAlanlari' => $this->kuralAlanlari(),
            'eslesmeler' => CollectionRules::ESLESMELER,

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
            /*
            | ⚠️ Alan ve işleç listesi SUNUCUDAN geliyor, arayüzde
            | kopyalanmıyor: 2D'de listeye yeni alan eklenirse ekran
            | kendiliğinden öğreniyor. Kopyalansaydı iki liste ayrışır ve
            | marka olmayan bir alanı seçebilirdi.
            */
            'kuralAlanlari' => $this->kuralAlanlari(),

            'eslesmeler' => CollectionRules::ESLESMELER,

            'eklenebilir' => $koleksiyon->type === CollectionType::Manual
                ? Product::query()->orderBy('title')->limit(200)
                    ->get()->map(fn (Product $u) => ['uuid' => $u->uuid, 'title' => $u->title])
                    ->values()->all()
                : [],
        ]);
    }

    /**
     * Kural alanları ve destekledikleri işleçler.
     *
     * ⚠️ SUNUCUDAN geliyor, arayüzde kopyalanmıyor: 2D'de listeye yeni
     * alan eklenirse ekran kendiliğinden öğreniyor.
     *
     * @return list<array{alan: string, islecler: list<string>}>
     */
    private function kuralAlanlari(): array
    {
        /** @var list<array{alan: string, islecler: list<string>}> $liste */
        $liste = collect(CollectionRules::ALANLAR)
            ->map(fn (array $islecler, string $alan) => ['alan' => $alan, 'islecler' => $islecler])
            ->values()
            ->all();

        return $liste;
    }

    public function ekle(CollectionRequest $istek): RedirectResponse
    {
        /** @var array<string, mixed>|null $kural */
        $kural = $istek->validated('rules');

        /*
        | ⚠️ KURALLI KOLEKSİYON KURALSIZ OLUŞTURULAMAZ ve bu kontrol
        | erken burada: servise kuralsız gidilse `CollectionRules::dogrula`
        | "Kural bir nesne olmalı" diyor — teknik olarak doğru ama markaya
        | ne yapacağını söylemeyen bir mesaj.
        |
        | Boş kural 2D'de bilerek yasaklandı: izin verilseydi koleksiyon
        | TÜM KATALOĞU gösterirdi — sessizce.
        */
        if ($istek->validated('type') === CollectionType::Rule->value
            && ($kural === null || ($kural['conditions'] ?? []) === [])) {
            return back()->with('hata', 'Kurallı koleksiyon en az bir koşul içermeli.');
        }

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

    public function kuralKaydet(Request $istek, ProductCollection $koleksiyon): RedirectResponse
    {
        $veri = $istek->validate([
            'match' => ['required', 'string'],
            'conditions' => ['present', 'array'],
            'conditions.*.field' => ['required', 'string'],

            // ⚠️ Anahtar `op` — `operator` DEĞİL (2D). Yanlış anahtar
            // sessizce atlanmıyor, istisna fırlıyor.
            'conditions.*.op' => ['required', 'string'],
            'conditions.*.value' => ['required'],
        ]);

        try {
            $this->koleksiyonlar->guncelle($koleksiyon, [], null, [
                'match' => $veri['match'],
                'conditions' => $veri['conditions'],
            ]);
        } catch (CollectionRuleException $hata) {
            /*
            | ⚠️ Bilinmeyen alan ya da desteklenmeyen işleç SESSİZCE
            | ATLANMIYOR (2D): atlansaydı üç koşullu bir kuralın ikisi
            | uygulanır, koleksiyon FAZLA ürün gösterir ve kimse fark
            | etmezdi.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Kural kaydedildi.');
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
