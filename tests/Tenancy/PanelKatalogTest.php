<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Identity\RoleService;
use App\Domain\Settings\StorePublication;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/*
| PANEL KATALOG YÖNETİMİ (4D) — markanın ürün eklediği ekran.
|
| ★ Bu blok 4C-K4'ün ikinci yarısını ÖLÇÜLEBİLİR yapıyor: menüde madde
| gizlemek bir kolaylıktı, gerçek koruma `izin:` middleware'inde.
*/

beforeEach(function () {
    $this->withoutVite();
});

/** `product.write` izni OLMAYAN personel. */
function izinsizPersonel(string $izin = Permission::OrderView->value): User
{
    $rol = app(RoleService::class)->olustur('Sinirli', [$izin]);

    $personel = User::factory()->create(['email' => 'sinirli@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    return $personel->refresh();
}

it('★ urun listesi aciliyor ve BOS liste dogru prop ile geliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $cevap = $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/urunler');

    $cevap->assertOk();

    /*
    | ⚠️ EKRANDAKİ METİN ARANMAZ — Inertia'da sunucu cevabı METİN İÇERMİYOR.
    |
    | "Henüz ürün yok" yazısı Vue şablonunda ve TARAYICIDA üretiliyor;
    | sunucu yalnızca bileşen adını ve prop'ları gönderiyor. Testi
    | `assertSee('Henüz ürün yok')` diye yazdım ve düştü — ölçmesi gereken
    | şey "hangi veri gitti", "hangi yazı çizildi" değil.
    |
    | ⚠️ Bu vitrinle KÖKTEN farklı: orada sayfa sunucuda render ediliyor
    | (4-K1) ve metin aramak doğru yöntem.
    */
    $sayfa = inertiaVerisi($cevap->getContent());

    expect($sayfa['component'])->toBe('Urunler/Liste')
        ->and($sayfa['props']['urunler']['data'])->toBe([])
        ->and($sayfa['props']['arama'])->toBeNull();
});

it('★★ UÇTAN UCA: marka PANELDEN urun ekliyor ve VITRINDE goruyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    // 1 — Ürün oluştur.
    $olustur = $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/urunler', ['title' => 'Deri Cuzdan', 'brand' => 'Demo']);

    $urun = Product::query()->latest('id')->firstOrFail();

    /*
    | ⚠️ Yeni ürün TASLAK doğuyor ve DÜZENLEME sayfasına yönlendiriliyor:
    | varyantsız ürün satılamaz, listeye dönmek markayı yarım kayıtla
    | baş başa bırakırdı.
    */
    $olustur->assertRedirect('http://marka-a.test/yonetim/urunler/'.$urun->uuid);
    expect($urun->status)->toBe(ProductStatus::Draft);

    // 2 — Taslak ürün VİTRİNDE YOK.
    $this->get('http://marka-a.test/')->assertOk()->assertDontSee('Deri Cuzdan');

    // 3 — Varyant ekle.
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/urunler/'.$urun->uuid.'/varyantlar', [
            'sku' => 'CZ-1', 'price' => 349.90, 'stock' => 5, 'options' => [],
        ])->assertRedirect();

    expect($urun->refresh()->variants)->toHaveCount(1);

    // 4 — Yayına al.
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/urunler/'.$urun->uuid.'/durum', ['status' => 'active'])
        ->assertRedirect();

    // 5 — ★ ARTIK VİTRİNDE. Zincirin tamamı: panel → vitrin.
    $this->get('http://marka-a.test/')->assertOk()->assertSee('Deri Cuzdan');
    $this->get('http://marka-a.test/urun/'.$urun->refresh()->slug)->assertOk()->assertSee('349,90');
});

/*
|--------------------------------------------------------------------------
| ★★ 4C-K4'ÜN TAMAMI: MENÜYÜ GİZLEMEK YETKİ DEĞİLDİR
|--------------------------------------------------------------------------
*/

it('★★ IZINSIZ personel urun sayfasina GIREMIYOR — 403', function () {
    markaKur('marka-a.test');
    $personel = izinsizPersonel();

    /*
    | ★ FAZIN EN ÖNEMLİ TESTİ. Menüde "Ürünler" maddesi bu kullanıcıya
    | çizilmiyor — ama o bir KOLAYLIK. Adresi elle yazarsa sunucu
    | durdurmalı.
    |
    | ⚠️ Bu test düşerse "izni arayüzde kontrol ettik, uçta gerek yok"
    | hatası sisteme girmiş demektir.
    */
    $this->actingAs($personel, 'staff-web')
        ->get('http://marka-a.test/yonetim/urunler')
        ->assertForbidden();
});

