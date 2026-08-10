<?php

use App\Models\Address;
use App\Models\Customer;

/*
| Adres defteri — projedeki en çok tekrar edecek güvenlik kuralının
| ilk uygulaması: "sahibi olmadığın kaynağa erişemezsin".
*/

/**
 * Kütle atama testinde `customer_id` (int) de gönderiliyor; bu yüzden
 * değer tipi `mixed`.
 *
 * @param  array<string, mixed>  $degisiklikler
 * @return array<string, mixed>
 */
function ornekAdres(array $degisiklikler = []): array
{
    return array_merge([
        'title' => 'Ev',
        'full_name' => 'Ayşe Yılmaz',
        'phone' => '+905321112233',
        'city' => 'İstanbul',
        'district' => 'Kadıköy',
        'neighborhood' => 'Caferağa',
        'line1' => 'Moda Cad. No:12 D:4',
        'postal_code' => '34710',
    ], $degisiklikler);
}

it('müşteri adres ekliyor ve listeliyor', function () {
    markaKur('adres-a.test');
    $m = musteriTokeni('adres-a.test', 'ayse@adres-a.test');

    $this->withToken($m['token'])
        ->postJson('http://adres-a.test/api/addresses', ornekAdres())
        ->assertStatus(201)
        ->assertJsonPath('address.title', 'Ev');

    guardOnbelleginiTemizle();
    $this->withToken($m['token'])
        ->getJson('http://adres-a.test/api/addresses')
        ->assertOk()
        ->assertJsonCount(1, 'addresses');
});

it('URL de id değil uuid görünüyor', function () {
    markaKur('adres-b.test');
    $m = musteriTokeni('adres-b.test', 'ayse@adres-b.test');

    $cevap = $this->withToken($m['token'])
        ->postJson('http://adres-b.test/api/addresses', ornekAdres());

    // Ardışık id sızsaydı müşteri komşu numaraları tarayıp mağazadaki
    // toplam adres sayısını çıkarabilirdi.
    expect($cevap->json('address.uuid'))->toBeString()->toHaveLength(36);
});

it('customer_id istekten GELMİYOR — başkasının defterine yazılamıyor', function () {
    markaKur('adres-c.test');
    $kurban = Customer::factory()->create(['email' => 'kurban@adres-c.test']);
    $m = musteriTokeni('adres-c.test', 'saldirgan@adres-c.test');

    $this->withToken($m['token'])
        ->postJson('http://adres-c.test/api/addresses', ornekAdres([
            'customer_id' => $kurban->id,   // ⚠️ kütle atama denemesi
        ]))
        ->assertStatus(201);

    // Adres saldırganın defterine düşmeli, kurbanınkine değil.
    expect($kurban->addresses()->count())->toBe(0)
        ->and($m['musteri']->addresses()->count())->toBe(1);
});

it('başkasının adresi listede GÖRÜNMÜYOR', function () {
    markaKur('adres-d.test');
    $baskasi = Customer::factory()->create(['email' => 'baskasi@adres-d.test']);
    $baskasi->addresses()->create(ornekAdres(['title' => 'Gizli']));

    $m = musteriTokeni('adres-d.test', 'ben@adres-d.test');

    $this->withToken($m['token'])
        ->getJson('http://adres-d.test/api/addresses')
        ->assertOk()
        ->assertJsonCount(0, 'addresses');
});

it('başkasının adresini güncelleyemiyor ve silemiyor — 404', function () {
    markaKur('adres-e.test');
    $baskasi = Customer::factory()->create(['email' => 'baskasi@adres-e.test']);
    $yabanci = $baskasi->addresses()->create(ornekAdres(['title' => 'Gizli']));

    $m = musteriTokeni('adres-e.test', 'ben@adres-e.test');

    // ⚠️ 403 DEĞİL 404: 403 "böyle bir adres var ama senin değil" demek
    // olurdu ve varlık bilgisi sızdırırdı.
    $this->withToken($m['token'])
        ->putJson("http://adres-e.test/api/addresses/{$yabanci->uuid}", ornekAdres(['title' => 'Ele geçirildi']))
        ->assertStatus(404);

    guardOnbelleginiTemizle();
    $this->withToken($m['token'])
        ->deleteJson("http://adres-e.test/api/addresses/{$yabanci->uuid}")
        ->assertStatus(404);

    // Yabancı adres hiç etkilenmemiş olmalı.
    expect($yabanci->fresh()?->title)->toBe('Gizli')
        ->and($yabanci->fresh()?->deleted_at)->toBeNull();
});

it('kendi adresini güncelleyip silebiliyor', function () {
    markaKur('adres-f.test');
    $m = musteriTokeni('adres-f.test', 'ben@adres-f.test');
    $adres = $m['musteri']->addresses()->create(ornekAdres());

    $this->withToken($m['token'])
        ->putJson("http://adres-f.test/api/addresses/{$adres->uuid}", ornekAdres(['title' => 'İş']))
        ->assertOk()
        ->assertJsonPath('address.title', 'İş');

    guardOnbelleginiTemizle();
    $this->withToken($m['token'])
        ->deleteJson("http://adres-f.test/api/addresses/{$adres->uuid}")
        ->assertOk();

    // Yumuşak silme: satır duruyor, deleted_at dolu.
    expect(Address::withTrashed()->find($adres->id)?->deleted_at)->not->toBeNull()
        ->and($m['musteri']->addresses()->count())->toBe(0);
});

it('giriş yapmamış istek 401 alıyor', function () {
    markaKur('adres-g.test');

    $this->getJson('http://adres-g.test/api/addresses')->assertStatus(401);
});

it('personel token ı adres defterine giremiyor', function () {
    $marka = markaKur('adres-h.test');
    $token = panelTokeni('adres-h.test', $marka['sahip']->email);

    // auth:customer yalnızca Customer token'ı kabul ediyor (1A.2).
    guardOnbelleginiTemizle();
    $this->withToken($token)->getJson('http://adres-h.test/api/addresses')->assertStatus(401);
});

it('eksik alanlar reddediliyor', function () {
    markaKur('adres-i.test');
    $m = musteriTokeni('adres-i.test', 'ben@adres-i.test');

    $this->withToken($m['token'])
        ->postJson('http://adres-i.test/api/addresses', ['title' => 'Ev'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['full_name', 'phone', 'city', 'district', 'line1']);
});

it('A markasının müşterisinin adresi B markasında yok', function () {
    markaKur('adres-j.test');
    $a = musteriTokeni('adres-j.test', 'ortak@ornek.test');
    $a['musteri']->addresses()->create(ornekAdres(['title' => 'A Evi']));

    tenancy()->end();
    markaKur('adres-k.test');
    $b = musteriTokeni('adres-k.test', 'ortak@ornek.test');

    // Aynı e-posta, iki ayrı şema, iki ayrı insan.
    $this->withToken($b['token'])
        ->getJson('http://adres-k.test/api/addresses')
        ->assertOk()
        ->assertJsonCount(0, 'addresses');
});
