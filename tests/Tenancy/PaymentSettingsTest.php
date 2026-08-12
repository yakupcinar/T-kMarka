<?php

use App\Domain\Payment\FakePaymentProvider;
use App\Domain\Payment\PaymentNotConfiguredException;
use App\Domain\Payment\PaymentReadiness;
use App\Domain\Payment\PaymentService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\SettingGroup;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;

/*
| Ödeme sağlayıcı ayarları (1E-K11 · 1E.7.1).
|
| ★ Bu bloğun iddiası: YANLIŞ YAZILMIŞ ANAHTAR SESSİZCE KABUL EDİLMİYOR.
|
| ⚠️ Genel ayar ucu serbest biçimli. `iyzico_api_key` yerine `iyzico_api`
| yazan marka orada hata almaz; anahtar hiçbir zaman okunmayan bir satıra
| yazılır, panel "tanımlı" gösterir ve yanlış olduğu ancak İLK GERÇEK
| MÜŞTERİDE anlaşılır — para çekilmemiş olarak.
*/

/** Ödeme ayarları ucuna erişebilen bir panel oturumu kurar. */
function odemeAyarTokeni(string $alanAdi): string
{
    $sahip = User::where('is_owner', true)->firstOrFail();

    return panelTokeni($alanAdi, $sahip->email);
}

it('panel gerekli anahtarları ve DURUMLARINI gösteriyor', function () {
    markaKur('oday-a.test');
    magazayiHazirla();

    $token = odemeAyarTokeni('oday-a.test');

    guardOnbelleginiTemizle();
    $cevap = $this->withToken($token)->getJson('http://oday-a.test/panel/payment')->assertOk();

    /*
    | ⚠️ Anahtarın DEĞERİ dönmüyor, yalnızca "tanımlı mı" bilgisi.
    | Panelde okunmasına gerek yok; yazılması yeterli (§4).
    */
    expect($cevap->json('payment.provider'))->toBe('fake')
        ->and($cevap->json('payment.available'))->toBe(['fake'])
        ->and($cevap->json('payment.keys.fake_secret'))->toBeTrue()
        ->and($cevap->json('payment.ready'))->toBeTrue()
        ->and(json_encode($cevap->json()))->not->toContain(
            (string) app(SettingsService::class)->al(SettingGroup::Payment, 'fake_secret')
        );
});

it('★ TANINMAYAN anahtar 422 ile REDDEDİLİYOR', function () {
    markaKur('oday-b.test');
    magazayiHazirla();

    $token = odemeAyarTokeni('oday-b.test');

    /*
    | ⚠️ 1E-K11'in kalbi. Kabul edilseydi marka "ayarladım" sanırdı ve
    | sistem onu hiç okumazdı.
    */
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://oday-b.test/panel/payment', ['keys' => ['fake_secre' => 'abc']])
        ->assertStatus(422)
        ->assertJsonPath('errors.keys', ['fake_secre'])
        ->assertJsonPath('expected', ['fake_secret']);

    // Yanlış anahtar HİÇ yazılmamış olmalı.
    expect(app(SettingsService::class)->al(SettingGroup::Payment, 'fake_secre'))->toBeNull();
});

it('★ TANINMAYAN sağlayıcı seçilemiyor', function () {
    markaKur('oday-c.test');
    magazayiHazirla();

    $token = odemeAyarTokeni('oday-c.test');

    // Canlıda `iyzico` yerine `iyziko` yazılan tek harf.
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://oday-c.test/panel/payment', ['provider' => 'iyziko'])
        ->assertStatus(422);

    expect(app(SettingsService::class)->al(SettingGroup::Payment, 'provider'))->toBe('fake');
});

