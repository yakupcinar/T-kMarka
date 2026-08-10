<?php

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;
use App\Models\Role;
use App\Models\User;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Mağazayı yayına hazır hâle getirir: şirket bilgileri + üç yasal metin.
 */
function magazayiHazirla(): void
{
    $ayarlar = app(SettingsService::class);

    foreach ([
        'name' => 'Test Markası',
        'legal_name' => 'Test Ticaret Ltd. Şti.',
        'tax_number' => '1234567890',
        'tax_office' => 'Kadıköy',
        'address' => 'Test Cad. No:1',
        'phone' => '+902161112233',
        'contact_email' => 'destek@test.com',
    ] as $anahtar => $deger) {
        $ayarlar->yaz(SettingGroup::Store, $anahtar, $deger);
    }

    $belgeler = app(LegalDocumentService::class);

    foreach (LegalDocumentType::cases() as $tur) {
        $belgeler->taslagaYaz($tur, "{$tur->value} metni");
        $belgeler->yayinla($tur);
    }
}

it('yeni marka kapalı doğuyor ve eksikleri BİRDEN bildiriyor', function () {
    $marka = markaKur('yayin-a.test');
    $token = panelTokeni('yayin-a.test', $marka['sahip']->email);

    $cevap = $this->withToken($token)->getJson('http://yayin-a.test/panel/store/readiness');

    $cevap->assertOk()
        ->assertJson(['is_published' => false, 'ready' => false]);

    // 6 zorunlu ayar + 3 yayınlanmamış belge
    expect($cevap->json('missing'))->toHaveCount(9);

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson('http://yayin-a.test/panel/store/publish')
        ->assertStatus(422)
        ->assertJsonCount(9, 'missing');
});

it('eksikler tamamlanınca yayına alıyor', function () {
    $marka = markaKur('yayin-b.test');
    magazayiHazirla();
    $token = panelTokeni('yayin-b.test', $marka['sahip']->email);

    $this->withToken($token)
        ->postJson('http://yayin-b.test/panel/store/publish')
        ->assertOk()
        ->assertJson(['is_published' => true]);
});

it('yayındayken kilitli alanı reddediyor, serbest alanı kabul ediyor', function () {
    $marka = markaKur('yayin-c.test');
    magazayiHazirla();
    $token = panelTokeni('yayin-c.test', $marka['sahip']->email);
    $this->withToken($token)->postJson('http://yayin-c.test/panel/store/publish')->assertOk();

    // Sözleşmenin içine giren alan → 409 (yetki değil, ZAMAN sorunu).
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://yayin-c.test/panel/settings', ['store' => ['tax_number' => '9999999999']])
        ->assertStatus(409);

    // Sözleşmede geçmeyen alanlar serbest kalmalı — yoksa marka kargo
    // ücretini değiştirmek için mağazasını kapatmak zorunda kalırdı.
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://yayin-c.test/panel/settings', ['shipping' => ['flat_fee' => 39.90]])
        ->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://yayin-c.test/panel/settings', ['store' => ['name' => 'Yeni Ad']])
        ->assertOk();
});

it('kilitli alan reddedilince aynı istekteki diğer alanlar da YAZILMIYOR', function () {
    $marka = markaKur('yayin-d.test');
    magazayiHazirla();
    $token = panelTokeni('yayin-d.test', $marka['sahip']->email);
    $this->withToken($token)->postJson('http://yayin-d.test/panel/store/publish')->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)->putJson('http://yayin-d.test/panel/settings', [
        'shipping' => ['flat_fee' => 99.90],          // serbest
        'store' => ['tax_number' => '9999999999'],    // kilitli
    ])->assertStatus(409);

    // ⚠️ Yarım yazma olmamalı: marka "hata aldım" derken ayarlarının
    // yarısı değişmiş olmasın.
    expect(app(SettingsService::class)->al(SettingGroup::Shipping, 'flat_fee'))->not->toBe(99.90);
});

