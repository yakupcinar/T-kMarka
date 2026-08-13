<?php

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\CollectionQuery;
use App\Domain\Catalog\CollectionRuleException;
use App\Domain\Catalog\CollectionService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Enums\CollectionType;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCollection;

/*
| Koleksiyonlar (2D).
|
| ★ ÜÇ İDDİA:
|   1  manuel liste ve KURAL birlikte çalışıyor
|   2  kurallı koleksiyon FİYAT DEĞİŞİNCE kendiliğinden güncelleniyor
|      (2D-K2 — üyelik hiçbir yere yazılmıyor)
|   3  ikisi de VİTRİN SORGUSUNDAN geçiyor — taslak çıkmıyor
*/

/**
 * Koleksiyon denemeleri için katalog kurar.
 *
 * @return array<string, mixed> markaKur'un döndürdüğü marka bilgisi
 */
function koleksiyonluMagaza(string $alanAdi): array
{
    $marka = markaKur($alanAdi);
    magazayiHazirla();

    $urunler = app(ProductService::class);
    $varyantlar = app(VariantService::class);

    foreach ([
        ['Basic Tişört', 'Demo', 'TS-1', '150.00'],
        ['Deri Cüzdan', 'Marka', 'CZ-1', '300.00'],
        ['Spor Ayakkabı', 'Nike', 'AY-1', '900.00'],
    ] as [$baslik, $markaAdi, $sku, $fiyat]) {
        $urun = $urunler->olustur(['title' => $baslik, 'brand' => $markaAdi]);
        $varyantlar->ekle($urun, ['sku' => $sku, 'price' => $fiyat, 'stock' => 5]);
        $urunler->durumDegistir($urun->refresh(), ProductStatus::Active);
    }

    return $marka;
}

/** @param array<string, mixed>|null $kural */
function koleksiyonKur(string $baslik, CollectionType $tip, ?array $kural = null): ProductCollection
{
    return app(CollectionService::class)->olustur(['title' => $baslik], $tip, $kural);
}

/** @return list<string> */
function koleksiyonBasliklari(ProductCollection $koleksiyon): array
{
    /** @var list<string> $basliklar */
    $basliklar = app(CollectionQuery::class)->urunler($koleksiyon)->pluck('title')->all();

    return $basliklar;
}

it('MANUEL koleksiyon markanın SIRASINI koruyor', function () {
    koleksiyonluMagaza('kol-a.test');

    $koleksiyon = koleksiyonKur('Öne Çıkanlar', CollectionType::Manual);
    $servis = app(CollectionService::class);

    $ayakkabi = Product::where('title', 'Spor Ayakkabı')->firstOrFail();
    $tisort = Product::where('title', 'Basic Tişört')->firstOrFail();

    /*
    | ⚠️ SIRA BİLEREK `id`'YE TERS: önce eklenen ürünün id'si küçük.
    | `orderByDesc('id')` yazılsaydı test yine yeşil olurdu ve markanın
    | sırayı hiç etkileyemediğini kimse fark etmezdi (2C'de tam bu tuzağa
    | düşüldü).
    */
    $servis->urunEkle($koleksiyon, $ayakkabi, 0);
    $servis->urunEkle($koleksiyon, $tisort, 1);

    expect(koleksiyonBasliklari($koleksiyon))->toBe(['Spor Ayakkabı', 'Basic Tişört']);

    // Sıra değişince liste de değişiyor.
    app(CollectionService::class)->sirala($koleksiyon, [$tisort->id, $ayakkabi->id]);

    expect(koleksiyonBasliklari($koleksiyon))->toBe(['Basic Tişört', 'Spor Ayakkabı']);
});

