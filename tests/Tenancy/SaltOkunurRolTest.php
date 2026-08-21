<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Identity\DefaultRoles;
use App\Enums\Permission;
use App\Models\Product;

/*
| SALT OKUNUR PERSONEL ROLÜ (4.6S) — kullanıcı isteği.
|
| ★ *"Her şeyi görebilecek ama hiçbir tıkladığı sonuç almayacak."*
|
| ⚠️ Bu rol daha önce KURULAMIYORDU ve sebebi ölçüldü: ürün, katalog,
| ayar ve personel SAYFALARI yazma izniyle korunuyordu. `product.view`
| Faz 1'den beri tanımlıydı ama HİÇBİR ROTA onu kullanmıyordu.
*/

beforeEach(function () {
    $this->withoutVite();
});

/** @return list<string> */
function saltOkunurIzinleri(): array
{
    return array_map(
        fn (Permission $i) => $i->value,
        DefaultRoles::tanimlar()['Salt Okunur'],
    );
}

it('★★★ SALT OKUNUR rolu HICBIR yazma izni ICERMIYOR', function () {
    /*
    | ⚠️ Rol büyüdükçe içine yazma izni sızması en olası hata. Liste elle
    | okunmuyor, kalıpla taranıyor: yeni bir `*.write` eklenirse test
    | düşer.
    */
    foreach (saltOkunurIzinleri() as $izin) {
        expect($izin)->not->toContain('.write')
            ->and($izin)->not->toContain('.manage')
            ->and($izin)->not->toContain('.fulfill')
            ->and($izin)->not->toContain('.refund');
    }

    expect(saltOkunurIzinleri())->toContain('product.view', 'order.view', 'settings.view');
});

it('★★★ SALT OKUNUR personel BUTUN sayfalari GOREBILIYOR', function () {
    markaKur('marka-a.test');

    $personel = izinliPersonel(saltOkunurIzinleri(), 'okuyucu@marka-a.test');

    /*
    | ⚠️ Kullanıcının istediği tam olarak bu: "siparişlere tıklayıp içine
    | girebilir ve benzeri şeyler".
    */
    foreach ([
        '/yonetim/urunler',
        '/yonetim/katalog',
        '/yonetim/koleksiyonlar',
        '/yonetim/yorumlar',
        '/yonetim/siparisler',
        '/yonetim/iadeler',
        '/yonetim/magaza',
        '/yonetim/tema',
        '/yonetim/yasal',
        '/yonetim/alan-adlari',
        '/yonetim/odeme-ayarlari',
        '/yonetim/personel',
    ] as $yol) {
        $this->actingAs($personel, 'staff-web')
            ->get('http://marka-a.test'.$yol)
            ->assertOk();
    }
});

it('★★★ SALT OKUNUR personel HICBIR yazma ucunu KULLANAMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $urun = app(ProductService::class)->olustur(['title' => 'X', 'brand' => 'D']);

    $personel = izinliPersonel(saltOkunurIzinleri(), 'okuyucu@marka-a.test');

    /*
    | ⚠️ ASIL İDDİA: "hiçbir tıkladığı sonuç almayacak". Ekranda düğmeyi
    | gizlemek KOLAYLIK; gerçek koruma burada (4C-K4).
    */
    $denemeler = [
        ['post', '/yonetim/urunler', ['title' => 'Y', 'brand' => 'D', 'tax_rate' => 20]],
        ['put', "/yonetim/urunler/{$urun->uuid}", ['title' => 'Z', 'brand' => 'D', 'tax_rate' => 20]],
        ['delete', "/yonetim/urunler/{$urun->uuid}", []],
        ['post', '/yonetim/katalog/kategoriler', ['name' => 'Yeni']],
        ['post', '/yonetim/koleksiyonlar', ['title' => 'K', 'type' => 'manual']],
        ['post', '/yonetim/magaza', ['name' => 'Yeni Ad']],
        ['post', '/yonetim/tema', ['primary_color' => '#000000']],
        ['post', '/yonetim/personel', ['name' => 'A', 'email' => 'a@b.test', 'password' => 'sifre1234']],
        ['post', '/yonetim/roller', ['name' => 'Rol', 'permissions' => []]],
    ];

    foreach ($denemeler as [$metod, $yol, $veri]) {
        $this->actingAs($personel, 'staff-web')
            ->{$metod}('http://marka-a.test'.$yol, $veri)
            ->assertForbidden();
    }

    // ⚠️ Hiçbiri işlememiş olmalı — 403 dönüp yine de yazan bir uç en kötüsü.
    expect(Product::count())->toBe(1)
        ->and($urun->refresh()->title)->toBe('X');
});

it('★★★ SALT OKUNUR personel OLUSTURMA FORMUNU bile goremiyor', function () {
    markaKur('marka-a.test');

    $personel = izinliPersonel(saltOkunurIzinleri(), 'okuyucu@marka-a.test');

    /*
    | ⚠️ Doldurup gönderdiğinde 403 alacağı bir ekranı göstermenin anlamı
    | yok — `/urunler/yeni` bilerek yazma grubunda bırakıldı.
    */
    $this->actingAs($personel, 'staff-web')
        ->get('http://marka-a.test/yonetim/urunler/yeni')
        ->assertForbidden();
});

it('★★★ YAZMA IZNI OLAN personel sayfalari GORMEYE DEVAM EDIYOR', function () {
    markaKur('marka-a.test');

    /*
    | ⚠️ EN KRİTİK GERİLEME TESTİ. Sayfalar doğrudan `.view`'a taşınsaydı
    | yayındaki markalarda `product.write` verilmiş ama `.view` verilmemiş
    | roller ekranlarından SESSİZCE düşerdi. `|` (herhangi biri) bunu
    | önlüyor.
    */
    $yazan = izinliPersonel([Permission::ProductWrite->value], 'yazan@marka-a.test');

    $this->actingAs($yazan, 'staff-web')
        ->get('http://marka-a.test/yonetim/urunler')
        ->assertOk();

    $ayarci = izinliPersonel([Permission::SettingsWrite->value], 'ayarci@marka-a.test');

    $this->actingAs($ayarci, 'staff-web')
        ->get('http://marka-a.test/yonetim/magaza')
        ->assertOk();
});

it('★★ IZNI OLMAYAN personel sayfalari GOREMIYOR — kapi hala kapali', function () {
    markaKur('marka-a.test');

    $bos = izinliPersonel([Permission::OrderView->value], 'bos@marka-a.test');

    /*
    | ⚠️ Gevşetme YALNIZCA `.view` ekleyenler için. Hiçbir ürün izni
    | olmayan personel hâlâ giremiyor — yoksa "herkes her şeyi görür"
    | olurdu.
    */
    $this->actingAs($bos, 'staff-web')->get('http://marka-a.test/yonetim/urunler')->assertForbidden();
    $this->actingAs($bos, 'staff-web')->get('http://marka-a.test/yonetim/magaza')->assertForbidden();
    $this->actingAs($bos, 'staff-web')->get('http://marka-a.test/yonetim/personel')->assertForbidden();
});
