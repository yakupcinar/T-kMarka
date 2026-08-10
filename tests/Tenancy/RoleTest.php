<?php

use App\Domain\Identity\RoleInUseException;
use App\Domain\Identity\RoleService;
use App\Domain\Identity\SystemRoleException;
use App\Models\Role;
use App\Models\User;

/*
| Rol yönetimi — kapı `is_owner`, izin DEĞİL.
|
| Katı rol listesi güvenlik üretmez, aşırı yetki üretir: "sadece finans"
| rolü yoksa marka muhasebecisine Yönetici verir. Marka kendi rolünü
| kurabiliyor, ama izinler sabit enum'dan seçiliyor.
*/

it('sahip rolleri listeliyor, izin seçeneklerini de alıyor', function () {
    $marka = markaKur('rol-a.test');
    $token = panelTokeni('rol-a.test', $marka['sahip']->email);

    $cevap = $this->withToken($token)->getJson('http://rol-a.test/panel/roles');

    $cevap->assertOk()
        // Üç sistem rolü (1A.3).
        ->assertJsonCount(3, 'roles')
        ->assertJsonCount(9, 'available_permissions');

    // Panel izin listesini koda gömmesin diye sunucudan alıyor.
    expect($cevap->json('roles.0.is_system'))->toBeTrue();
});

it('sahip yeni rol kuruyor', function () {
    $marka = markaKur('rol-b.test');
    $token = panelTokeni('rol-b.test', $marka['sahip']->email);

    $this->withToken($token)
        ->postJson('http://rol-b.test/panel/roles', [
            'name' => 'Muhasebe',
            'permissions' => ['finance.view'],
        ])
        ->assertStatus(201)
        ->assertJsonPath('role.name', 'Muhasebe')
        ->assertJsonPath('role.is_system', false)
        ->assertJsonPath('role.permissions', ['finance.view']);
});