it('★ KURALLI koleksiyon FİYAT DEĞİŞİNCE kendiliğinden güncelleniyor', function () {
    koleksiyonluMagaza('kol-b.test');

    $koleksiyon = koleksiyonKur('250₺ Altı', CollectionType::Rule, [
        'match' => 'all',
        'conditions' => [['field' => 'price', 'op' => 'lte', 'value' => '250.00']],
    ]);

    expect(koleksiyonBasliklari($koleksiyon))->toBe(['Basic Tişört']);

    /*
    | ★ 2D-K2'NİN KANITI. Üyelik saklansaydı bu satırdan sonra liste
    | BAYAT kalırdı: 400₺'ye çıkan ürün "250₺ altı" koleksiyonunda
    | durmaya devam eder ve bu HATA VERMEZDİ.
    */
    $tisort = Product::where('title', 'Basic Tişört')->firstOrFail();
    $tisort->variants()->update(['price' => '400.00']);

    expect(koleksiyonBasliklari($koleksiyon))->toBe([]);

    // Ve ucuzlayan ürün kendiliğinden GİRİYOR.
    Product::where('title', 'Deri Cüzdan')->firstOrFail()->variants()->update(['price' => '99.00']);

    expect(koleksiyonBasliklari($koleksiyon))->toBe(['Deri Cüzdan']);
});

it('kural MARKA ve KATEGORİ üzerinden de çalışıyor', function () {
    koleksiyonluMagaza('kol-c.test');

    $marka = koleksiyonKur('Nike', CollectionType::Rule, [
        'match' => 'all',
        'conditions' => [['field' => 'brand', 'op' => 'eq', 'value' => 'Nike']],
    ]);

    expect(koleksiyonBasliklari($marka))->toBe(['Spor Ayakkabı']);

    /*
    | ⚠️ Kategori kuralı ALT AĞACI da kapsıyor (1B-K6). Yalnızca birebir
    | kategoriye bakılsaydı "Giyim" koleksiyonu "Giyim > Tişört"teki
    | ürünleri kaçırırdı — boş sayfa, hata yok.
    */
    $kategoriler = app(CategoryService::class);
    $giyim = $kategoriler->olustur('Giyim');
    $tisortler = $kategoriler->olustur('Tişörtler', $giyim);

    $urun = Product::where('title', 'Basic Tişört')->firstOrFail();
    app(ProductService::class)->guncelle($urun, [], $tisortler);

    $kategori = koleksiyonKur('Giyim Dünyası', CollectionType::Rule, [
        'match' => 'all',
        'conditions' => [['field' => 'category', 'op' => 'in_tree', 'value' => $giyim->slug]],
    ]);

    expect(koleksiyonBasliklari($kategori))->toBe(['Basic Tişört']);
});

it('★ "any" ile "all" GERÇEKTEN farklı davranıyor', function () {
    koleksiyonluMagaza('kol-d.test');

    $kosullar = [
        ['field' => 'brand', 'op' => 'eq', 'value' => 'Nike'],
        ['field' => 'price', 'op' => 'lte', 'value' => '250.00'],
    ];

    /*
    | ⚠️ "any" sessizce "all" gibi davransaydı koleksiyon çoğu zaman BOŞ
    | dönerdi — hata yok, sadece boş sayfa. Burada ikisi aynı koşullarla
    | kuruluyor ki fark yalnızca birleştirmeden gelsin.
    */
    $hepsi = koleksiyonKur('Hepsi', CollectionType::Rule, ['match' => 'all', 'conditions' => $kosullar]);
    $herhangi = koleksiyonKur('Herhangi', CollectionType::Rule, ['match' => 'any', 'conditions' => $kosullar]);

    expect(koleksiyonBasliklari($hepsi))->toBe([])
        ->and(koleksiyonBasliklari($herhangi))->toHaveCount(2);
});

it('★ BOŞ KURAL reddediliyor — tüm katalog demek olurdu', function () {
    koleksiyonluMagaza('kol-e.test');

    /*
    | ⚠️ İzin verilseydi koleksiyon TÜM KATALOĞU gösterirdi: hata yok,
    | marka "kampanya koleksiyonu" sanır, vitrinde her ürün çıkardı.
    */
    expect(fn () => koleksiyonKur('Boş', CollectionType::Rule, ['conditions' => []]))
        ->toThrow(CollectionRuleException::class);

    expect(fn () => koleksiyonKur('Kuralsız', CollectionType::Rule, null))
        ->toThrow(CollectionRuleException::class);
});

