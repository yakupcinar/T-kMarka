<?php

use App\Domain\Catalog\EmptySlugException;
use App\Domain\Catalog\OptionService;
use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
| Varyant eksenleri — MAĞAZA seviyesinde (1B-K3).
|
| Bu bloğun varlık sebebi tek bir cümle: aynı değerin iki farklı yazımı
| iki ayrı satır OLMAMALI. Türkçe'de küçük harf çevrimi bunu sağlamıyor,
| slug sağlıyor.
*/

it('eksen ve değer oluşturuluyor, slug üretiliyor', function () {
    $marka = markaKur('eksen-a.test');
    $token = panelTokeni('eksen-a.test', $marka['sahip']->email);

    $cevap = $this->withToken($token)
        ->postJson('http://eksen-a.test/panel/options', ['name' => 'Renk'])
        ->assertStatus(201)
        ->assertJsonPath('option.name', 'Renk')
        ->assertJsonPath('option.slug', 'renk');

    $eksenUuid = $cevap->json('option.uuid');

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://eksen-a.test/panel/options/{$eksenUuid}/values", [
            'value' => 'Kırmızı',
            'swatch' => '#cc0000',
        ])
        ->assertStatus(201)
        ->assertJsonPath('value.value', 'Kırmızı')
        ->assertJsonPath('value.slug', 'kirmizi')
        ->assertJsonPath('value.swatch', '#cc0000');
});

it('★ TÜRKÇE yazım farkı AYNI slug üretiyor — ikinci kayıt reddediliyor', function () {
    markaKur('eksen-b.test');
    $servis = app(OptionService::class);

    /*
    | ⚠️ Bu bloğun asıl sınavı — ve ad BİLEREK noktalı/noktasız I içeriyor.
    |
    | İlk yazımda 'Renk' kullanmıştım: onda I harfi yok, bu yüzden küçük
    | harf çevrimi de doğru sonuç veriyor ve test tuzağı HİÇ denemiyordu.
    |
    | 'İncelik' ile:
    |   mb_strtolower('İncelik') → 'i̇ncelik'  (i + AYRI birleşen nokta)
    |   mb_strtolower('INCELIK') → 'incelik'
    | iki ayrı eksen doğar, kategori filtresi ikiye bölünür ve HATA VERMEZ.
    |
    | Str::slug ikisini de 'incelik'te birleştiriyor.
    */
    $servis->olustur('İncelik');

    expect(fn () => $servis->olustur('INCELIK'))->toThrow(QueryException::class)
        ->and(fn () => $servis->olustur('incelik'))->toThrow(QueryException::class);

    expect(Option::count())->toBe(1);
});

it('aynı eksende aynı değerin farklı yazımı ikinci kez eklenemiyor', function () {
    markaKur('eksen-c.test');
    $servis = app(OptionService::class);
    $renk = $servis->olustur('Renk');

    $servis->degerEkle($renk, 'Kırmızı');

    expect(fn () => $servis->degerEkle($renk, 'KIRMIZI'))->toThrow(QueryException::class)
        ->and(fn () => $servis->degerEkle($renk, 'kırmızı'))->toThrow(QueryException::class);

    expect($renk->values()->count())->toBe(1);
});

it('FARKLI eksenlerde aynı değer olabiliyor', function () {
    markaKur('eksen-d.test');
    $servis = app(OptionService::class);

    // "Standart" hem Beden hem Boy ekseninde bulunabilmeli — benzersizlik
    // eksen İÇİNDE (option_id, slug), mağaza genelinde değil.
    $beden = $servis->olustur('Beden');
    $boy = $servis->olustur('Boy');

    $servis->degerEkle($beden, 'Standart');
    $servis->degerEkle($boy, 'Standart');

    expect($beden->values()->count())->toBe(1)
        ->and($boy->values()->count())->toBe(1);
});

it('slug üretmeyen ad reddediliyor', function () {
    $marka = markaKur('eksen-e.test');
    $token = panelTokeni('eksen-e.test', $marka['sahip']->email);

    // "★" Str::slug'tan boş dönüyor. Kaydedilseydi ikinci böyle değer
    // benzersizlik kısıtına takılır ve marka sebebini anlamazdı.
    $this->withToken($token)
        ->postJson('http://eksen-e.test/panel/options', ['name' => '★'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect(fn () => app(OptionService::class)->olustur('///'))
        ->toThrow(EmptySlugException::class);
});

it('slug istekten YAZILAMIYOR', function () {
    $marka = markaKur('eksen-f.test');
    $token = panelTokeni('eksen-f.test', $marka['sahip']->email);

    // Yazılabilseydi marka "Renk" adına "beden" slug'ı verip filtre
    // adreslerini bozabilirdi.
    $this->withToken($token)
        ->postJson('http://eksen-f.test/panel/options', ['name' => 'Renk', 'slug' => 'beden'])
        ->assertStatus(201)
        ->assertJsonPath('option.slug', 'renk');
});

it('değer BAŞKA eksenin altından yönetilemiyor', function () {
    $marka = markaKur('eksen-g.test');
    $token = panelTokeni('eksen-g.test', $marka['sahip']->email);
    $servis = app(OptionService::class);

    $renk = $servis->olustur('Renk');
    $beden = $servis->olustur('Beden');
    $kirmizi = $servis->degerEkle($renk, 'Kırmızı');

    // 1A.5'in deseni: sorgu eksene daraltılı, yabancı değer sonuç
    // kümesine hiç girmiyor → 404.
    $this->withToken($token)
        ->putJson("http://eksen-g.test/panel/options/{$beden->uuid}/values/{$kirmizi->uuid}", [
            'value' => 'Ele geçirildi',
        ])
        ->assertStatus(404);

    expect($kirmizi->fresh()?->value)->toBe('Kırmızı');
});

it('eksen silinince değerleri de gidiyor', function () {
    markaKur('eksen-h.test');
    $servis = app(OptionService::class);

    $renk = $servis->olustur('Renk');
    $servis->degerEkle($renk, 'Kırmızı');
    $servis->degerEkle($renk, 'Mavi');

    $servis->sil($renk);

    // Öksüz değer kalmamalı (cascadeOnDelete).
    expect(DB::table('option_values')->count())->toBe(0);
});

it('product.write izni olmayan personel giremiyor', function () {
    markaKur('eksen-i.test');

    // "Sipariş & Destek" rolünde product.view var ama product.write YOK.
    $personel = User::factory()->create(['email' => 'destek@eksen-i.test', 'password' => 'sifre1234']);
    $personel->roles()->sync(Role::where('name', 'Sipariş & Destek')->pluck('id'));

    $token = panelTokeni('eksen-i.test', $personel->email);

    guardOnbelleginiTemizle();
    $this->withToken($token)->getJson('http://eksen-i.test/panel/options')->assertStatus(403);
});

it('iki markanın eksenleri karışmıyor', function () {
    markaKur('eksen-j.test');
    app(OptionService::class)->olustur('Renk');

    tenancy()->end();
    markaKur('eksen-k.test');

    // Aynı slug, ayrı şema — çakışma yok.
    app(OptionService::class)->olustur('Renk');

    expect(Option::count())->toBe(1);
});
