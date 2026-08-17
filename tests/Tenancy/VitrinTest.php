<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Domain\Settings\ThemeSettings;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Http\Storefront\CartToken;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;

/*
| VİTRİN İSKELETİ (4A) — sunucuda render edilen Blade sayfası.
|
| ⚠️ ÇEREZ OKUYAN TESTLERDE `getJson` KULLANILMAZ — ÖLÇÜLDÜ.
|
| İki yardımcı iki farklı şey yapıyor:
|   withCookie()             değeri ŞİFRELER (bizim çerezimiz şifresiz)
|   withUnencryptedCookie()  düz gönderir                        ✓
|
| ve `getJson` şifrelenmemiş çerezleri SESSİZCE DÜŞÜRÜYOR — istek
| çerezsiz gidiyor, hata yok. Çerez testi `getJson` ile yazılsaydı
| "çerez okunuyor" iddiası hiç ölçülmezdi. Bunun yerine `get()` +
| elle `Accept` başlığı kullanılıyor.
|
| ⚠️ Ayrıca HTML/JSON ayrımını ölçen testlerde de `getJson` olmaz:
| başlığı otomatik eklediği için ölçülecek şeyi ortadan kaldırır (2E).
*/

/** Vitrini açık, adı belli bir marka kurar. */
function vitrinliMarka(string $alanAdi = 'marka-a.test', string $ad = 'Ada Kozmetik'): void
{
    markaKur($alanAdi);
    magazayiHazirla();

    // ⚠️ `magazayiHazirla()` name'i "Test Markası" yapıyor — sonra yazılmalı.
    app(SettingsService::class)->yaz(SettingGroup::Store, 'name', $ad);

    app(StorePublication::class)->yayinla();
}

/** Satılabilir ürün — vitrinde görünmesi için Active olmak ZORUNDA. */
function vitrinUrunu(string $baslik, string $sku, ProductStatus $durum = ProductStatus::Active): Product
{
    $urun = app(ProductService::class)->olustur(['title' => $baslik, 'brand' => 'Demo']);
    app(VariantService::class)->ekle($urun, ['sku' => $sku, 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), $durum);

    return $urun->refresh();
}

/** Ürünün tek varyantı — testte `null` gelmesi hatadır, sessizce geçilmez. */
function vitrinVaryanti(string $baslik, string $sku): ProductVariant
{
    $varyant = vitrinUrunu($baslik, $sku)->variants->first();

    expect($varyant)->toBeInstanceOf(ProductVariant::class);

    /** @var ProductVariant $varyant */
    return $varyant;
}

it('★ vitrin HTML donuyor, JSON degil', function () {
    vitrinliMarka();

    $cevap = $this->get('http://marka-a.test/');

    $cevap->assertOk();
    expect($cevap->headers->get('content-type'))->toContain('text/html');
    $cevap->assertSee('<!doctype html>', escape: false);
});

it('★ magaza adi ve marka rengi sayfaya giriyor', function () {
    vitrinliMarka();
    app(SettingsService::class)->yaz(SettingGroup::Theme, 'primary_color', '#123456');

    $this->get('http://marka-a.test/')
        ->assertSee('Ada Kozmetik')
        ->assertSee('#123456', escape: false);
});

/*
|--------------------------------------------------------------------------
| ★★ 4-K5 — AYAR DA BİR GİRİŞ KAPISI
|--------------------------------------------------------------------------
|
| "Marka şablon yazamaz" kapıyı kapatıyor; ayarın kendisi PENCERE.
| Aşağıdaki üç test o pencerenin kapalı olduğunu ölçüyor.
*/

it('★★ RENGE CSS enjekte edilemiyor — varsayilana dusuyor', function () {
    vitrinliMarka();

    /*
    | Marka panelden bunu kaydedebilseydi çıkan sayfa markanın yazmadığı
    | CSS'i çalıştırırdı — arka plana dış bir adres, hatta tam sayfa
    | kaplayan görünmez bir katman.
    */
    app(SettingsService::class)->yaz(
        SettingGroup::Theme,
        'primary_color',
        'red; } body { background: url(https://baskasi.example/x) ',
    );

    $cevap = $this->get('http://marka-a.test/');

    $cevap->assertOk();
    $cevap->assertDontSee('baskasi.example');
    $cevap->assertSee(ThemeSettings::VARSAYILAN_RENK, escape: false);
});

