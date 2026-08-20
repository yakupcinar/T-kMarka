<?php

use App\Domain\Catalog\ProductService;

/*
| PANEL KATALOG CİLASI (4.5P) — üç şikâyet, gerçek kullanımdan.
|
|   1  eksen kaydetmeden "varyant ekle" → anlaşılmaz sayfa
|   2  ürün oluşturunca varyant/görsel bölümü gelmiyor
|   3  arama KELİME ORTASINDAN eşleşiyor
*/

beforeEach(function () {
    $this->withoutVite();
});

it('★★★ EKSEN DEGERI BOS birakilinca ANLASILIR mesaj donuyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $eksen = eksenliDeger('Renk', ['Kırmızı']);
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört', 'brand' => 'D']);

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/eksenler", ['option_uuids' => [$eksen->uuid]]);

    /*
    | ★ TARAYICININ GÖNDERDİĞİ GİBİ: seçicinin boş seçeneği `value=""`
    | gönderiyor.
    |
    | ⚠️ `ConvertEmptyStringsToNull` onu **null**'a çeviriyor ve `string`
    | kuralı null'da düşüyordu — marka *"options.renk metin olmalıdır"*
    | uyarısı alıyordu. 4.5I.1'in aynısı, BEŞİNCİ kez.
    */
    $cevap = $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/varyantlar", [
            'sku' => 'TS-1', 'price' => '100', 'stock' => 5,
            'options' => ['renk' => ''],
        ]);

    $cevap->assertSessionHasErrors('options.renk');

    $hatalar = session('errors')?->get('options.renk') ?? [];

    expect($hatalar[0] ?? '')->toBe('Her varyant ekseni için bir değer seçin.')
        ->and($hatalar[0] ?? '')->not->toContain('metin olmalıdır');

    expect($urun->variants()->count())->toBe(0);

    /*
    | ⚠️ ANAHTARI HİÇ GÖNDERMEYEN istek de reddedilmeli — ama bunu
    | DOĞRULAMA DEĞİL, DOMAIN yapıyor: `VariantService` "'renk' ekseni
    | eksik" diyor.
    |
    | Kırma denemesi bunu gösterdi: istek kuralına `required` eklemiştim,
    | kaldırdığımda test YİNE GEÇTİ — çünkü koruma orada değildi. Kural
    | gereksiz olduğu için çıkarıldı (4.5E'deki "ölü koruma" dersinin
    | aynısı).
    */
    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/varyantlar", [
            'sku' => 'TS-2', 'price' => '100', 'stock' => 5,
            'options' => [],
        ])
        ->assertSessionHasErrors();

    expect($urun->variants()->count())->toBe(0);
});

it('★★★ ARAMA KELIME BASINDAN esliyor — ortadan DEGIL', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    app(ProductService::class)->olustur(['title' => 'Basic Tişört', 'brand' => 'D']);
    app(ProductService::class)->olustur(['title' => 'Deri Cüzdan', 'brand' => 'D']);

    /*
    | ⚠️ "iş" ORTADAN eşleşseydi "Tişört" gelirdi — bildirilen kusur tam
    | olarak buydu.
    */
    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/urunler?q=iş')->getContent() ?: '',
    );

    expect($veri['props']['urunler']['data'])->toBeEmpty();

    // Kelime BAŞINDAN eşleşme çalışıyor — ve büyük/küçük harf fark etmiyor.
    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/urunler?q=cüz')->getContent() ?: '',
    );

    expect($veri['props']['urunler']['data'])->toHaveCount(1)
        ->and($veri['props']['urunler']['data'][0]['title'])->toBe('Deri Cüzdan');
});

it('★★★ ARAMA ikinci kelimenin BASINDAN da esliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    app(ProductService::class)->olustur(['title' => 'Kahverengi Deri Çanta', 'brand' => 'D']);

    /*
    | ⚠️ Yalnızca `ILIKE 'kelime%'` yazılsaydı (başlığın başı) bu ürün
    | "deri" aramasında HİÇ çıkmazdı — marka kendi ürününü bulamazdı.
    */
    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/urunler?q=deri')->getContent() ?: '',
    );

    expect($veri['props']['urunler']['data'])->toHaveCount(1);
});

it('★★★ ARAMADA DUZENLI IFADE OZEL KARAKTERI kataloğu ACMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    app(ProductService::class)->olustur(['title' => 'Basic Tişört', 'brand' => 'D']);
    app(ProductService::class)->olustur(['title' => 'Deri Cüzdan', 'brand' => 'D']);

    /*
    | ⚠️ Kaçırılmasaydı `.*` TÜM kataloğu döndürürdü. Panelde bu yalnızca
    | gürültü ama aynı desen bir gün vitrine kopyalanırsa yayınlanmamış
    | ürünleri saymanın yolu olurdu.
    */
    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/urunler?q='.urlencode('.*'))->getContent() ?: '',
    );

    expect($veri['props']['urunler']['data'])->toBeEmpty();

    // ⚠️ Yarım desen sorguyu PATLATMAMALI.
    $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/urunler?q='.urlencode('('))
        ->assertOk();
});

it('★★ OLUSTURMA ekrani da eksen listesini aliyor — varyant paneli icin', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    eksenliDeger('Renk', ['Kırmızı']);

    /*
    | ⚠️ "Ürün oluşturunca varyant/görsel bölümü gelmiyordu" kusuru
    | (4.5L) Vue tarafındaydı: Inertia aynı bileşene giderken örneği
    | yeniden kurmuyor ve `yeniMi` donuyordu. Sunucu tarafı bu testle
    | sabitleniyor — prop'lar İKİ ekrana da gitmezse düzeltme çalışamaz.
    */
    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/urunler/yeni')->getContent() ?: '',
    );

    expect($veri['props']['urun'])->toBeNull()
        ->and($veri['props']['eksenler'])->toHaveCount(1);
});