it('anahtar ŞİFRELİ yazılıyor', function () {
    markaKur('oday-d.test');
    magazayiHazirla();

    $token = odemeAyarTokeni('oday-d.test');

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://oday-d.test/panel/payment', ['keys' => ['fake_secret' => 'yeni-gizli-anahtar']])
        ->assertOk()
        ->assertJsonPath('payment.ready', true);

    $satir = Setting::where('group', 'payment')
        ->where('key', FakePaymentProvider::GIZLI_ANAHTAR)->firstOrFail();

    expect($satir->is_encrypted)->toBeTrue()
        ->and((string) $satir->getRawOriginal('value'))->not->toContain('yeni-gizli-anahtar')
        ->and(app(SettingsService::class)->al(SettingGroup::Payment, 'fake_secret'))->toBe('yeni-gizli-anahtar');
});

it('BOŞ gönderim mevcut anahtarı SİLMİYOR', function () {
    markaKur('oday-e.test');
    magazayiHazirla();

    $eski = app(SettingsService::class)->al(SettingGroup::Payment, 'fake_secret');
    $token = odemeAyarTokeni('oday-e.test');

    /*
    | ⚠️ Panel formunda anahtar alanı BOŞ gösteriliyor (değeri hiç
    | dönmüyoruz). Kaydete basan marka boş gönderir; bu, "anahtarı sil"
    | anlamına gelmemeli — yoksa her ayar kaydında ödeme bozulurdu.
    */
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->putJson('http://oday-e.test/panel/payment', ['keys' => ['fake_secret' => '']])
        ->assertOk()
        ->assertJsonPath('payment.ready', true);

    expect(app(SettingsService::class)->al(SettingGroup::Payment, 'fake_secret'))->toBe($eski);
});

it('★ EKSİK ANAHTARLA ödeme başlatılamıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('oday-f.test');
    app(StorePublication::class)->yayinla();

    // Anahtar silindi (marka hesabını henüz tanımlamadı).
    app(SettingsService::class)->yaz(SettingGroup::Payment, 'fake_secret', null, sifreli: true);

    expect(app(PaymentReadiness::class)->hazirMi())->toBeFalse()
        ->and(app(PaymentReadiness::class)->eksikler())->toBe(['fake_secret']);

    expect(fn () => app(PaymentService::class)->baslat($siparis, 'http://oday-f.test/odeme/donus'))
        ->toThrow(PaymentNotConfiguredException::class);

    /*
    | ⚠️ Deneme satırı AÇILMAMIŞ olmalı: kontrol en başta. Sonra
    | patlasaydı arkada yarım bir kayıt kalır ve `UNIQUE (order_id,
    | idempotency_key)` yüzünden ikinci denemeyi de engellerdi.
    */
    expect(Payment::count())->toBe(0);
});

it('★ UÇTAN: eksik yapılandırmada 503 + Retry-After', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('oday-g.test');
    app(StorePublication::class)->yayinla();

    app(SettingsService::class)->yaz(SettingGroup::Payment, 'fake_secret', null, sifreli: true);

    /*
    | ⚠️ 503 — GEÇİCİ. Müşterinin hatası yok, markanın tamamlaması gereken
    | bir yapılandırma var.
    |
    | ⚠️ Eksik anahtar ADLARI cevapta YOK: markanın altyapısı hakkında
    | müşteriye bilgi vermenin anlamı yok.
    */
    $cevap = $this->postJson("http://oday-g.test/api/orders/{$siparis->uuid}/pay")
        ->assertStatus(503);

    $cevap->assertHeader('Retry-After', '300');

    expect(json_encode($cevap->json()))->not->toContain('fake_secret');
});

it('iki markanın ödeme ayarları karışmıyor', function () {
    markaKur('oday-h.test');
    magazayiHazirla();

    $tokenA = odemeAyarTokeni('oday-h.test');

    guardOnbelleginiTemizle();
    $this->withToken($tokenA)
        ->putJson('http://oday-h.test/panel/payment', ['keys' => ['fake_secret' => 'A-anahtari']])
        ->assertOk();

    tenancy()->end();
    markaKur('oday-i.test');
    magazayiHazirla();

    expect(app(SettingsService::class)->al(SettingGroup::Payment, 'fake_secret'))
        ->not->toBe('A-anahtari');
});