it('★★ YAZI TIPI listede yoksa varsayilan kullaniliyor', function () {
    vitrinliMarka();
    app(SettingsService::class)->yaz(SettingGroup::Theme, 'font', 'x; } * { display:none ');

    $cevap = $this->get('http://marka-a.test/');

    $cevap->assertOk();
    $cevap->assertDontSee('display:none', escape: false);
    $cevap->assertSee(ThemeSettings::YAZI_TIPLERI[ThemeSettings::VARSAYILAN_YAZI_TIPI], escape: false);
});

it('★★ LOGO yolu marka klasorunun disina cikamiyor', function () {
    vitrinliMarka();

    foreach (['../../../etc/passwd', '/etc/passwd'] as $kotu) {
        app(SettingsService::class)->yaz(SettingGroup::Theme, 'logo_path', $kotu);

        expect(app(ThemeSettings::class)->logo())->toBeNull();
    }
});

it('★ DUZEN ayari listede yoksa varsayilan gorunum kullaniliyor', function () {
    vitrinliMarka();

    // Var olmayan bir düzen adı yazılırsa sayfa PATLAMAMALI.
    app(SettingsService::class)->yaz(SettingGroup::Theme, 'layout', 'baska/bir/yol');

    $this->get('http://marka-a.test/')->assertOk()->assertSee('Ada Kozmetik');
});

/*
|--------------------------------------------------------------------------
| MAĞAZA KAPALI — middleware'den gelen sayfa
|--------------------------------------------------------------------------
*/

it('★ kapali magaza TARAYICIYA HTML donuyor, JSON degil', function () {
    markaKur('marka-a.test');
    // Mağaza varsayılan olarak KAPALI doğuyor — yayınlamıyoruz.

    $cevap = $this->get('http://marka-a.test/');

    $cevap->assertStatus(503);
    $cevap->assertHeader('Retry-After', '3600');
    expect($cevap->headers->get('content-type'))->toContain('text/html');
    $cevap->assertSee('Mağaza şu anda kapalı');
});

it('★ kapali magaza API istemcisine hala JSON donuyor', function () {
    markaKur('marka-a.test');

    /*
    | ⚠️ Bu ayrımı `ForceJson`'ın `api` grubuna daraltılması mümkün kıldı.
    | Global kalsaydı her istek "JSON istiyorum" derdi ve yukarıdaki HTML
    | dalı hiç çalışmazdı.
    */
    $this->getJson('http://marka-a.test/api/products')
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});

/*
|--------------------------------------------------------------------------
| ★★ SEPET ÇEREZİ — 4A'nın görünür sebebi
|--------------------------------------------------------------------------
*/

it('★★ sepet sayisi CEREZDEN okunuyor — baslik gonderilemeyen yerde', function () {
    vitrinliMarka();

    $varyant = vitrinVaryanti('Deri Cuzdan', 'CZ-1');

    // Sepete API'den ekliyoruz — gerçek müşterinin yaptığı gibi.
    $ekle = $this->postJson('http://marka-a.test/api/cart/items', [
        'variant_uuid' => $varyant->uuid,
        'quantity' => 3,
    ])->assertCreated();

    $token = $ekle->json('cart_token');
    expect($token)->toBeString();

    /*
    | ★ ASIL ÖLÇÜM: sayfaya YALNIZCA ÇEREZLE giriyoruz, başlık YOK.
    | Tarayıcı düz gezinmede tam olarak bunu yapıyor.
    */
    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee('>3<', escape: false);
});

