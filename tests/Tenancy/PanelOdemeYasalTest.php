<?php

use App\Domain\Identity\RoleService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\SettingsService;
use App\Enums\LegalDocumentType;
use App\Enums\Permission;
use App\Enums\SettingGroup;
use App\Models\User;

/*
| PANEL: ÖDEME AYARLARI VE YASAL METİNLER (4.5B)
|
| ★ Faz 4'ün iki en ciddi boşluğu:
|   · marka panelden ödeme sağlayıcısını KURAMIYORDU → gerçek para
|     tahsil edemiyordu
|   · marka sözleşmesini DÜZENLEYEMİYORDU
*/

beforeEach(function () {
    $this->withoutVite();
});

it('★ odeme ayarlari sayfasi aciliyor ve DURUMU gosteriyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/odeme-ayarlari')->getContent(),
    );

    expect($sayfa['component'])->toBe('Odeme')
        ->and($sayfa['props']['odeme'])->toHaveKeys(['provider', 'available', 'keys', 'ready']);
});

it('★★ ANAHTAR DEGERLERI sayfaya SIZMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    app(SettingsService::class)->yaz(SettingGroup::Payment, 'api_key', 'COK-GIZLI-ANAHTAR', sifreli: true);

    $cevap = $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/odeme-ayarlari');

    /*
    | ⚠️ Anahtarlar şifreli saklanıyor (1E.1) ama ekranda gösterilseydi
    | panele giren herkes onları okuyabilirdi. Ekran yalnızca
    | "girilmiş mi" bilgisini veriyor.
    */
    $cevap->assertOk()->assertDontSee('COK-GIZLI-ANAHTAR');

    $sayfa = inertiaVerisi($cevap->getContent());

    expect($sayfa['props']['odeme']['keys'])->toBeArray();

    foreach ($sayfa['props']['odeme']['keys'] as $deger) {
        expect($deger)->toBeBool();   // değer değil, DURUM
    }
});

it('★★ BOS BIRAKILAN anahtar mevcut degeri SILMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $ayarlar = app(SettingsService::class);
    $ayarlar->yaz(SettingGroup::Payment, 'api_key', 'MEVCUT-ANAHTAR', sifreli: true);

    /*
    | ★ Ekran mevcut değeri göstermiyor; marka formu açıp yalnızca
    | sağlayıcıyı değiştirdiğinde anahtar alanları BOŞ gider. Boşu
    | yazsaydık marka farkında olmadan anahtarını SİLER ve tahsilat
    | dururdu.
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/odeme-ayarlari', [
            'provider' => 'fake',
            'keys' => ['api_key' => ''],
        ])->assertRedirect();

    expect($ayarlar->al(SettingGroup::Payment, 'api_key'))->toBe('MEVCUT-ANAHTAR');
});

it('★ anahtar DOLU gonderilince guncelleniyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/odeme-ayarlari', [
            'provider' => 'fake',
            'keys' => ['api_key' => 'YENI-ANAHTAR'],
        ])->assertRedirect();

    expect(app(SettingsService::class)->al(SettingGroup::Payment, 'api_key'))->toBe('YENI-ANAHTAR');
});

it('★★ TANIMSIZ saglayici reddediliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/odeme-ayarlari', ['provider' => 'uydurma-saglayici'])
        ->assertSessionHasErrors('provider');
});

it('★★ IZINSIZ personel odeme ayarlarina GIREMIYOR', function () {
    markaKur('marka-a.test');

    $rol = app(RoleService::class)->olustur('Depocu', [Permission::OrderView->value]);
    $personel = User::factory()->create(['email' => 'depo@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    $this->actingAs($personel->refresh(), 'staff-web')
        ->get('http://marka-a.test/yonetim/odeme-ayarlari')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| YASAL METİNLER
|--------------------------------------------------------------------------
*/

it('★ yasal metin sayfasi TASLAK ve YAYIN durumunu ayri gosteriyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/yasal')->getContent(),
    );

    expect($sayfa['component'])->toBe('Yasal')
        ->and($sayfa['props']['belgeler'])->toHaveCount(count(LegalDocumentType::cases()));

    $ilk = $sayfa['props']['belgeler'][0];

    expect($ilk)->toHaveKeys(['tur', 'ad', 'taslak', 'yayin_surumu', 'yayinlanmamis_degisiklik']);
});

it('★★ TASLAK KAYDETMEK YAYINLAMAK DEGIL', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    sirketBilgileriniDoldur();

    $belgeler = app(LegalDocumentService::class);

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/yasal/returns', ['icerik' => 'yeni iade kosullari'])
        ->assertRedirect();

    /*
    | ⚠️ 1A.4: düzenlemek yayınlamak değil. Taslak yazıldı ama yayın
    | sürümü hâlâ yok — vitrinde de görünmüyor.
    */
    expect($belgeler->taslak(LegalDocumentType::Returns))->toBe('yeni iade kosullari')
        ->and($belgeler->guncelSurum(LegalDocumentType::Returns))->toBeNull();

    $this->get('http://marka-a.test/yasal/returns')->assertNotFound();
});

it('★★ YAYINLAMAK yeni SURUM aciyor, eskisini SILMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();

    $belgeler = app(LegalDocumentService::class);
    $ilkSurum = $belgeler->guncelSurum(LegalDocumentType::Returns);

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/yasal/returns', ['icerik' => 'guncellenmis kosullar'])
        ->assertRedirect();

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/yasal/returns/yayinla')
        ->assertRedirect();

    $yeniSurum = $belgeler->guncelSurum(LegalDocumentType::Returns);

    /*
    | ⚠️ Tablo SALT-EKLEME (1A.4): eski sürüm duruyor çünkü siparişler
    | ona bağlı (1D-K2). Güncellemek eski metni yok etseydi "müşteri neyi
    | onayladı" sorusu cevapsız kalırdı.
    */
    expect($yeniSurum?->version_no)->toBeGreaterThan((int) $ilkSurum?->version_no)
        ->and($belgeler->surum((int) $ilkSurum?->id))->not->toBeNull();
});

it('★★ BOS metin yayinlanamiyor ve SEBEBI yaziliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    sirketBilgileriniDoldur();

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/yasal/returns', ['icerik' => ''])
        ->assertRedirect();

    /*
    | ⚠️ 500 DEĞİL: boş metni yayınlamaya çalışmak markanın hatası değil,
    | sıradan bir sonuç. Sebep sayfada yazıyor.
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/yasal/returns/yayinla')
        ->assertRedirect()
        ->assertSessionHas('hata');
});

it('★ YAYINLANMAMIS DEGISIKLIK uyarisi gorunuyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/yasal/returns', ['icerik' => 'degistirdim ama yayinlamadim'])
        ->assertRedirect();

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/yasal')->getContent(),
    );

    $iade = null;

    foreach ($sayfa['props']['belgeler'] as $b) {
        if ($b['tur'] === 'returns') {
            $iade = $b;
        }
    }

    /*
    | ⚠️ Marka değişikliğini yayınladığını sanmasın: taslak yayındakinden
    | farklıysa bu AYRI bir uyarı.
    */
    expect($iade['yayinlanmamis_degisiklik'])->toBeTrue();
});
