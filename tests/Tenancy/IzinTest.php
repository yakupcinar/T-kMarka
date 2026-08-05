<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

/*
| 1A.3'ün kanıtı — izin sistemi ve personel yönetimi.
*/

// ────────────────────────────────────────────────────── İZİN MANTIĞI

it('sahip hicbir rolu olmasa da tum izinlere sahip', function () {
    ['sahip' => $sahip] = markaKur();

    expect($sahip->roles)->toHaveCount(0);

    foreach (Permission::cases() as $izin) {
        expect($sahip->hasPermission($izin))->toBeTrue();
    }
});

it('rolsuz personelin hicbir izni yok', function () {
    markaKur();
    $personel = User::factory()->create();

    foreach (Permission::cases() as $izin) {
        expect($personel->hasPermission($izin))->toBeFalse();
    }
});

it('personel yalnizca rolunun izinlerine sahip', function () {
    markaKur();
    $personel = User::factory()->create();
    $personel->roles()->attach(Role::where('name', 'Katalog')->value('id'));

    expect($personel->hasPermission(Permission::ProductWrite))->toBeTrue()
        ->and($personel->hasPermission(Permission::OrderRefund))->toBeFalse()
        ->and($personel->hasPermission(Permission::StaffManage))->toBeFalse();
});

it('siparis destek rolunde IADE izni YOK', function () {
    markaKur();
    $personel = User::factory()->create();
    $personel->roles()->attach(Role::where('name', 'Sipariş & Destek')->value('id'));

    // "Depocu siparişi görsün ama iade yapamasın" — domain-model §3'teki örnek.
    expect($personel->hasPermission(Permission::OrderView))->toBeTrue()
        ->and($personel->hasPermission(Permission::OrderFulfill))->toBeTrue()
        ->and($personel->hasPermission(Permission::OrderRefund))->toBeFalse();
});

it('varsayilan rollerin HICBIRINDE staff.manage yok', function () {
    markaKur();

    // Personel yönetimi yetki yükseltmeye en yakın işlem: yalnızca sahipte.
    foreach (Role::all() as $rol) {
        expect($rol->permissions())->not->toContain(Permission::StaffManage->value);
    }
});

// ────────────────────────────────────────────── PERSONEL YÖNETİMİ UÇLARI

it('sahip personel listesini gorebiliyor', function () {
    markaKur();
    $token = panelTokeni('marka-a.test', 'sahip@marka-a.test');

    $this->getJson('http://marka-a.test/panel/staff', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('staff.0.is_owner', true);
});

it('izinsiz personel listeye ERISEMIYOR', function () {
    markaKur();
    $depocu = User::factory()->create(['email' => 'depocu@marka.test', 'password' => 'sifre1234']);
    $depocu->roles()->attach(Role::where('name', 'Katalog')->value('id'));

    $token = panelTokeni('marka-a.test', 'depocu@marka.test');

    guardOnbelleginiTemizle();
    $this->getJson('http://marka-a.test/panel/staff', ['Authorization' => "Bearer {$token}"])
        ->assertForbidden();
});

it('sahip personel davet edebiliyor ve rol atanabiliyor', function () {
    markaKur();
    $token = panelTokeni('marka-a.test', 'sahip@marka-a.test');

    guardOnbelleginiTemizle();
    $this->postJson('http://marka-a.test/panel/staff', [
        'name' => 'Depocu Veli',
        'email' => 'depocu@marka.test',
        'password' => 'depo1234',
        'roles' => ['Katalog'],
    ], ['Authorization' => "Bearer {$token}"])
        ->assertCreated()
        ->assertJsonPath('user.roles', ['Katalog']);
});

it('olmayan rol ile davet reddediliyor', function () {
    markaKur();
    $token = panelTokeni('marka-a.test', 'sahip@marka-a.test');

    // Yazım hatası sessizce yok sayılsaydı rolsüz personel oluşur ve
    // neden hiçbir şey göremediği anlaşılmazdı.
    guardOnbelleginiTemizle();
    $this->postJson('http://marka-a.test/panel/staff', [
        'name' => 'X', 'email' => 'x@y.test', 'password' => 'sifre1234',
        'roles' => ['Kataloq'],
    ], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(422)
        ->assertJsonValidationErrors('roles.0');
});

// ──────────────────────────────────────────────────── EMNİYET KİLİTLERİ

it('marka sahibi CIKARILAMIYOR', function () {
    ['sahip' => $sahip] = markaKur();
    $token = panelTokeni('marka-a.test', 'sahip@marka-a.test');

    guardOnbelleginiTemizle();
    $this->deleteJson("http://marka-a.test/panel/staff/{$sahip->uuid}",
        [], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');

    expect(User::find($sahip->id))->not->toBeNull();
});

it('staff.manage izni olan biri KENDINI cikaramiyor', function () {
    markaKur();

    /*
    | Bu senaryo yalnızca marka KENDİ rolünü oluşturup ona staff.manage
    | verdiğinde mümkün: kişi sahip değil ama personel yönetebiliyor.
    | Varsayılan rollerle test edilemez — o yüzden özel rol kuruyoruz.
    */
    $rol = Role::create(['name' => 'İK']);
    $rol->syncPermissions([Permission::StaffManage]);

    $ik = User::factory()->create(['email' => 'ik@marka.test', 'password' => 'sifre1234']);
    $ik->roles()->attach($rol->id);

    $token = panelTokeni('marka-a.test', 'ik@marka.test');

    guardOnbelleginiTemizle();
    $this->deleteJson("http://marka-a.test/panel/staff/{$ik->uuid}",
        [], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

it('cikarilan personelin tokeni iptal ediliyor', function () {
    markaKur();
    $depocu = User::factory()->create(['email' => 'depocu@marka.test', 'password' => 'sifre1234']);

    $depocuToken = panelTokeni('marka-a.test', 'depocu@marka.test');
    $sahipToken = panelTokeni('marka-a.test', 'sahip@marka-a.test');

    guardOnbelleginiTemizle();
    $this->deleteJson("http://marka-a.test/panel/staff/{$depocu->uuid}",
        [], ['Authorization' => "Bearer {$sahipToken}"])->assertOk();

    // Çıkarılan personel elindeki token'la panele girmeye devam etmemeli.
    guardOnbelleginiTemizle();
    $this->getJson('http://marka-a.test/panel/me', ['Authorization' => "Bearer {$depocuToken}"])
        ->assertUnauthorized();
});

it('MUSTERI tokeni personel yonetimine giremiyor', function () {
    markaKur();

    $musteriToken = $this->postJson('http://marka-a.test/api/register', [
        'name' => 'Ali', 'email' => 'ali@site.test', 'password' => 'sifre1234',
    ])->json('token');

    guardOnbelleginiTemizle();
    $this->getJson('http://marka-a.test/panel/staff', ['Authorization' => "Bearer {$musteriToken}"])
        ->assertUnauthorized();
});