it('★★ AYNI CEREZ hem sayfada hem sepet ucunda calisiyor', function () {
    vitrinliMarka();

    $varyant = vitrinVaryanti('Deri Cuzdan', 'CZ-1');

    $token = $this->postJson('http://marka-a.test/api/cart/items', [
        'variant_uuid' => $varyant->uuid,
    ])->json('cart_token');

    /*
    | ⚠️ EN SİNSİ TUZAK BURADA ve testin ADI DEĞİL YAPISI ölçüyor.
    |
    | `EncryptCookies` YALNIZCA `web` grubunda çalışıyor; `api` grubunda
    | çerez middleware'i hiç yok. Şifreleme istisnası konmasaydı AYNI
    | çerez iki grupta İKİ FARKLI DEĞER olurdu:
    |
    |   sayfa (web) → çözmeye çalışır, düşer → sepet YOK
    |   uç   (api)  → ham değeri okur     → sepet VAR
    |
    | Hata vermezdi: müşteri sepetini bir yerde görür, diğerinde göremezdi.
    |
    | ★ Bu yüzden test TEK çerezle İKİ GRUBA birden vuruyor. Yalnızca
    |   `api` tarafına vuran bir test istisnayı hiç ölçmez — ölçüldü:
    |   istisna kaldırıldığında öyle bir test YEŞİL KALIYOR.
    */
    $sayfa = $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/');

    $sayfa->assertOk()->assertSee('>1<', escape: false);

    $uc = $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/api/cart', ['Accept' => 'application/json']);

    $uc->assertOk()->assertJsonPath('cart_token', $token);
});

it('★ BASLIK cerezi eziyor — API istemcisinin niyeti acik', function () {
    vitrinliMarka();

    $sepetA = app(CartService::class)->misafirSepetiOlustur();
    $sepetB = app(CartService::class)->misafirSepetiOlustur();

    /*
    | Tarayıcıdan atılan bir API çağrısında ikisi birden bulunabilir.
    | Başlık kazanmalı: onu istemci BİLEREK koydu.
    */
    $this->withUnencryptedCookie(CartToken::CEREZ, (string) $sepetB->session_token)
        ->get('http://marka-a.test/api/cart', [
            'Accept' => 'application/json',
            'X-Cart-Token' => (string) $sepetA->session_token,
        ])
        ->assertOk()
        ->assertJsonPath('cart_token', $sepetA->session_token);
});

it('★ sayfa ziyareti BOS SEPET ACMIYOR', function () {
    vitrinliMarka();

    $this->get('http://marka-a.test/')->assertOk();
    $this->get('http://marka-a.test/')->assertOk();

    /*
    | ⚠️ Her sayfa görüntülemesi sepet açsaydı veritabanı hiç alışveriş
    | yapmayan ziyaretçilerin sepetleriyle dolar, terk edilmiş sepet
    | raporu da (2F) anlamsızlaşırdı.
    */
    expect(app(CartService::class)->misafirSepetiBul('yok'))->toBeNull();
    expect(Cart::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| ARAMA VE ÜRÜN LİSTESİ
|--------------------------------------------------------------------------
*/

it('★ vitrin YALNIZCA satilabilir urunleri gosteriyor', function () {
    vitrinliMarka();

    vitrinUrunu('Yayindaki Urun', 'Y-1');
    vitrinUrunu('Taslak Urun', 'T-1', ProductStatus::Draft);

    $this->get('http://marka-a.test/')
        ->assertSee('Yayindaki Urun')
        ->assertDontSee('Taslak Urun');
});

it('★ arama kelimesi sayfada calisiyor', function () {
    vitrinliMarka();

    vitrinUrunu('Deri Cuzdan', 'CZ-1');
    vitrinUrunu('Pamuklu Tisort', 'TS-1');

    $this->get('http://marka-a.test/?q=cuzdan')
        ->assertOk()
        ->assertSee('Deri Cuzdan')
        ->assertDontSee('Pamuklu Tisort');
});

it('★ urunu olmayan magaza HATA gostermiyor', function () {
    vitrinliMarka();

    $this->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee('henüz ürün yok');
});