it('★ BİLİNMEYEN ALAN sessizce ATLANMIYOR', function () {
    koleksiyonluMagaza('kol-f.test');

    /*
    | ⚠️ Atlansaydı iki koşullu kuralın biri uygulanır, koleksiyon FAZLA
    | ürün gösterir ve kimse fark etmezdi. Ayrıca kapalı liste `cost_price`
    | üzerinden koleksiyon kurulmasını da engelliyor.
    */
    expect(fn () => koleksiyonKur('Maliyet', CollectionType::Rule, [
        'conditions' => [['field' => 'cost_price', 'op' => 'lte', 'value' => '10']],
    ]))->toThrow(CollectionRuleException::class);

    // Alan geçerli ama işleç değil.
    expect(fn () => koleksiyonKur('Yanlış İşleç', CollectionType::Rule, [
        'conditions' => [['field' => 'title', 'op' => 'eq', 'value' => 'Tişört']],
    ]))->toThrow(CollectionRuleException::class);
});

it('★ LIKE joker karakteri KAÇIRILIYOR', function () {
    koleksiyonluMagaza('kol-g.test');

    /*
    | ⚠️ Kaçırılmasaydı `%` yazan tek bir kural TÜM kataloğu eşleştirirdi
    | — yine sessizce.
    */
    $koleksiyon = koleksiyonKur('Joker', CollectionType::Rule, [
        'conditions' => [['field' => 'title', 'op' => 'contains', 'value' => '%']],
    ]);

    expect(koleksiyonBasliklari($koleksiyon))->toBe([]);
});

it('★ TASLAK ürün koleksiyonda ÇIKMIYOR — manuelde de', function () {
    koleksiyonluMagaza('kol-h.test');

    $koleksiyon = koleksiyonKur('Seçtiklerimiz', CollectionType::Manual);
    $urun = Product::where('title', 'Basic Tişört')->firstOrFail();

    app(CollectionService::class)->urunEkle($koleksiyon, $urun);

    expect(koleksiyonBasliklari($koleksiyon))->toBe(['Basic Tişört']);

    /*
    | ★ MANUEL KOLEKSİYONUN ASIL RİSKİ: marka ürünü listeye ekler, sonra
    | taslağa alır. Pivot satırı DURUYOR — ürün yalnızca `forStorefront()`
    | sayesinde düşüyor. Koleksiyon kendi sorgusunu yazsaydı yayınlanmamış
    | ürün vitrinde kalırdı.
    */
    app(ProductService::class)->durumDegistir($urun, ProductStatus::Draft);

    expect(koleksiyonBasliklari($koleksiyon))->toBe([]);
});

it('★ KURALLI koleksiyona ELLE ürün eklenemiyor', function () {
    koleksiyonluMagaza('kol-i.test');

    $koleksiyon = koleksiyonKur('Ucuzlar', CollectionType::Rule, [
        'conditions' => [['field' => 'price', 'op' => 'lte', 'value' => '250.00']],
    ]);

    /*
    | ⚠️ İzin verilseydi "bu ürün neden burada" sorusunun İKİ cevabı
    | olurdu ve elle eklenen ürün, kural onu dışlasa bile listede kalırdı.
    */
    expect(fn () => app(CollectionService::class)->urunEkle($koleksiyon, Product::firstOrFail()))
        ->toThrow(CollectionRuleException::class);
});

it('★ MANUELE dönerken KURAL SİLİNİYOR', function () {
    koleksiyonluMagaza('kol-j.test');

    $koleksiyon = koleksiyonKur('Ucuzlar', CollectionType::Rule, [
        'conditions' => [['field' => 'price', 'op' => 'lte', 'value' => '250.00']],
    ]);

    app(CollectionService::class)->guncelle($koleksiyon, [], CollectionType::Manual);

    /*
    | ⚠️ Kural kalsaydı koleksiyon manuel görünür, gövdede öylece dururdu;
    | bir gün tip geri çevrildiğinde markanın hatırlamadığı eski kural
    | yürürlüğe girerdi.
    */
    expect($koleksiyon->refresh()->rules)->toBeNull()
        ->and(koleksiyonBasliklari($koleksiyon))->toBe([]);
});

