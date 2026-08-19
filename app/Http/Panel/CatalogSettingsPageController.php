<?php

namespace App\Http\Panel;

use App\Domain\Catalog\CategoryCycleException;
use App\Domain\Catalog\CategoryHasChildrenException;
use App\Domain\Catalog\CategoryHasProductsException;
use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\EmptySlugException;
use App\Domain\Catalog\OptionInUseException;
use App\Domain\Catalog\OptionService;
use App\Domain\Catalog\OptionValueInUseException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategoriler ve varyant eksenleri. (4.5E)
 *
 * ★ İkisi de katalogun ALTYAPISI: kategori olmadan ürün gruplanamıyor,
 * eksen olmadan çok varyantlı ürün kurulamıyor. Uçları 1B'de vardı,
 * ekranı yoktu.
 *
 * ⚠️ Tek ekranda ikisi birden: ayrı sayfalar olsaydı marka "beden"
 * eksenini eklemek için nereye gideceğini aramak zorunda kalırdı — ikisi
 * de ürün eklemeden ÖNCE yapılan hazırlık işi.
 */
class CatalogSettingsPageController extends Controller
{
    public function __construct(
        private readonly CategoryService $kategoriler,
        private readonly OptionService $eksenler,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Katalog', [
            /*
            | ⚠️ Kategoriler AĞAÇ olarak değil DÜZ liste + derinlik olarak
            | gönderiliyor: `ltree` yolu zaten sıralı geliyor ve arayüzde
            | girinti derinlikten çiziliyor. Ağaç kurmak istemciye iş
            | yüklerdi ve sıralama iki yerde tutulurdu.
            */
            'kategoriler' => $this->kategoriler->listele()->map(fn (Category $k) => [
                'uuid' => $k->uuid,
                'name' => $k->name,
                'slug' => $k->slug,
                /*
                | ⚠️ AYRAÇ `/`, `.` DEĞİL. `ltree` yolu `/1/2/` biçiminde;
                | nokta sayılsaydı derinlik HER ZAMAN 0 çıkar ve girinti
                | hiç oluşmazdı — ağaç düz liste gibi görünürdü.
                |
                | Kök `/1/` → 2 bölü → derinlik 0.
                */
                'derinlik' => max(0, substr_count((string) $k->path, '/') - 2),
                /*
                | ⚠️ `Category::products()` ilişkisi YOK — ürün sayısı
                | doğrudan sorgudan. İlişki eklemek cazipti ama kategori
                | modelinin ürünleri bilmesi gerekmiyor; bu sayı yalnızca
                | bu ekranın ihtiyacı.
                */
                'urun_sayisi' => Product::where('category_id', $k->id)->count(),
            ])->values()->all(),

            'eksenler' => $this->eksenler->listele()->map(fn (Option $e) => [
                'uuid' => $e->uuid,
                'name' => $e->name,
                'degerler' => $e->values->map(fn (OptionValue $d) => [
                    'uuid' => $d->uuid,
                    'value' => $d->value,
                    'swatch' => $d->swatch,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function kategoriEkle(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_uuid' => ['nullable', 'uuid'],
        ]);

        $ust = isset($veri['parent_uuid'])
            ? Category::where('uuid', $veri['parent_uuid'])->first()
            : null;

        try {
            $this->kategoriler->olustur((string) $veri['name'], $ust);
        } catch (EmptySlugException $hata) {
            /*
            | ⚠️ Yalnızca `EmptySlugException` yakalanıyor — PHPStan diğer
            | türleri "ölü catch" diye işaretledi, çünkü `olustur()` onları
            | atmıyor. Geniş yakalamak hangi hatanın gerçekten yakalandığını
            | belirsizleştirirdi.
            |
            | ★ Bu istisna sanılandan sık: "!!!" ya da yalnızca emoji
            | içeren bir ad slug üretemiyor (1B). 500 DEĞİL — marka bir
            | şeyi yanlış yazdı, sebebi ekranda olmalı.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Kategori eklendi.');
    }

    public function kategoriSil(string $kategori): RedirectResponse
    {
        $kayit = Category::where('uuid', $kategori)->firstOrFail();

        try {
            $this->kategoriler->sil($kayit);
        } catch (CategoryHasChildrenException|CategoryHasProductsException $hata) {
            /*
            | ⚠️ Alt kategorisi ya da ürünü olan kategori silinemiyor (1B):
            | silinseydi ürünler kategorisiz kalır ve vitrinde
            | gezinilemezdi.
            |
            | ⚠️ İki İSTİSNA AYRI AYRI yakalanıyor, ortak atası
            | `CatalogRuleException` ile DEĞİL: PHPStan ortak atayı "ölü
            | catch" diye işaretledi — çünkü servis o türü hiç atmıyor.
            | Geniş yakalamak, gerçekte hangi hatanın yakalandığını
            | belirsizleştirirdi.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Kategori silindi.');
    }

    public function kategoriTasi(Request $istek, string $kategori): RedirectResponse
    {
        $kayit = Category::where('uuid', $kategori)->firstOrFail();

        $veri = $istek->validate(['parent_uuid' => ['nullable', 'uuid']]);

        $yeniUst = isset($veri['parent_uuid'])
            ? Category::where('uuid', $veri['parent_uuid'])->first()
            : null;

        try {
            $this->kategoriler->tasi($kayit, $yeniUst);
        } catch (CategoryCycleException $hata) {
            /*
            | ⚠️ Kategori KENDİ ALTINA taşınamıyor (1B): taşınabilseydi
            | `ltree` yolu döngüye girer ve ağaç sorgusu sonsuza kadar
            | dönerdi. 500 değil, ekranda sebep.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Kategori taşındı.');
    }

    public function eksenEkle(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(['name' => ['required', 'string', 'max:60']]);

        try {
            $this->eksenler->olustur((string) $veri['name']);
        } catch (EmptySlugException $hata) {
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Eksen eklendi.');
    }

    public function eksenSil(string $eksen): RedirectResponse
    {
        $kayit = Option::where('uuid', $eksen)->firstOrFail();

        try {
            $this->eksenler->sil($kayit);
        } catch (OptionInUseException $hata) {
            /*
            | ⚠️ Kullanımdaki eksen silinemiyor: silinseydi o ekseni
            | kullanan varyantların seçenekleri anlamsızlaşırdı.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Eksen silindi.');
    }

    public function degerEkle(Request $istek, string $eksen): RedirectResponse
    {
        $kayit = Option::where('uuid', $eksen)->firstOrFail();

        $veri = $istek->validate([
            'value' => ['required', 'string', 'max:60'],

            // ⚠️ Renk kutusu yalnızca `#rrggbb`: serbest metin CSS'e giriyor.
            'swatch' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        try {
            $this->eksenler->degerEkle($kayit, (string) $veri['value'], $veri['swatch'] ?? null);
        } catch (EmptySlugException $hata) {
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Değer eklendi.');
    }

    public function degerSil(string $eksen, string $deger): RedirectResponse
    {
        $eksenKaydi = Option::where('uuid', $eksen)->firstOrFail();

        /*
        | ⚠️ Değer EKSENE DARALTILMIŞ sorgudan çözülüyor (1A.5 deseni):
        | başka eksenin değeri sonuç kümesine hiç girmiyor → 404.
        */
        $kayit = $eksenKaydi->values()->where('uuid', $deger)->firstOrFail();

        try {
            $this->eksenler->degerSil($kayit);
        } catch (OptionValueInUseException $hata) {
            /*
            | ⚠️ Bu kuralın veritabanı karşılığı YOK (1B): değer varyantın
            | jsonb alanının içinde, yabancı anahtar kurulamıyor. Kural
            | yalnızca serviste yaşıyor — ekran onu görünür kılıyor.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Değer silindi.');
    }
}
