<?php

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\OptionService;
use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use App\Platform\Models\Tenant;

/*
| VİTRİN kataloğu (1B-K8, 1B-K10).
|
| Bu bloğun iki iddiası var ve ikisi de SESSİZ sızıntı riski:
|   cost_price (maliyet) müşteriye gitmemeli — marka rakibine kârını verir
|   taslak ürün müşteriye gitmemeli — yayınlanmamış kampanya sızar
*/

/**
 * Yayında bir mağaza + satılabilir tek ürün kurar.
 *
 * @return array{marka: array{tenant: Tenant, sahip: User}, urun: Product}
 */
function vitrinliMagaza(string $alanAdi): array
{
    $marka = markaKur($alanAdi);
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Basic Tişört']);
    app(VariantService::class)->ekle($urun, [
        'sku' => 'TS-1', 'price' => 199.90, 'cost_price' => 80, 'stock' => 5,
    ]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return ['marka' => $marka, 'urun' => $urun];
}

it('★ cost_price VİTRİNE ÇIKMIYOR — sorgu onu hiç seçmiyor', function () {
    $m = vitrinliMagaza('vitrin-a.test');

    $cevap = $this->getJson("http://vitrin-a.test/api/products/{$m['urun']->slug}")->assertOk();

    // Cevabın tamamında maliyet geçmemeli — anahtar adı da değeri de.
    expect($cevap->json())->not->toHaveKey('product.variants.0.cost_price')
        ->and(json_encode($cevap->json()))->not->toContain('cost_price')
        ->and(json_encode($cevap->json()))->not->toContain('80.00');

    /*
    | ⚠️ Asıl emniyet sunumda değil SORGUDA: kolon hiç seçilmediği için
    | nesnede de yok. Biri modeli doğrudan JSON'a çevirse bile maliyet
    | sızmıyor.
    */
    $urun = app(ProductQuery::class)->vitrindeBul($m['urun']->slug);
    expect($urun?->variants->first()?->getAttributes())->not->toHaveKey('cost_price');
});

it('★ TASLAK ve ARŞİV ürün vitrinde YOK — listede de bağlantıyla da', function () {
    $m = vitrinliMagaza('vitrin-b.test');
    $servis = app(ProductService::class);

    $taslak = $servis->olustur(['title' => 'Gizli Kampanya Ürünü']);
    app(VariantService::class)->ekle($taslak, ['sku' => 'GZ-1', 'price' => 10, 'stock' => 5]);

    $arsiv = $servis->olustur(['title' => 'Kaldırılmış Ürün']);
    app(VariantService::class)->ekle($arsiv, ['sku' => 'AR-1', 'price' => 10, 'stock' => 5]);
    $servis->durumDegistir($arsiv->refresh(), ProductStatus::Active);
    $servis->durumDegistir($arsiv->refresh(), ProductStatus::Archived);

    $liste = $this->getJson('http://vitrin-b.test/api/products')->assertOk();

    expect($liste->json('products'))->toHaveCount(1)
        ->and($liste->json('products.0.slug'))->toBe($m['urun']->slug);

    // "Listede yoksa hiç yok" — doğrudan bağlantı da 404 (1B-K8).
    $this->getJson("http://vitrin-b.test/api/products/{$taslak->slug}")->assertNotFound();
    $this->getJson("http://vitrin-b.test/api/products/{$arsiv->slug}")->assertNotFound();
});

it('satılabilir varyantı olmayan ürün vitrinde YOK', function () {
    $m = vitrinliMagaza('vitrin-c.test');
    $servis = app(ProductService::class);

    $tukenmis = $servis->olustur(['title' => 'Tükenmiş Ürün']);
    app(VariantService::class)->ekle($tukenmis, ['sku' => 'TK-1', 'price' => 10, 'stock' => 0]);
    $servis->durumDegistir($tukenmis->refresh(), ProductStatus::Active);

    $liste = $this->getJson('http://vitrin-c.test/api/products')->assertOk();

    expect($liste->json('products'))->toHaveCount(1);

    $this->getJson("http://vitrin-c.test/api/products/{$tukenmis->slug}")->assertNotFound();
});

it('★ mağaza KAPALIYKEN vitrin 503 + Retry-After veriyor', function () {
    $m = vitrinliMagaza('vitrin-d.test');

    $this->getJson('http://vitrin-d.test/api/products')->assertOk();

    app(StorePublication::class)->kapat();

    // 1A.4'te yazılan kapı, ilk kez gerçek bir rotada.
    $this->getJson('http://vitrin-d.test/api/products')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '3600');

    // ⚠️ Panel bu kapının DIŞINDA: kapalıysa marka mağazasını tekrar
    // açamazdı.
    $token = panelTokeni('vitrin-d.test', $m['marka']['sahip']->email);
    $this->withToken($token)->getJson('http://vitrin-d.test/panel/products')->assertOk();
});

