<?php

use App\Domain\Identity\RoleService;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Platform\Domains\DnsChecker;
use App\Platform\Domains\FakeDnsChecker;
use App\Platform\Models\Domain;

/*
| PANEL: PERSONEL/ROLLER VE ÖZEL ALAN ADI (4.5C)
|
| ★ İkisi de Faz 4'ün boşluklarıydı:
|   · marka personel EKLEYEMİYORDU (uçları 1A'da)
|   · marka DNS talimatını HİÇ GÖREMİYORDU (uçları 3H'de)
*/

beforeEach(function () {
    $this->withoutVite();

    /*
    | ⚠️ GERÇEK DNS SORGUSU TESTTE YAPILMAZ — ölçüldü: doğrulama testi
    | 24 SANİYE sürüyordu çünkü `SystemDnsChecker` ağa çıkıp zaman
    | aşımını bekliyordu.
    |
    | Bundan kötüsü: test AĞA BAĞIMLI olurdu. Ağ yavaşsa yavaş, ağ
    | yoksa kırık — ve ölçtüğü şey bizim kodumuz değil internet olurdu.
    | 3H'de bu yüzden [FakeDnsChecker] yazılmıştı.
    */
    app()->bind(DnsChecker::class, FakeDnsChecker::class);
});

it('★ personel ekrani personeli ve rolleri gosteriyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/personel')->getContent(),
    );

    expect($sayfa['component'])->toBe('Personel')
        ->and($sayfa['props']['personel'])->not->toBeEmpty()
        ->and($sayfa['props']['roller'])->not->toBeEmpty()
        ->and($sayfa['props']['izinler'])->toHaveCount(count(Permission::cases()));

    // ⚠️ Sahip bayrağı görünmeli: çıkarılamıyor ve sebebi ekranda olmalı.
    expect($sayfa['props']['personel'][0]['is_owner'])->toBeTrue();
});

it('★ panelden PERSONEL EKLENEBILIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/personel', [
            'name' => 'Depo Sorumlusu',
            'email' => 'depo@marka-a.test',
            'password' => 'sifre1234',
            'roles' => ['Katalog'],
        ])->assertRedirect();

    $eklenen = User::where('email', 'depo@marka-a.test')->first();

    expect($eklenen)->toBeInstanceOf(User::class);

    /** @var User $eklenen */
    expect($eklenen->roles->pluck('name')->all())->toBe(['Katalog']);
});

it('★★ SAHIP cikarilamiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /*
    | ⚠️ Kural SERVİSTE (1A.3), controller'da tekrarlanmıyor. Tekrarlansaydı
    | iki yerden biri güncellenmeden kalır ve panelden yapılabilen bir şey
    | API'den yapılamaz (ya da tersi) olurdu.
    */
    /*
    | ⚠️ Adres `uuid` ile: `User::getRouteKeyName()` uuid döndürüyor.
    | `id` yazıldığında rota eşleşmiyor ve 404 geliyor — koruma değil
    | KAZA sonucu bir engel, yani ölçtüğü şey yanlış olurdu.
    */
    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/personel/{$sahip->uuid}")
        ->assertStatus(302)
        ->assertSessionHasErrors();

    expect(User::find($sahip->id))->not->toBeNull();
});

it('★★ KULLANIMDAKI ROL silinemiyor ve SEBEBI yaziliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $rol = app(RoleService::class)->olustur('Gecici', [Permission::OrderView->value]);

    $personel = User::factory()->create(['email' => 'p@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    /*
    | ⚠️ Silinseydi o roldeki personel SESSİZCE yetkisiz kalırdı (1A.6).
    | 500 değil, ekranda sebep.
    */
    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/roller/{$rol->id}")
        ->assertRedirect()
        ->assertSessionHas('hata');

    expect(Role::find($rol->id))->not->toBeNull();
});

it('★★ SISTEM ROLU SILINEMIYOR — ama adi degistirilebiliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /** @var Role $sistemRolu */
    $sistemRolu = Role::where('is_system', true)->firstOrFail();

    /*
    | ★ TESTİ YAZARKEN YANLIŞ VARSAYDIM: "sistem rolü değiştirilemez"
    | diye ölçtüm ve düştü. Kod haklıydı — 1A.6 yalnızca SİLMEYİ
    | kilitliyor, adı ve izinleri değiştirilebiliyor.
    |
    | ⚠️ Bu bilinçli bir sınır: markanın "Yönetici" rolüne kendi
    | adlandırmasını vermesi meşru bir istek. Silinmesi değil — silinseydi
    | o roldeki personel sessizce yetkisiz kalırdı.
    */
    $this->actingAs($sahip, 'staff-web')
        ->put("http://marka-a.test/yonetim/roller/{$sistemRolu->id}", [
            'name' => 'Baş Yönetici',
            'permissions' => [Permission::OrderView->value],
        ])->assertRedirect();

    expect($sistemRolu->refresh()->name)->toBe('Baş Yönetici');

    // SİLME ise kilitli.
    $this->actingAs($sahip, 'staff-web')
        ->delete("http://marka-a.test/yonetim/roller/{$sistemRolu->id}")
        ->assertRedirect()
        ->assertSessionHas('hata');

    expect(Role::find($sistemRolu->id))->not->toBeNull();
});

