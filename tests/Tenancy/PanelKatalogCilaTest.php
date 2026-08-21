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

/*
| EKSEN SINIRI VE MERKEZ ARAMASI (4.5S) — gerçek kullanımdan.
|
| ★ *"varyant ekseni kaydetsem bile seçenekler gelmiyor, tüm varyant
| eksenlerini (5 tane var) aynı anda kaydetmek istediğimde varyant kısmına
| seçenek olarak düşmediler."*
|
| ⚠️ Sebep: bir üründe en fazla 3 eksen olabiliyor (1B-K4). İstek
| doğrulaması beşi reddediyordu ama ekranda HİÇBİR ŞEY GÖRÜNMÜYORDU —
| düz `router.post` 422'yi sessizce yutuyordu.
*/

it('★★★ SINIRI ASAN eksen kaydi ANLASILIR sekilde REDDEDILIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $eksenler = collect(['Renk', 'Beden', 'Kalıp', 'Ram', 'Depolama'])
        ->map(fn (string $ad) => eksenliDeger($ad, ['A', 'B']));

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört', 'brand' => 'D']);

    /*
    | ⚠️ Beşini birden kaydetmek: markanın yaptığı tam olarak buydu.
    */
    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/eksenler",
            ['option_uuids' => $eksenler->pluck('uuid')->all()])
        ->assertSessionHasErrors('option_uuids');

    /*
    | ⚠️ MESAJIN KENDİSİ ölçülüyor. Varsayılan metin çeviri anahtarını
    | olduğu gibi basıyordu (`validation.max.array`) ve marka ekranda
    | onu görüyordu — gerçek koşuda yakalandı.
    */
    expect(session('errors')?->get('option_uuids')[0] ?? '')
        ->toContain('en fazla 3 eksen')
        ->and(session('errors')?->get('option_uuids')[0] ?? '')->not->toContain('validation.');

    // ⚠️ Hiçbiri bağlanmamalı — yarım kayıt en kötü sonuç olurdu.
    expect($urun->refresh()->options()->count())->toBe(0);

    // Sınır içinde kalan kayıt çalışıyor.
    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/eksenler",
            ['option_uuids' => $eksenler->take(3)->pluck('uuid')->all()])
        ->assertSessionHasNoErrors();

    expect($urun->refresh()->options()->count())->toBe(3);
});

it('★★★ EKRAN eksen SINIRINI da biliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört', 'brand' => 'D']);

    /*
    | ⚠️ Sayı arayüzde sabit yazılsaydı sınır değiştiğinde iki taraf
    | ayrışırdı — 4.5L'deki "deneme_gun" kararının aynısı.
    */
    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get("http://marka-a.test/yonetim/urunler/{$urun->uuid}")->getContent() ?: '',
    );

    expect($veri['props']['maksEksen'])->toBe(ProductService::MAKS_EKSEN);
});

it('★★★ EKSEN KAYDEDILINCE secenekler EKRANA dusuyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $eksen = eksenliDeger('Renk', ['Kırmızı', 'Mavi']);
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört', 'brand' => 'D']);

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/eksenler", ['option_uuids' => [$eksen->uuid]]);

    /*
    | ⚠️ ASIL ŞİKÂYET BUYDU: "kaydetsem bile seçenekler gelmiyor."
    | Sunucunun ürünün eksenlerini DEĞERLERİYLE göndermesi şart; yoksa
    | ekran varyant formunda seçici çizemez.
    */
    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get("http://marka-a.test/yonetim/urunler/{$urun->uuid}")->getContent() ?: '',
    );

    expect($veri['props']['urun']['options'])->toHaveCount(1)
        ->and($veri['props']['urun']['options'][0]['slug'])->toBe('renk')
        ->and($veri['props']['urun']['options'][0]['values'])->toHaveCount(2);
});
