<?php

/*
| Kapı görevlisinin (InitializeTenancyByDomain) ve Caddy sorgu ucunun
| testleri. HTTP tarafı — gerçek istek atılıyor.
*/

it('kiraci adresine gelen istek dogru markayi buluyor', function () {
    $a = kiraciOlustur('marka-a.test');
    $b = kiraciOlustur('marka-b.test');

    $this->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee($a->id);

    $this->get('http://marka-b.test/')
        ->assertOk()
        ->assertSee($b->id);
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