it('★★ IZINSIZ personel PERSONEL ekranina GIREMIYOR', function () {
    markaKur('marka-a.test');

    $rol = app(RoleService::class)->olustur('Depocu', [Permission::OrderView->value]);
    $personel = User::factory()->create(['email' => 'depo2@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    /*
    | ★ `staff.manage` SİSTEMDEKİ EN TEHLİKELİ izin: yetki dağıtma
    | yetkisi. Menüde madde gizleniyor ama koruma burada.
    */
    $this->actingAs($personel->refresh(), 'staff-web')
        ->get('http://marka-a.test/yonetim/personel')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| ÖZEL ALAN ADI — 3H'nin ekranı
|--------------------------------------------------------------------------
*/

it('★★ ALAN ADI ekraninda DNS TALIMATI gorunuyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/alan-adlari', ['domain' => 'magazam.example'])
        ->assertRedirect();

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/alan-adlari')->getContent(),
    );

    $yeni = null;

    foreach ($sayfa['props']['alanAdlari'] as $d) {
        if ($d['domain'] === 'magazam.example') {
            $yeni = $d;
        }
    }

    /*
    | ★ 3H'nin eksik parçası buydu: talimat uçta vardı ama EKRAN YOKTU,
    | yani marka ne yapacağını hiç göremiyordu. Bu adım İNSAN İŞİ ve
    | destek yükünün tamamı orada.
    |
    | ⚠️ ÜÇ SEÇENEK birden veriliyor — marka sağlayıcısının izin verdiğini
    | kullanabilsin (kök alan adında CNAME yasak olabiliyor).
    */
    expect($yeni)->not->toBeNull()
        ->and($yeni['dogrulandi'])->toBeFalse()
        ->and($yeni['talimat'])->toHaveKeys(['cname', 'a', 'txt']);
});

it('★ DOGRULANMIS alan adinda talimat GOSTERILMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/alan-adlari')->getContent(),
    );

    // Markanın kendi alan adı doğrulanmış doğuyor (3H).
    expect($sayfa['props']['alanAdlari'][0]['dogrulandi'])->toBeTrue()
        ->and($sayfa['props']['alanAdlari'][0]['talimat'])->toBeNull();
});

it('★★ MERKEZ alan adi eklenemiyor ve SEBEBI yaziliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /*
    | ⚠️ Alınabilseydi marka kendi paneline `localhost` yazar ve kapı
    | görevlisi MERKEZ isteklerini o markaya yönlendirirdi — kontrol
    | düzlemimizi kaybederdik (3H).
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/alan-adlari', ['domain' => '127.0.0.1'])
        ->assertRedirect()
        ->assertSessionHas('hata');
});

it('★★ SON ALAN ADI silinemiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /*
    | ⚠️ Silinseydi markaya hiçbir adresten ulaşılamaz, PANELİNE DE
    | giremezdi (3H).
    */
    $this->actingAs($sahip, 'staff-web')
        ->delete('http://marka-a.test/yonetim/alan-adlari/marka-a.test')
        ->assertRedirect()
        ->assertSessionHas('hata');

    expect(Domain::where('domain', 'marka-a.test')->exists())->toBeTrue();
});

it('★★ BASKA MARKANIN alan adi bu panelden silinemiyor', function () {
    markaKur('marka-b.test');
    tenancy()->end();

    ['sahip' => $sahipA] = markaKur('marka-a.test');

    // A markasının paneline ikinci bir alan adı ekle (son-alan-adı kuralı devre dışı).
    $this->actingAs($sahipA, 'staff-web')
        ->post('http://marka-a.test/yonetim/alan-adlari', ['domain' => 'ikinci.example'])
        ->assertRedirect();

    /*
    | ⚠️ 1A.5 deseni: kayıt MARKAYA DARALTILMIŞ sorgudan çözülüyor,
    | başka markanın alan adı sonuç kümesine hiç girmiyor → 404.
    */
    $this->actingAs($sahipA, 'staff-web')
        ->delete('http://marka-a.test/yonetim/alan-adlari/marka-b.test')
        ->assertNotFound();

    expect(Domain::where('domain', 'marka-b.test')->exists())->toBeTrue();
});

it('★ DOGRULAMA basarisiz olunca SEBEP yaziliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/alan-adlari', ['domain' => 'dogrulanmayan.example'])
        ->assertRedirect();

    /*
    | ⚠️ Sessizce sayfayı yenilemek "ekledim ama olmuyor" çağrısı demek
    | (3H). Başarısızlık da AÇIKÇA bildiriliyor.
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/alan-adlari/dogrulanmayan.example/dogrula')
        ->assertRedirect()
        ->assertSessionHas('hata');
});