it('yayındayken taslağa yazılıyor ama YAYINLANAMIYOR', function () {
    $marka = markaKur('yayin-e.test');
    magazayiHazirla();
    $token = panelTokeni('yayin-e.test', $marka['sahip']->email);
    $this->withToken($token)->postJson('http://yayin-e.test/panel/store/publish')->assertOk();

    // Taslak kimseye görünmüyor → serbest.
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://yayin-e.test/panel/legal/returns', ['content' => 'iade 7 gün'])
        ->assertOk();

    // Yayınlamak müşterinin gördüğünü değiştirir → 409.
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson('http://yayin-e.test/panel/legal/returns/publish')
        ->assertStatus(409);
});

it('kapat → düzenle → tekrar aç akışı çalışıyor', function () {
    $marka = markaKur('yayin-f.test');
    magazayiHazirla();
    $token = panelTokeni('yayin-f.test', $marka['sahip']->email);
    $this->withToken($token)->postJson('http://yayin-f.test/panel/store/publish')->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)->postJson('http://yayin-f.test/panel/store/close')->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://yayin-f.test/panel/settings', ['store' => ['tax_number' => '9999999999']])
        ->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson('http://yayin-f.test/panel/legal/returns/publish')
        ->assertStatus(201);

    guardOnbelleginiTemizle();
    $this->withToken($token)->postJson('http://yayin-f.test/panel/store/publish')->assertOk();
});

it('yayın durumu ayar gibi yazılamıyor — denetim atlanamıyor', function () {
    $marka = markaKur('yayin-g.test');
    $token = panelTokeni('yayin-g.test', $marka['sahip']->email);

    // Buradan yazılabilseydi eksik bilgiyle mağaza açılır, hazırlık
    // denetiminin TAMAMI atlanırdı.
    $this->withToken($token)
        ->putJson('http://yayin-g.test/panel/settings', ['store' => ['is_published' => true]])
        ->assertStatus(422);

    expect(app(SettingsService::class)->al(SettingGroup::Store, 'is_published', false))->toBeFalse();
});

it('geçersiz ayar grubu reddediliyor', function () {
    $marka = markaKur('yayin-h.test');
    $token = panelTokeni('yayin-h.test', $marka['sahip']->email);

    // 'payments' (fazladan s) — serbest metin olsaydı hiçbir yerde
    // görünmeyen bir gruba yazılır, hata da vermezdi.
    $this->withToken($token)
        ->putJson('http://yayin-h.test/panel/settings', ['payments' => ['api_key' => 'x']])
        ->assertStatus(422);
});

it('settings.write izni olmayan personel giremiyor', function () {
    markaKur('yayin-i.test');

    // Katalog rolünde settings.write YOK (DefaultRoles).
    $personel = User::factory()->create(['email' => 'katalog@yayin-i.test', 'password' => 'sifre1234']);
    $personel->roles()->sync(Role::where('name', 'Katalog')->pluck('id'));

    $token = panelTokeni('yayin-i.test', $personel->email);

    guardOnbelleginiTemizle();
    $this->withToken($token)->getJson('http://yayin-i.test/panel/settings')->assertStatus(403);

    guardOnbelleginiTemizle();
    $this->withToken($token)->postJson('http://yayin-i.test/panel/store/close')->assertStatus(403);
});

it('vitrin kapısı mağaza kapalıyken 503 + Retry-After dönüyor', function () {
    markaKur('yayin-j.test');

    // Vitrin rotaları 1B'de gelecek; kapının kendisini şimdi ölçüyoruz.
    Route::middleware(['api', InitializeTenancyByDomain::class, 'magaza-acik'])
        ->get('/deneme-vitrin', fn () => response()->json(['ok' => true]));

    $this->getJson('http://yayin-j.test/deneme-vitrin')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '3600');

    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $this->getJson('http://yayin-j.test/deneme-vitrin')->assertOk();
});
