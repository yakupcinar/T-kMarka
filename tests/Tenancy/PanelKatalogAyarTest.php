<?php

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\CollectionService;
use App\Domain\Catalog\OptionService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Identity\RoleService;
use App\Enums\CollectionType;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Option;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/*
| PANEL: KATALOG ALTYAPISI VE KOLEKSİYONLAR (4.5E)
|
| ★ Dört boşluk birden: kategori · varyant ekseni · koleksiyon · ürün
| görseli. Hepsinin ucu 1B/2D'de vardı, hiçbirinin ekranı yoktu.
*/

beforeEach(function () {
    $this->withoutVite();
});

it('★ katalog ekrani kategorileri ve eksenleri gosteriyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    app(CategoryService::class)->olustur('Giyim');
    app(OptionService::class)->olustur('Beden');

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/katalog')->getContent(),
    );

    expect($sayfa['component'])->toBe('Katalog')
        ->and($sayfa['props']['kategoriler'])->not->toBeEmpty()
        ->and($sayfa['props']['eksenler'])->not->toBeEmpty();
});

it('★ ALT KATEGORI eklenebiliyor ve derinligi dogru', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $ust = app(CategoryService::class)->olustur('Giyim');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/katalog/kategoriler', [
            'name' => 'Tişört', 'parent_uuid' => $ust->uuid,
        ])->assertRedirect();

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/katalog')->getContent(),
    );

    $alt = null;

    foreach ($sayfa['props']['kategoriler'] as $k) {
        if ($k['name'] === 'Tişört') {
            $alt = $k;
        }
    }

    /*
    | ⚠️ Girinti DERİNLİKTEN çiziliyor; `ltree` yolu zaten sıralı geliyor.
    | Ağacı istemcide kurmak sıralamayı iki yerde tutmak olurdu.
    */
    expect($alt)->not->toBeNull()
        ->and($alt['derinlik'])->toBe(1);
});

it('★★ URUNU OLAN kategori silinemiyor ve SEBEBI yaziliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $kategori = app(CategoryService::class)->olustur('Giyim');
    app(ProductService::class)->olustur(['title' => 'Tisort'], $kategori);

    /*
    | ⚠️ `nullOnDelete` seçilseydi marka kategoriyi silince ürünler
    | sessizce kategorisiz kalır, menüden düşer ve "neden kimse bu
    | ürünleri görmüyor" sorusu doğardı (1B).
    */
    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/katalog/kategoriler/{$kategori->uuid}")
        ->assertRedirect()
        ->assertSessionHas('hata');

    expect(Category::find($kategori->id))->not->toBeNull();
});

it('★★ KULLANIMDAKI eksen silinemiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $eksen = app(OptionService::class)->olustur('Beden');
    app(OptionService::class)->degerEkle($eksen, 'M');

    $urun = app(ProductService::class)->olustur(['title' => 'Tisort']);
    app(ProductService::class)->eksenleriAyarla($urun, [$eksen]);

    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/katalog/eksenler/{$eksen->uuid}")
        ->assertRedirect()
        ->assertSessionHas('hata');

    expect(Option::find($eksen->id))->not->toBeNull();
});

it('★★ RENK KUTUSU serbest metin kabul etmiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $eksen = app(OptionService::class)->olustur('Renk');

    /*
    | ⚠️ Değer doğrudan CSS'e giriyor: serbest metin kabul edilseydi
    | 4-K5'te kapatılan CSS enjeksiyonu buradan geri gelirdi.
    */
    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/katalog/eksenler/{$eksen->uuid}/degerler", [
            'value' => 'Kirmizi',
            'swatch' => 'red; } body { background: url(https://kotu.example/x) ',
        ])
        ->assertSessionHasErrors('swatch');
});

