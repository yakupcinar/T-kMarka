<?php

use App\Domain\Settings\DefaultSettings;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\SettingGroup;

/*
| Kapı görevlisinin (InitializeTenancyByDomain) ve Caddy sorgu ucunun
| testleri. HTTP tarafı — gerçek istek atılıyor.
*/

it('kiraci adresine gelen istek dogru markayi buluyor', function () {
    /*
    | ⚠️ BU TEST 4A'DA YENİDEN YAZILDI ve ölçtüğü şey GÜÇLENDİ.
    |
    | Önce `/` bir hata ayıklama ucuydu ve `tenant('id')` basıyordu; test de
    | o kimliği arıyordu. Yani ölçtüğü tek şey "kiracı DEĞİŞKENİ doğru
    | kuruldu"ydu — şemadan tek bir satır bile okunmuyordu.
    |
    | `/` artık vitrin. Aranan şey markanın KENDİ AYARINDAN gelen mağaza
    | adı; o ayar marka şemasında duruyor. Yani bu hâliyle test
    | `search_path`'in gerçekten o markaya kurulduğunu ölçüyor.
    */
    foreach ([['marka-a.test', 'Ada Kozmetik'], ['marka-b.test', 'Bora Spor']] as [$alanAdi, $ad]) {
        $marka = kiraciOlustur($alanAdi, $ad);

        tenancy()->initialize($marka);
        app(DefaultSettings::class)->kur($ad);
        magazayiHazirla();

        /*
        | ⚠️ AD BURADA, `magazayiHazirla()`'DAN SONRA yazılıyor: o yardımcı
        | şirket bilgilerini doldururken `name`'i de "Test Markası" yapıyor
        | ve markanın kendi adını EZİYOR. Önce yazılsaydı iki marka da aynı
        | adı taşır, test "ayrı şemadan okuyor muyuz" sorusunu ölçemezdi —
        | üstelik yeşil kalırdı.
        */
        app(SettingsService::class)->yaz(SettingGroup::Store, 'name', $ad);

        app(StorePublication::class)->yayinla();
        tenancy()->end();
    }

    $this->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee('Ada Kozmetik')
        ->assertDontSee('Bora Spor');

    $this->get('http://marka-b.test/')
        ->assertOk()
        ->assertSee('Bora Spor')
        ->assertDontSee('Ada Kozmetik');
});

it('tanimsiz alan adi 404 donuyor', function () {
    kiraciOlustur('marka-a.test');

    // Kayıtlı olmayan bir adres → kiracı bulunamaz → 404.
    // Hata sayfası değil, temiz 404 dönmeli (bootstrap/app.php).
    $this->get('http://kayitli-degil.test/')->assertNotFound();
});

it('merkez adres kiraci cozumlemesi yapmiyor', function () {
    kiraciOlustur('marka-a.test');

    $this->get('http://localhost/')
        ->assertOk()
        ->assertSee('kontrol düzlemi');

    expect(tenancy()->initialized)->toBeFalse();
});

it('domain-check kayitli alan adi icin 200 donuyor', function () {
    kiraciOlustur('marka-a.test');

    $this->get('http://localhost/tenancy/domain-check?domain=marka-a.test')
        ->assertOk();
});

it('domain-check kayitsiz alan adi icin 404 donuyor', function () {
    kiraciOlustur('marka-a.test');

    $this->get('http://localhost/tenancy/domain-check?domain=baskasinin-adresi.test')
        ->assertNotFound();
});

it('domain-check bos parametre icin 404 donuyor', function () {
    $this->get('http://localhost/tenancy/domain-check?domain=')->assertNotFound();
});

it('domain-check buyuk harfli alan adini taniyor', function () {
    kiraciOlustur('marka-a.test');

    // DNS büyük/küçük harf duyarsız — sınırda küçültme yapılıyor.
    $this->get('http://localhost/tenancy/domain-check?domain=MARKA-A.TEST')
        ->assertOk();
});