it('fiyat EN DÜŞÜK SATILABİLİR varyanttan türetiliyor', function () {
    vitrinliMagaza('vitrin-e.test');
    $servis = app(ProductService::class);
    $varyantlar = app(VariantService::class);
    $eksenler = app(OptionService::class);

    // Çok varyantlı ürün için eksen şart (UNIQUE(product_id, options)).
    $beden = $eksenler->olustur('Beden');
    foreach (['S', 'M', 'L'] as $d) {
        $eksenler->degerEkle($beden, $d);
    }

    $urun = $servis->olustur(['title' => 'Çok Bedenli Tişört']);
    $servis->eksenleriAyarla($urun, [$beden]);

    // ⚠️ En ucuz olan TÜKENMİŞ: gösterilseydi müşteri seçemeyeceği bir
    // fiyatla çağrılmış olurdu.
    $varyantlar->ekle($urun, ['sku' => 'CB-S', 'price' => 49.90, 'stock' => 0], ['beden' => 's']);
    $varyantlar->ekle($urun, ['sku' => 'CB-M', 'price' => 149.90, 'stock' => 3], ['beden' => 'm']);
    $varyantlar->ekle($urun, ['sku' => 'CB-L', 'price' => 299.90, 'stock' => 5], ['beden' => 'l']);

    $servis->durumDegistir($urun->refresh(), ProductStatus::Active);

    $cevap = $this->getJson('http://vitrin-e.test/api/products')->assertOk();

    // Liste yeniden eskiye — bu ürün başta.
    expect($cevap->json('products.0.slug'))->toBe($urun->slug)
        ->and($cevap->json('products.0.from_price'))->toBe('149.90');
});

it('kategori filtresi ALT AĞACI da kapsıyor', function () {
    vitrinliMagaza('vitrin-f.test');
    $kategoriler = app(CategoryService::class);
    $servis = app(ProductService::class);

    $giyim = $kategoriler->olustur('Giyim');
    $tisort = $kategoriler->olustur('Tişört', $giyim);

    $urun = $servis->olustur(['title' => 'Alt Kategori Ürünü'], $tisort);
    app(VariantService::class)->ekle($urun, ['sku' => 'AK-1', 'price' => 99, 'stock' => 2]);
    $servis->durumDegistir($urun->refresh(), ProductStatus::Active);

    // Müşteri üst kategoriye tıklayınca boş sayfa görmemeli.
    $cevap = $this->getJson('http://vitrin-f.test/api/products?category=giyim')->assertOk();

    expect($cevap->json('products'))->toHaveCount(1)
        ->and($cevap->json('products.0.slug'))->toBe($urun->slug);
});

