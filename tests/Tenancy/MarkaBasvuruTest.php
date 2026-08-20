<?php

use App\Enums\TenantStatus;
use App\Platform\Models\Tenant;
use App\Platform\TenantProvisioning;

/*
| MARKA BAŞVURU / ONAY AKIŞI (4.5N) — kullanıcı isteği.
|
| ★ *"Açılan yeni Markaları tekrar elle eklemek gerekiyor Caddy üzerinden,
| ben o işlemi de yönetim paneline koyalım diyorum yeni gelen Marka
| isteğini onay/red yapayım."*
|
| ⚠️ Self-servis kayıt (3D) markayı ANINDA yayına alıyordu: internetten
| kaydolan herkes çalışan bir mağaza açabiliyordu.
|
| ⚠️ M-1'in şartı BOZULMUYOR ("elle kurulum gerektiren ürün değil,
| taslaktır"): kurulumun tamamı hâlâ otomatik ve senkron. Onay bir
| kurulum adımı değil, bir KARAR.
*/

beforeEach(function () {
    $this->withoutVite();
});

it('★★★ SELF-SERVIS KAYIT markayi ONAY BEKLER durumda aciyor', function () {
    $cevap = $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Deneme Marka',
        'email' => 'sahip@ornek.test',
        'password' => 'sifre1234',
        'subdomain' => 'deneme-marka',
    ])->assertCreated();

    $marka = Tenant::firstOrFail();

    expect($marka->status)->toBe(TenantStatus::Pending)
        /*
        | ⚠️ DENEME SÜRESİ BAŞLAMIYOR: onayı üç gün süren marka 14 günlük
        | denemesinin beşte birini beklemekle geçirirdi.
        */
        ->and($marka->trial_ends_at)->toBeNull();

    // Kurulum yine de TAM: alan adı ve şema hazır.
    expect($marka->domains()->count())->toBe(1);
});

it('★★★ ONAY BEKLEYEN markanin PANELI ve VITRINI kapali', function () {
    expect(TenantStatus::Pending->panelAcikMi())->toBeFalse()
        ->and(TenantStatus::Pending->satisAcikMi())->toBeFalse();
});

it('★★★ ONAY denemeyi BASLATIYOR', function () {
    $marka = app(TenantProvisioning::class)->ac(
        'Deneme Marka', 'deneme.localhost', 'sahip@ornek.test', 'sifre1234', onayBekliyor: true,
    );

    $yonetici = merkezKullanici();

    $this->actingAs($yonetici, 'platform-web')
        ->post("/yonetim/markalar/{$marka->id}/onayla")
        ->assertRedirect();

    $marka->refresh();

    expect($marka->status)->toBe(TenantStatus::Trial)
        ->and($marka->trial_ends_at)->not->toBeNull();
});

it('★★★ RED markayi KAPATIYOR ve sebebi saklıyor', function () {
    $marka = app(TenantProvisioning::class)->ac(
        'Deneme Marka', 'deneme.localhost', 'sahip@ornek.test', 'sifre1234', onayBekliyor: true,
    );

    $yonetici = merkezKullanici();

    $this->actingAs($yonetici, 'platform-web')
        ->post("/yonetim/markalar/{$marka->id}/reddet", ['sebep' => 'Marka adı başka bir müşteride'])
        ->assertRedirect();

    $marka->refresh();

    /*
    | ⚠️ Kayıt SİLİNMİYOR: "neden reddedildi" cevabı kalmalı ve alan adı
    | hemen yeniden kapılmamalı. Silme yolu 3G'de zaten var.
    */
    expect($marka->status)->toBe(TenantStatus::Closed)
        ->and($marka->getAttribute('rejection_reason'))->toContain('başka bir müşteride');
});

it('★★★ ONAY BEKLEMEYEN marka onaylanamiyor', function () {
    $marka = app(TenantProvisioning::class)->ac(
        'Deneme Marka', 'deneme.localhost', 'sahip@ornek.test', 'sifre1234',
    );

    $yonetici = merkezKullanici();

    /*
    | ⚠️ Zaten denemede olan markaya "onayla" basılırsa deneme süresi
    | YENİDEN yazılırdı — marka bedava 14 gün daha kazanırdı.
    */
    $eskiBitis = $marka->trial_ends_at;

    $this->actingAs($yonetici, 'platform-web')
        ->post("/yonetim/markalar/{$marka->id}/onayla")
        ->assertSessionHas('hata');

    expect($marka->refresh()->trial_ends_at?->toIso8601String())
        ->toBe($eskiBitis?->toIso8601String());
});

it('★★★ ONAY BEKLEYEN markanin alan adi SERTIFIKA ALAMIYOR', function () {
    $marka = app(TenantProvisioning::class)->ac(
        'Deneme Marka', 'deneme.localhost', 'sahip@ornek.test', 'sifre1234', onayBekliyor: true,
    );

    /*
    | ⚠️ `ask` ucu yalnızca `verified_at`'e baksaydı onay bekleyen —
    | hatta REDDEDİLMİŞ — her başvurunun alan adı sertifika alırdı.
    | Kota haftada 50 (3-K5); internetten kaydolan herkesin kota
    | yakabilmesi, bu ucu koymamızın gerekçesini boşa çıkarırdı.
    */
    $this->get('http://localhost/tenancy/domain-check?domain=deneme.localhost')
        ->assertNotFound();

    $yonetici = merkezKullanici();

    $this->actingAs($yonetici, 'platform-web')
        ->post("/yonetim/markalar/{$marka->id}/onayla");

    $this->get('http://localhost/tenancy/domain-check?domain=deneme.localhost')
        ->assertOk();
});

it('★★ tenant:create ONAY ISTEMIYOR — operasyon komutu kilitlenmemeli', function () {
    $marka = app(TenantProvisioning::class)->ac(
        'Komut Marka', 'komut.localhost', 'sahip@ornek.test', 'sifre1234',
    );

    /*
    | ⚠️ Bayrak varsayılan `false`: `tenant:create` ile açılan marka
    | doğrudan denemede. Onay istenseydi geliştirme ve operasyon akışı
    | her seferinde kontrol düzlemine uğramak zorunda kalırdı.
    */
    expect($marka->status)->toBe(TenantStatus::Trial)
        ->and($marka->trial_ends_at)->not->toBeNull();
});