it('tanımsız izin reddediliyor', function () {
    $marka = markaKur('rol-c.test');
    $token = panelTokeni('rol-c.test', $marka['sahip']->email);

    // Serbest metin kabul edilseydi kayıt başarılı olur, hiçbir kapı bu
    // izni sormaz ve rol sessizce yetkisiz kalırdı.
    $this->withToken($token)
        ->postJson('http://rol-c.test/panel/roles', [
            'name' => 'Uydurma',
            'permissions' => ['urun.duzenle'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['permissions.0']);
});

it('is_system istekle YAZILAMIYOR', function () {
    $marka = markaKur('rol-d.test');
    $token = panelTokeni('rol-d.test', $marka['sahip']->email);

    // Yazılabilseydi marka kendi rolünü "silinemez" ilan ederdi.
    $this->withToken($token)
        ->postJson('http://rol-d.test/panel/roles', [
            'name' => 'Sahte Sistem',
            'permissions' => [],
            'is_system' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('role.is_system', false);
});

it('aynı adda ikinci rol kurulamıyor', function () {
    $marka = markaKur('rol-e.test');
    $token = panelTokeni('rol-e.test', $marka['sahip']->email);

    // Personel ataması rol ADIYLA yapılıyor (1A.3); iki aynı ad
    // "hangisi" sorusunu doğururdu.
    $this->withToken($token)
        ->postJson('http://rol-e.test/panel/roles', ['name' => 'Katalog', 'permissions' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('sistem rolünün izinleri DÜZENLENEBİLİYOR', function () {
    $marka = markaKur('rol-f.test');
    $token = panelTokeni('rol-f.test', $marka['sahip']->email);
    $yonetici = Role::where('name', 'Yönetici')->firstOrFail();

    expect($yonetici->permissions())->toContain('finance.view');

    // "Yönetici'den finans iznini alayım" meşru bir istek. Yasaklasaydık
    // marka rolü kopyalar, sonuç aynı olur ama iki karışık rol kalırdı.
    $this->withToken($token)
        ->putJson("http://rol-f.test/panel/roles/{$yonetici->id}", [
            'name' => 'Yönetici',
            'permissions' => ['product.view', 'product.write', 'order.view'],
        ])
        ->assertOk();

    expect($yonetici->permissions())->not->toContain('finance.view');
});

it('sistem rolü SİLİNEMİYOR', function () {
    $marka = markaKur('rol-g.test');
    $token = panelTokeni('rol-g.test', $marka['sahip']->email);
    $katalog = Role::where('name', 'Katalog')->firstOrFail();

    // Silinebilseydi marka bütün rollerini silip personelini dışarıda
    // bırakabilirdi.
    $this->withToken($token)
        ->deleteJson("http://rol-g.test/panel/roles/{$katalog->id}")
        ->assertStatus(409);

    expect(Role::find($katalog->id))->not->toBeNull();
});

it('üzerinde personel olan rol SİLİNEMİYOR', function () {
    $marka = markaKur('rol-h.test');
    $token = panelTokeni('rol-h.test', $marka['sahip']->email);

    $rol = Role::create(['name' => 'Depo']);
    $personel = User::factory()->create(['email' => 'depo@rol-h.test']);
    $personel->roles()->sync([$rol->id]);

    // Sessizce çözülseydi personel bir sabah yetkisiz uyanır, kimse
    // sebebini bilmezdi.
    $this->withToken($token)
        ->deleteJson("http://rol-h.test/panel/roles/{$rol->id}")
        ->assertStatus(409)
        ->assertJsonPath('staff_count', 1);

    // Personel taşınınca silinebilmeli.
    $personel->roles()->sync([]);

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->deleteJson("http://rol-h.test/panel/roles/{$rol->id}")
        ->assertOk();

    expect(Role::find($rol->id))->toBeNull();
});

it('rol silinince izin satırları da gidiyor', function () {
    $marka = markaKur('rol-i.test');
    $token = panelTokeni('rol-i.test', $marka['sahip']->email);

    $cevap = $this->withToken($token)->postJson('http://rol-i.test/panel/roles', [
        'name' => 'Geçici',
        'permissions' => ['order.view', 'customer.view'],
    ]);
    $rolId = $cevap->json('role.id');

    guardOnbelleginiTemizle();
    $this->withToken($token)->deleteJson("http://rol-i.test/panel/roles/{$rolId}")->assertOk();

    // Öksüz izin satırı kalmamalı: aynı id yeniden kullanılırsa yeni rol
    // eski izinlerle doğardı.
    expect(DB::table('role_permissions')->where('role_id', $rolId)->count())->toBe(0);
});

it('SAHİP OLMAYAN personel rol yönetimine giremiyor', function () {
    markaKur('rol-j.test');

    // ⚠️ Yönetici rolünde settings.write DAHİL her şey var ama rol
    // yönetimi izinle korunmuyor — `is_owner` ile korunuyor.
    $personel = User::factory()->create(['email' => 'yonetici@rol-j.test']);
    $personel->roles()->sync(Role::where('name', 'Yönetici')->pluck('id'));

    $token = panelTokeni('rol-j.test', $personel->email);

    guardOnbelleginiTemizle();
    $this->withToken($token)->getJson('http://rol-j.test/panel/roles')->assertStatus(403);

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson('http://rol-j.test/panel/roles', ['name' => 'Yardımcı', 'permissions' => ['settings.write']])
        ->assertStatus(403);
});

it('müşteri token ı rol yönetimine giremiyor', function () {
    markaKur('rol-k.test');
    $m = musteriTokeni('rol-k.test', 'musteri@rol-k.test');

    guardOnbelleginiTemizle();
    $this->withToken($m['token'])->getJson('http://rol-k.test/panel/roles')->assertStatus(401);
});

/*
| Aşağıdaki iki test HTTP'den GEÇMİYOR — servisi doğrudan çağırıyor.
|
| Kurallar controller'da dururken bu testler yazılamazdı: bir artisan
| komutu, kuyruk işi ya da tohumlayıcı rol silseydi kurallar hiç
| çalışmazdı ve kimse hata almazdı. Bu iki test, taşımanın gerekçesi.
*/

it('sistem rolü kuralı HTTP olmadan da geçerli', function () {
    markaKur('rol-l.test');

    $katalog = Role::where('name', 'Katalog')->firstOrFail();

    expect(fn () => app(RoleService::class)->sil($katalog))
        ->toThrow(SystemRoleException::class);

    expect(Role::find($katalog->id))->not->toBeNull();
});

it('personelli rol kuralı HTTP olmadan da geçerli', function () {
    markaKur('rol-m.test');

    $rol = Role::create(['name' => 'Depo']);
    User::factory()->create(['email' => 'depo@rol-m.test'])->roles()->sync([$rol->id]);

    expect(fn () => app(RoleService::class)->sil($rol))
        ->toThrow(RoleInUseException::class);
});