it('★ BOZUK KURAL veritabanından gelse de çalıştırılmıyor', function () {
    koleksiyonluMagaza('kol-k.test');

    $koleksiyon = koleksiyonKur('Ucuzlar', CollectionType::Rule, [
        'conditions' => [['field' => 'price', 'op' => 'lte', 'value' => '250.00']],
    ]);

    /*
    | ⚠️ "Yazarken doğruladık" YETMEZ: kural veritabanına elle, seed'le ya
    | da eski bir sürümle girmiş olabilir. Doğrulanmadan çalıştırılsaydı
    | bilinmeyen alan sessizce atlanır ve koleksiyon TÜM kataloğu
    | gösterirdi.
    */
    ProductCollection::withoutEvents(function () use ($koleksiyon) {
        $koleksiyon->forceFill(['rules' => ['conditions' => [['field' => 'gizli', 'op' => 'eq', 'value' => 'x']]]])->save();
    });

    expect(fn () => koleksiyonBasliklari($koleksiyon->refresh()))
        ->toThrow(CollectionRuleException::class);
});

it('★ UÇTAN: panel kurallı koleksiyon açıyor, vitrin gösteriyor', function () {
    $marka = koleksiyonluMagaza('kol-l.test');
    app(StorePublication::class)->yayinla();

    $token = panelTokeni('kol-l.test', $marka['sahip']->email);

    $olustur = $this->withToken($token)->postJson('http://kol-l.test/panel/collections', [
        'title' => '250₺ Altı',
        'type' => 'rule',
        'rules' => ['match' => 'all', 'conditions' => [['field' => 'price', 'op' => 'lte', 'value' => '250.00']]],
    ])->assertCreated();

    /*
    | ⚠️ Slug UÇTAN okunuyor, modelden değil: "istemci bu değeri nereden
    | bulacak" sorusunu sormayan test iki ölü uç kaçırmıştı (1D.6).
    */
    $slug = $olustur->json('collection.slug');
    $uuid = $olustur->json('collection.uuid');

    expect($slug)->toBeString()->and($uuid)->toBeString();

    // Panel kuralın ŞU AN ne getirdiğini görebiliyor.
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->getJson("http://kol-l.test/panel/collections/{$uuid}/products")
        ->assertOk();

    $vitrin = $this->getJson("http://kol-l.test/api/collections/{$slug}")->assertOk();

    expect($vitrin->json('meta.total'))->toBe(1)
        ->and($vitrin->json('products.0.title'))->toBe('Basic Tişört');

    // Listede de görünüyor.
    $liste = $this->getJson('http://kol-l.test/api/collections')->assertOk();

    expect($liste->json('collections.0.slug'))->toBe($slug);
});

it('★ GEÇERSİZ KURAL uçtan 422 dönüyor — 500 değil', function () {
    $marka = koleksiyonluMagaza('kol-m.test');

    $token = panelTokeni('kol-m.test', $marka['sahip']->email);

    /*
    | ⚠️ İstisna eşlenmeseydi marka yığın izi görürdü ve panel hatayı
    | kullanıcıya anlatamazdı (1E'de aynısı yaşandı: sağlayıcı hatası 500
    | dönüyordu).
    */
    $this->withToken($token)->postJson('http://kol-m.test/panel/collections', [
        'title' => 'Bozuk',
        'type' => 'rule',
        'rules' => ['conditions' => [['field' => 'cost_price', 'op' => 'lte', 'value' => '10']]],
    ])->assertStatus(422);
});

it('pasif koleksiyon vitrinde 404', function () {
    koleksiyonluMagaza('kol-n.test');
    app(StorePublication::class)->yayinla();

    $koleksiyon = koleksiyonKur('Gizli', CollectionType::Manual);
    $koleksiyon->is_active = false;
    $koleksiyon->save();

    $this->getJson("http://kol-n.test/api/collections/{$koleksiyon->slug}")->assertNotFound();
});

it('iki markanın koleksiyonları karışmıyor', function () {
    koleksiyonluMagaza('kol-o.test');
    koleksiyonKur('Öne Çıkanlar', CollectionType::Manual);

    tenancy()->end();
    koleksiyonluMagaza('kol-p.test');

    expect(ProductCollection::count())->toBe(0);
});
