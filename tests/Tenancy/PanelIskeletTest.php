<?php

use App\Domain\Identity\RoleService;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/*
| PANEL İSKELETİ (4C) — Inertia + Vue, OTURUM tabanlı kimlik.
|
| ⚠️ `withoutVite()` HER TESTTE (4C-K2): kök görünüm `@vite` çağırıyor ve
| derlenmiş varlık yoksa istisna fırlatıyor. Testleri JS derlemesine
| bağlamak, süiti Node'a bağımlı yapardı. Derlemenin GERÇEKTEN çalıştığını
| CI ayrı bir adımda ölçüyor — burada değil.
*/

beforeEach(function () {
    $this->withoutVite();
});

it('★ giris sayfasi Inertia sayfasi donuyor', function () {
    markaKur('marka-a.test');

    $cevap = $this->get('http://marka-a.test/yonetim/giris')->assertOk();

    /*
    | ⚠️ Ham metinde aramak yerine `data-page` JSON'u ÇÖZÜLÜYOR. HTML
    | kaçışının biçimi Inertia sürümüne göre değişebiliyor; iddia
    | "hangi sayfa render edildi" olmalı, "hangi karakterlerle yazıldı"
    | değil.
    */
    expect(inertiaVerisi($cevap->getContent())['component'])->toBe('Giris');
});

it('★★ KIMLIKSIZ pano GIRIS SAYFASINA yonlendiriyor — 401 JSON degil', function () {
    markaKur('marka-a.test');

    /*
    | ⚠️ 2E'de ölçülen hatanın panel karşılığı: `ForceJson` global olsaydı
    | burada 401 JSON dönerdi ve tarayıcıdaki personel bomboş bir ekran
    | görürdü. 4A'da `api` grubuna daraltıldığı için giriş sayfasına
    | yönlendirme çalışıyor.
    */
    $this->get('http://marka-a.test/yonetim')
        ->assertRedirect('http://marka-a.test/yonetim/giris');
});

it('★★ OTURUMLA giris yapiliyor ve pano aciliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->post('http://marka-a.test/yonetim/giris', [
        'email' => $sahip->email,
        'password' => 'sifre1234',
    ])->assertRedirect('http://marka-a.test/yonetim');

    expect(Auth::guard('staff-web')->check())->toBeTrue();

    $this->get('http://marka-a.test/yonetim')
        ->assertOk();

    expect(inertiaVerisi($this->get('http://marka-a.test/yonetim')->getContent())['component'])
        ->toBe('Panosu');
});

it('★ YANLIS parola giris yapamiyor ve ayni mesaji veriyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /*
    | ⚠️ "Kullanıcı yok" ile "parola yanlış" AYNI mesajı veriyor; yoksa
    | hangi e-postaların panele erişimi olduğu tek tek öğrenilebilirdi.
    */
    $yanlisParola = $this->post('http://marka-a.test/yonetim/giris', [
        'email' => $sahip->email,
        'password' => 'bambaska',
    ])->assertSessionHasErrors('email');

    $olmayanHesap = $this->post('http://marka-a.test/yonetim/giris', [
        'email' => 'hicyok@marka-a.test',
        'password' => 'sifre1234',
    ])->assertSessionHasErrors('email');

    expect(session()->get('errors')->get('email'))->toBe(
        $yanlisParola->getSession()->get('errors')->get('email'),
    );

    expect(Auth::guard('staff-web')->check())->toBeFalse();
    expect($olmayanHesap)->not->toBeNull();
});

it('★ CIKIS oturumu kapatiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/cikis')
        ->assertRedirect('http://marka-a.test/yonetim/giris');

    expect(Auth::guard('staff-web')->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ★★ 4C-K4 — DÜĞMEYİ GİZLEMEK YETKİ DEĞİLDİR
|--------------------------------------------------------------------------
*/

it('★★ IZINLER prop olarak gidiyor — SAHIP hepsini goruyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $cevap = $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim');

    $sayfa = inertiaVerisi($cevap->getContent());

    /*
    | ⚠️ Sahibin ROLÜ boş olabilir ama `hasPermission` `is_owner` ile kısa
    | devre yapıyor. Prop yalnızca rollerden okunsaydı sahip, sunucunun
    | "yapabilirsin" dediği menüleri GÖREMEZDİ.
    */
    expect($sayfa['props']['auth']['permissions'])
        ->toHaveCount(count(Permission::cases()))
        ->and($sayfa['props']['auth']['user']['is_owner'])->toBeTrue();
});