it('ürün detayı eksenleri, ekmek kırıntısını ve stok durumunu veriyor', function () {
    vitrinliMagaza('vitrin-g.test');
    $kategoriler = app(CategoryService::class);
    $eksenler = app(OptionService::class);
    $servis = app(ProductService::class);

    $giyim = $kategoriler->olustur('Giyim');
    $tisort = $kategoriler->olustur('Tişört', $giyim);

    $renk = $eksenler->olustur('Renk');
    $eksenler->degerEkle($renk, 'Kırmızı', '#c00');
    $eksenler->degerEkle($renk, 'Mavi');

    $urun = $servis->olustur(['title' => 'Desenli Tişört'], $tisort);
    $servis->eksenleriAyarla($urun, [$renk]);
    app(VariantService::class)->ekle($urun, ['sku' => 'DS-1', 'price' => 250, 'stock' => 4], ['renk' => 'kirmizi']);
    $servis->durumDegistir($urun->refresh(), ProductStatus::Active);

    $cevap = $this->getJson("http://vitrin-g.test/api/products/{$urun->slug}")->assertOk();

    expect($cevap->json('product.options.0.slug'))->toBe('renk')
        ->and($cevap->json('product.options.0.values.0.swatch'))->toBe('#c00')
        // Kırıntı `path`'ten çıkıyor, ek sorgu yok (1B-K6).
        ->and($cevap->json('product.breadcrumb'))->toBe([
            ['name' => 'Giyim', 'slug' => 'giyim'],
            ['name' => 'Tişört', 'slug' => 'tisort'],
        ])
        ->and($cevap->json('product.variants.0.in_stock'))->toBeTrue();
});

it('★ satinAlinabilirMi() ile SQL ikizi AYNI cevabı veriyor', function () {
    vitrinliMagaza('vitrin-h.test');
    $servis = app(ProductService::class);
    $varyantlar = app(VariantService::class);

    /*
    | ⚠️ Dört varyant için EKSEN gerekiyor: eksensiz üründe `options` boş
    | kalıyor ve UNIQUE(product_id, options) ikinci varyantı reddediyor.
    | Yani kısıt, "tek seçenekli üründe tek varyant" kuralını kendiliğinden
    | zorluyor — ilk yazımda bunu unutup testi kırdım.
    */
    $eksenler = app(OptionService::class);
    $durum = $eksenler->olustur('Durum');

    foreach (['A', 'B', 'C', 'D'] as $d) {
        $eksenler->degerEkle($durum, $d);
    }

    $urun = $servis->olustur(['title' => 'Sınav Ürünü']);
    $servis->eksenleriAyarla($urun, [$durum]);

    $durumlar = [
        'a' => ['stock' => 5, 'is_active' => true],
        'b' => ['stock' => 0, 'is_active' => true],
        'c' => ['stock' => 5, 'is_active' => false],
        'd' => ['stock' => 0, 'is_active' => false],
    ];

    foreach ($durumlar as $deger => $ayar) {
        $varyantlar->ekle($urun, ['sku' => 'SN-'.$deger, 'price' => 10] + $ayar, ['durum' => $deger]);
    }

    /*
    | ⚠️ Bu testin varlık sebebi: kural İKİ YERDE yazılı.
    |   PHP  → ProductVariant::satinAlinabilirMi()
    |   SQL  → ProductVariant::scopeSatinAlinabilir()
    | Tek uygulama mümkün değil (liste sorgusu veritabanında çözmek
    | zorunda). Biri değişip diğeri unutulursa BURASI kırılır.
    |
    | 1D'de `stock - rezerve > 0` olduğunda ikisi birden değişecek.
    */
    $phpCevabi = $urun->variants()->get()
        ->filter(fn ($v) => $v->satinAlinabilirMi())->pluck('sku')->sort()->values()->all();

    $sqlCevabi = $urun->variants()->satinAlinabilir()->pluck('sku')->sort()->values()->all();

    expect($sqlCevabi)->toBe($phpCevabi)
        ->and($sqlCevabi)->toBe(['SN-a']);
});

it('iki markanın vitrini karışmıyor', function () {
    vitrinliMagaza('vitrin-i.test');

    tenancy()->end();
    $b = vitrinliMagaza('vitrin-j.test');

    $cevap = $this->getJson('http://vitrin-j.test/api/products')->assertOk();

    expect($cevap->json('products'))->toHaveCount(1)
        ->and(Product::count())->toBe(1);
});