it('★★ BASKA EKSENIN degeri bu eksen uzerinden silinemiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $beden = app(OptionService::class)->olustur('Beden');
    $renk = app(OptionService::class)->olustur('Renk');

    $renginDegeri = app(OptionService::class)->degerEkle($renk, 'Kirmizi');

    /*
    | ⚠️ 1A.5 deseni: değer EKSENE DARALTILMIŞ sorgudan çözülüyor.
    */
    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/katalog/eksenler/{$beden->uuid}/degerler/{$renginDegeri->uuid}")
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| KOLEKSİYONLAR — 2D'nin ekranı
|--------------------------------------------------------------------------
*/

it('★★ KURALLI koleksiyonun uye sayisi SORGUDAN geliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $urun = app(ProductService::class)->olustur(['title' => 'Pahali Urun']);
    app(VariantService::class)->ekle($urun, ['sku' => 'P-1', 'price' => 500, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    app(CollectionService::class)->olustur(
        ['title' => 'Pahalilar'],
        CollectionType::Rule,
        /*
        | ⚠️ Anahtar `op`, `operator` DEĞİL (2D). Yanlış anahtar sessizce
        | atlanmıyor — `CollectionRuleException` fırlıyor, çünkü atlansaydı
        | koleksiyon FAZLA ürün gösterir ve kimse fark etmezdi.
        */
        ['match' => 'all', 'conditions' => [['field' => 'price', 'op' => 'gte', 'value' => '100']]],
    );

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/koleksiyonlar')->getContent(),
    );

    /*
    | ★ 2D: kurallı koleksiyonun üyeleri SORGU ANINDA hesaplanıyor.
    | Üye sayısı tabloya bakılarak verilseydi kurallı koleksiyon ekranda
    | hep "0 ürün" görünürdü — marka kuralın çalıştığını hiç göremezdi.
    */
    expect($sayfa['props']['koleksiyonlar'][0]['urun_sayisi'])->toBe(1);
});

it('★★ KURALLI koleksiyona ELLE urun eklenemiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $urun = app(ProductService::class)->olustur(['title' => 'Urun']);

    $koleksiyon = app(CollectionService::class)->olustur(
        ['title' => 'Kurallı'],
        CollectionType::Rule,
        ['match' => 'all', 'conditions' => [['field' => 'price', 'op' => 'gte', 'value' => '1']]],
    );

    /*
    | ⚠️ Üyelik sorguyla belirleniyor; elle eklenen ürün bir sonraki
    | sorguda KAYBOLURDU — yani sessizce çalışmayan bir düğme olurdu.
    */
    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/koleksiyonlar/{$koleksiyon->uuid}/urunler", [
            'product_uuid' => $urun->uuid,
        ])->assertStatus(422);
});

it('★ MANUEL koleksiyona urun eklenip cikarilabiliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $urun = app(ProductService::class)->olustur(['title' => 'Urun']);

    $koleksiyon = app(CollectionService::class)->olustur(['title' => 'Seçmeler'], CollectionType::Manual);

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/koleksiyonlar/{$koleksiyon->uuid}/urunler", ['product_uuid' => $urun->uuid])
        ->assertRedirect();

    expect($koleksiyon->refresh()->products()->count())->toBe(1);

    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/koleksiyonlar/{$koleksiyon->uuid}/urunler/{$urun->uuid}")
        ->assertRedirect();

    expect($koleksiyon->refresh()->products()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| ÜRÜN GÖRSELİ
|--------------------------------------------------------------------------
*/

it('★ panelden URUN GORSELI yuklenebiliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $urun = app(ProductService::class)->olustur(['title' => 'Tisort']);

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/gorseller", [
            'image' => UploadedFile::fake()->image('urun.png', 200, 200),
        ])->assertRedirect();

    expect($urun->refresh()->images)->toHaveCount(1);
});

it('★★ BASKA URUNUN gorseli bu urun uzerinden silinemiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $a = app(ProductService::class)->olustur(['title' => 'A Urunu']);
    $b = app(ProductService::class)->olustur(['title' => 'B Urunu']);

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$b->uuid}/gorseller", [
            'image' => UploadedFile::fake()->image('b.png', 100, 100),
        ])->assertRedirect();

    $bninGorseli = $b->refresh()->images->firstOrFail();

    /*
    | ⚠️ 1A.5 deseni: görsel ÜRÜNE DARALTILMIŞ sorgudan çözülüyor.
    */
    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/urunler/{$a->uuid}/gorseller/{$bninGorseli->uuid}")
        ->assertNotFound();

    expect($b->refresh()->images)->toHaveCount(1);
});

it('★★ IZINSIZ personel katalog ayarlarina GIREMIYOR', function () {
    markaKur('marka-a.test');

    $rol = app(RoleService::class)->olustur('Depocu', [Permission::OrderView->value]);
    $personel = User::factory()->create(['email' => 'depo@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    foreach (['/yonetim/katalog', '/yonetim/koleksiyonlar'] as $yol) {
        $this->actingAs($personel->refresh(), 'staff-web')
            ->get('http://marka-a.test'.$yol)
            ->assertForbidden();
    }
});