it('★★ IZINSIZ personel prop olarak da IZIN GORMUYOR', function () {
    markaKur('marka-a.test');

    $rol = app(RoleService::class)->olustur('Depocu', [Permission::OrderView->value]);

    $personel = User::factory()->create(['email' => 'depo@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    $cevap = $this->actingAs($personel->refresh(), 'staff-web')->get('http://marka-a.test/yonetim');

    $sayfa = inertiaVerisi($cevap->getContent());

    expect($sayfa['props']['auth']['permissions'])->toBe([Permission::OrderView->value]);
});

it('★★ TARAYICI OTURUMU token ucunu ACMIYOR — guard ayrimi', function () {
    markaKur('marka-a.test');

    $rol = app(RoleService::class)->olustur('Depocu', [Permission::OrderView->value]);
    $personel = User::factory()->create(['email' => 'depo@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    /*
    | ★ 4C-K4'ÜN İLK YARISI: arayüzde menü gizlemek bir KOLAYLIK, koruma
    | sunucudadır. Bu test onun ölçülebilen bugünkü parçası — panelde
    | `izin:` korumalı bir SAYFA henüz yok (4D'de gelecek).
    |
    | ⚠️ Ölçtüğü şey GUARD AYRIMI: tarayıcıda oturum açmış olmak, token
    | tabanlı panel API'sini AÇMIYOR. Tek guard kullanılsaydı oturum
    | çerezi olan herkes API'ye de girerdi.
    |
    | ⚠️ Bu testin adı iddiasıyla hizalı tutulmalı: "yetki arayüzde
    | değil sunucuda" iddiasının tamamı 4D'de `izin:` korumalı panel
    | sayfasıyla ölçülecek.
    */
    $this->actingAs($personel->refresh(), 'staff-web')
        ->get('http://marka-a.test/panel/products', ['Accept' => 'application/json'])
        ->assertStatus(401);
});

it('★★ PAROLA HASHI prop olarak SIZMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $cevap = $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim');

    /*
    | ⚠️ Modeli olduğu gibi paylaşmak (`$kullanici->toArray()`) en kolay
    | yoldu ve `$hidden` listesine güvenirdi; alan alan yazmak yeni bir
    | sütun eklendiğinde onun kendiliğinden sızmamasını garantiliyor.
    */
    $cevap->assertDontSee('password');
    $cevap->assertDontSee($sahip->password);

    $sayfa = inertiaVerisi($cevap->getContent());
    expect($sayfa['props']['auth']['user'])->not->toHaveKey('password');
});

it('★ PANEL magaza KAPALIYKEN de aciliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /*
    | ⚠️ Mağaza varsayılan olarak kapalı doğuyor. Panel `magaza-acik`
    | kapısının DIŞINDA olmasaydı marka kendini dışarıda bırakırdı —
    | mağazayı tekrar açmanın tek yolu panel.
    */
    $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim')
        ->assertOk();
});

it('★ PANEL arama motoruna KAPALI', function () {
    markaKur('marka-a.test');

    $this->get('http://marka-a.test/yonetim/giris')
        ->assertSee('name="robots" content="noindex, nofollow"', escape: false);
});

it('★★ PANEL BETIGININ ADRESI kiraci yoluna yazilmiyor', function () {
    markaKur('marka-a.test');

    /*
    | ★ BU TESTİ GERÇEK TARAYICI DOĞURDU — süit göremezdi.
    |
    | `tenancy.features.asset_helper_tenancy` açıkken paket `asset()`
    | çağrılarını `/tenancy/assets/...` yoluna çeviriyor. Vite derlenmiş
    | panel paketini `asset()` ile adresliyor ve orada dosya YOK:
    |
    |   /tenancy/assets/build/assets/panel-*.js  → 404
    |   /build/assets/panel-*.js                 → 200
    |
    | ⚠️ Bedeli SESSİZDİ: sunucu 200 ve doğru HTML dönüyordu, testler
    | `withoutVite()` kullandığı için yeşildi — ama tarayıcı betiği
    | indiremediğinden panel BOŞ SAYFA açılıyordu.
    |
    | ⚠️ Marka dosyaları etkilenmiyor: onlar açıkça `tenant_asset()` ile
    | adresleniyor ([ProductImage::url]) ve o yardımcı bu ayardan bağımsız.
    */
    expect(asset('build/deneme.js'))->not->toContain('/tenancy/assets/');

    // Marka dosyalarının yolu ise kiracıya ÖZEL kalmalı.
    expect(tenant_asset('urun.jpg'))->toContain('/tenancy/assets/');
});