it('★★ IZINSIZ personel urun OLUSTURAMIYOR — 403', function () {
    markaKur('marka-a.test');
    $personel = izinsizPersonel();

    $this->actingAs($personel, 'staff-web')
        ->post('http://marka-a.test/yonetim/urunler', ['title' => 'Gizli'])
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});

it('★★ IZINSIZ personel MENUDE de urunleri gormuyor', function () {
    markaKur('marka-a.test');
    $personel = izinsizPersonel();

    $sayfa = inertiaVerisi(
        $this->actingAs($personel, 'staff-web')->get('http://marka-a.test/yonetim')->getContent(),
    );

    // İki taraf HİZALI olmalı: izin yoksa ne menüde madde, ne sayfaya erişim.
    expect($sayfa['props']['auth']['permissions'])->not->toContain(Permission::ProductWrite->value);
});

/*
|--------------------------------------------------------------------------
| DOĞRULAMA VE SINIRLAR
|--------------------------------------------------------------------------
*/

it('★ BASLIKSIZ urun olusturulamiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/urunler', ['title' => ''])
        ->assertSessionHasErrors('title');

    expect(Product::count())->toBe(0);
});

it('★★ BASKA URUNUN varyanti bu urun uzerinden degistirilemiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $a = app(ProductService::class)->olustur(['title' => 'A Urunu']);
    $b = app(ProductService::class)->olustur(['title' => 'B Urunu']);

    app(VariantService::class)->ekle($b, ['sku' => 'B-1', 'price' => 10, 'stock' => 1]);

    /** @var ProductVariant $bninVaryanti */
    $bninVaryanti = $b->refresh()->variants->first();

    /*
    | ⚠️ 1A.5 deseni: varyant ÜRÜNE DARALTILMIŞ doğrulamadan geçiyor.
    | Yalnızca varyant bağlansaydı, A ürününü düzenleme yetkisi olan biri
    | B ürününün varyantını değiştirebilirdi.
    */
    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/urunler/{$a->uuid}/varyantlar/{$bninVaryanti->uuid}")
        ->assertNotFound();

    expect($b->refresh()->variants)->toHaveCount(1);
});

it('★ PANEL taslak urunu GORUYOR — vitrin gormuyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    app(ProductService::class)->olustur(['title' => 'Taslak Urun']);

    /*
    | ⚠️ Panelde `forPanel()` kullanılıyor. Vitrin sorgusu kullanılsaydı
    | marka kendi taslağını GÖREMEZ, yani düzenleyemezdi.
    */
    $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/urunler')
        ->assertOk()
        ->assertSee('Taslak Urun');

    $this->get('http://marka-a.test/')->assertOk()->assertDontSee('Taslak Urun');
});

it('★ PANEL ARAMASI taslagi da buluyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    app(ProductService::class)->olustur(['title' => 'Taslak Cuzdan']);

    /*
    | ⚠️ Panelde arama motoru (2C) KULLANILMIYOR: o vitrin sorgusundan
    | geçiyor ve taslakları elerdi — marka yeni eklediği ürünü arayamazdı.
    */
    $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/urunler?q=cuzdan')
        ->assertOk()
        ->assertSee('Taslak Cuzdan');
});

it('★ iki markanin urunleri panelde karismiyor', function () {
    ['sahip' => $sahipA] = markaKur('marka-a.test');
    app(ProductService::class)->olustur(['title' => 'A Markasinin Urunu']);
    tenancy()->end();

    ['sahip' => $sahipB] = markaKur('marka-b.test');
    app(ProductService::class)->olustur(['title' => 'B Markasinin Urunu']);
    tenancy()->end();

    $this->actingAs($sahipB, 'staff-web')
        ->get('http://marka-b.test/yonetim/urunler')
        ->assertOk()
        ->assertSee('B Markasinin Urunu')
        ->assertDontSee('A Markasinin Urunu');

    expect($sahipA)->not->toBeNull();
});
