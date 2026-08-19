<?php

use App\Domain\Catalog\CollectionService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Enums\CollectionType;
use App\Enums\ProductStatus;
use App\Models\Product;

/*
| VİTRİNDE KOLEKSİYON (4.5H) — gerçek kullanımda bulunan eksik.
|
| ★ Marka koleksiyon kuruyordu ama müşteri onu HİÇBİR YERDEN göremiyordu:
| uçları 2D'de vardı (`/api/collections`), SAYFASI YOKTU.
|
| ⚠️ 4.5F'nin kapsam testi bunu yakalayamazdı — yalnızca PANEL uçlarına
| bakıyordu. Test 4.5H'de vitrini de kapsayacak şekilde genişletildi.
*/

function vitrinKoleksiyonMagazasi(): void
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

function satilikUrun(string $baslik, string $sku, float $fiyat = 100): Product
{
    $urun = app(ProductService::class)->olustur(['title' => $baslik, 'brand' => 'Demo']);
    app(VariantService::class)->ekle($urun, ['sku' => $sku, 'price' => $fiyat, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $urun->refresh();
}

it('★★ KOLEKSIYON SAYFASI acilip urunleri gosteriyor', function () {
    vitrinKoleksiyonMagazasi();

    $urun = satilikUrun('Deri Cuzdan', 'CZ-1');

    $koleksiyon = app(CollectionService::class)->olustur(['title' => 'Seçmeler'], CollectionType::Manual);
    app(CollectionService::class)->urunEkle($koleksiyon, $urun);

    $this->get('http://marka-a.test/koleksiyon/'.$koleksiyon->slug)
        ->assertOk()
        ->assertSee('Seçmeler')
        ->assertSee('Deri Cuzdan');
});

it('★★ KURALLI koleksiyon uyeleri SORGU ANINDA hesaplaniyor', function () {
    vitrinKoleksiyonMagazasi();

    satilikUrun('Ucuz Urun', 'U-1', 50);
    $pahali = satilikUrun('Pahali Urun', 'P-1', 500);

    $koleksiyon = app(CollectionService::class)->olustur(
        ['title' => '100 TL Ustu'],
        CollectionType::Rule,
        ['match' => 'all', 'conditions' => [['field' => 'price', 'op' => 'gte', 'value' => '100']]],
    );

    $sayfa = $this->get('http://marka-a.test/koleksiyon/'.$koleksiyon->slug);

    $sayfa->assertOk()->assertSee('Pahali Urun')->assertDontSee('Ucuz Urun');

    /*
    | ★ 2D'nin çekirdeği: fiyat değişince liste KENDİLİĞİNDEN güncelleniyor.
    | Üyelik tabloda saklansaydı bu ürün koleksiyonda kalırdı.
    */
    $pahali->variants->firstOrFail()->update(['price' => 10]);

    $this->get('http://marka-a.test/koleksiyon/'.$koleksiyon->slug)
        ->assertOk()
        ->assertDontSee('Pahali Urun');
});

it('★★ KAPALI koleksiyon vitrinde 404', function () {
    vitrinKoleksiyonMagazasi();

    $koleksiyon = app(CollectionService::class)->olustur(
        ['title' => 'Gizli', 'is_active' => false],
        CollectionType::Manual,
    );

    /*
    | ⚠️ Kapalı koleksiyon listede de görünmüyor: görünseydi müşteri
    | tıklar ve 404 alırdı.
    */
    $this->get('http://marka-a.test/koleksiyon/'.$koleksiyon->slug)->assertNotFound();

    $this->get('http://marka-a.test/koleksiyonlar')->assertOk()->assertDontSee('Gizli');
});

it('★★ TASLAK urun koleksiyonda da GORUNMUYOR', function () {
    vitrinKoleksiyonMagazasi();

    $taslak = app(ProductService::class)->olustur(['title' => 'Taslak Urun']);

    $koleksiyon = app(CollectionService::class)->olustur(['title' => 'Seçmeler'], CollectionType::Manual);
    app(CollectionService::class)->urunEkle($koleksiyon, $taslak);

    /*
    | ⚠️ Sorgu `forStorefront()` üzerinden gidiyor (1B-K10): koleksiyona
    | elle eklenmiş olması taslağı vitrine çıkarmaya YETMEZ.
    */
    $this->get('http://marka-a.test/koleksiyon/'.$koleksiyon->slug)
        ->assertOk()
        ->assertDontSee('Taslak Urun');
});

it('★ KOLEKSIYON YOKSA ust barda baglanti da YOK', function () {
    vitrinKoleksiyonMagazasi();

    // ⚠️ Boş sayfaya götüren bir menü maddesi müşteriyi yanıltırdı.
    $this->get('http://marka-a.test/')->assertOk()->assertDontSee('>Koleksiyonlar<', escape: false);

    app(CollectionService::class)->olustur(['title' => 'Seçmeler'], CollectionType::Manual);

    $this->get('http://marka-a.test/')->assertOk()->assertSee('>Koleksiyonlar<', escape: false);
});

it('★ BOS koleksiyon HATA gibi gorunmuyor', function () {
    vitrinKoleksiyonMagazasi();

    $koleksiyon = app(CollectionService::class)->olustur(
        ['title' => 'Kampanya'],
        CollectionType::Rule,
        ['match' => 'all', 'conditions' => [['field' => 'price', 'op' => 'gte', 'value' => '99999']]],
    );

    /*
    | ⚠️ KURALLI koleksiyonda bu NORMAL bir durum: kurala uyan ürün
    | kalmamıştır. Hata gibi göstermek marka ve müşteriyi yanıltırdı.
    */
    $this->get('http://marka-a.test/koleksiyon/'.$koleksiyon->slug)
        ->assertOk()
        ->assertSee('şu anda ürün yok');
});
