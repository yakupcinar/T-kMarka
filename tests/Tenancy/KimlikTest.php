<?php

use App\Domain\Identity\EmailNormalizer;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| 1A.2'nin kanıtı. Şimdiye kadar bu uçları elle (curl) doğruladık — tek
| seferlik bir kanıt. Buradan sonra bilgisayar her değişiklikte kontrol edecek.
|
| Testler kiracı paketinde çünkü marka alan adına istek atıyorlar.
*/

// ─────────────────────────────────────────────────────────── MÜŞTERİ · KAYIT

it('musteri kayit olabiliyor ve token aliyor', function () {
    kiraciOlustur('marka-a.test');

    $cevap = $this->postJson('http://marka-a.test/api/register', [
        'name' => 'Ali Veli',
        'email' => 'ali@site.test',
        'password' => 'sifre1234',
    ]);

    $cevap->assertCreated()
        ->assertJsonPath('customer.email', 'ali@site.test')
        ->assertJsonStructure(['customer' => ['uuid', 'name', 'email'], 'token']);

    // Parola hiçbir koşulda cevaba çıkmamalı.
    expect($cevap->json())->not->toHaveKey('customer.password');
});

it('kayitta e-posta kucultuluyor ve kirpiliyor', function () {
    kiraciOlustur('marka-a.test');

    $this->postJson('http://marka-a.test/api/register', [
        'name' => 'Ali',
        'email' => '  Ali@Site.TEST  ',
        'password' => 'sifre1234',
    ])->assertCreated()->assertJsonPath('customer.email', 'ali@site.test');
});

it('ayni e-posta ile ikinci kayit reddediliyor BUYUK harfle de', function () {
    kiraciOlustur('marka-a.test');
    $veri = ['name' => 'Ali', 'email' => 'ali@site.test', 'password' => 'sifre1234'];

    $this->postJson('http://marka-a.test/api/register', $veri)->assertCreated();

    // ⚠️ BÜYÜK harfli — prepareForValidation() küçültmeseydi `unique` kuralı
    // bulamaz, geçer, sonra veritabanı kısıtı patlar ve kullanıcı 500 görürdü.
    $this->postJson('http://marka-a.test/api/register', [...$veri, 'email' => 'ALI@SITE.TEST'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('eksik alanla kayit reddediliyor', function () {
    kiraciOlustur('marka-a.test');

    $this->postJson('http://marka-a.test/api/register', ['email' => 'gecersiz'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

// ────────────────────────────────────────────────────────── MÜŞTERİ · GİRİŞ

it('musteri dogru parolayla giris yapabiliyor', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));
    Customer::factory()->create(['email' => 'ali@site.test', 'password' => 'sifre1234']);

    $this->postJson('http://marka-a.test/api/login', [
        'email' => 'ALI@Site.test',   // büyük harfle de olmalı
        'password' => 'sifre1234',
    ])->assertOk()->assertJsonStructure(['customer', 'token']);
});

it('yanlis parola ve olmayan hesap AYNI mesaji donuyor', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));
    Customer::factory()->create(['email' => 'ali@site.test', 'password' => 'sifre1234']);

    $yanlisParola = $this->postJson('http://marka-a.test/api/login', [
        'email' => 'ali@site.test', 'password' => 'bambaska',
    ])->assertStatus(422);

    $olmayanHesap = $this->postJson('http://marka-a.test/api/login', [
        'email' => 'hicyok@site.test', 'password' => 'sifre1234',
    ])->assertStatus(422);

    /*
    | ⚠️ İkisi AYNI mesajı vermeli. Farklı verselerdi saldırgan hangi
    | e-postaların kayıtlı olduğunu tek tek öğrenebilirdi (hesap sayımı).
    */
    expect($yanlisParola->json('errors.email'))
        ->toBe($olmayanHesap->json('errors.email'));
});

it('misafir musteri giris yapamiyor', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));

    // Misafirin parolası NULL — hiçbir parolayla eşleşmemeli.
    Customer::factory()->misafir()->create(['email' => 'misafir@site.test']);

    $this->postJson('http://marka-a.test/api/login', [
        'email' => 'misafir@site.test', 'password' => 'sifre1234',
    ])->assertStatus(422);
});

// ──────────────────────────────────────────────────── MÜŞTERİ · TOKEN AKIŞI

it('me ucu tokensiz 401 doner', function () {
    kiraciOlustur('marka-a.test');

    $this->getJson('http://marka-a.test/api/me')->assertUnauthorized();
});

it('cikis sonrasi ayni token gecersiz oluyor', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));
    Customer::factory()->create(['email' => 'ali@site.test', 'password' => 'sifre1234']);

    $token = $this->postJson('http://marka-a.test/api/login', [
        'email' => 'ali@site.test', 'password' => 'sifre1234',
    ])->json('token');

    $basliklar = ['Authorization' => "Bearer {$token}"];

    $this->getJson('http://marka-a.test/api/me', $basliklar)->assertOk();

    guardOnbelleginiTemizle();
    $this->postJson('http://marka-a.test/api/logout', [], $basliklar)->assertOk();

    guardOnbelleginiTemizle();
    $this->getJson('http://marka-a.test/api/me', $basliklar)->assertUnauthorized();
});

// ─────────────────────────────────────────────────────────────────── PERSONEL

it('personel panele giris yapabiliyor', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));
    User::factory()->sahip()->create(['email' => 'sahip@marka.test', 'password' => 'panel1234']);

    $this->postJson('http://marka-a.test/panel/login', [
        'email' => 'sahip@marka.test', 'password' => 'panel1234',
    ])->assertOk()->assertJsonPath('user.is_owner', true);
});

it('panelde kayit ucu YOK', function () {
    kiraciOlustur('marka-a.test');

    // Personel davetle gelir (1A.3). Kayıt ucu olsaydı markanın alan adını
    // bilen herkes panele hesap açabilirdi.
    $this->postJson('http://marka-a.test/panel/register', [])->assertNotFound();
});

it('silinmis personel giris yapamiyor', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));
    $personel = User::factory()->create(['email' => 'ayrilan@marka.test', 'password' => 'panel1234']);
    $personel->delete();   // soft delete

    $this->postJson('http://marka-a.test/panel/login', [
        'email' => 'ayrilan@marka.test', 'password' => 'panel1234',
    ])->assertStatus(422);
});

// ────────────────────────────────────────────── ★ ÇAPRAZ ERİŞİM (kritik test)

it('MUSTERI tokeni panel ucuna GIREMIYOR', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));
    Customer::factory()->create(['email' => 'ali@site.test', 'password' => 'sifre1234']);

    $musteriToken = $this->postJson('http://marka-a.test/api/login', [
        'email' => 'ali@site.test', 'password' => 'sifre1234',
    ])->json('token');

    guardOnbelleginiTemizle();

    $this->getJson('http://marka-a.test/panel/me', ['Authorization' => "Bearer {$musteriToken}"])
        ->assertUnauthorized();
});

it('PERSONEL tokeni vitrin ucuna GIREMIYOR', function () {
    tenancy()->initialize(kiraciOlustur('marka-a.test'));
    User::factory()->create(['email' => 'sahip@marka.test', 'password' => 'panel1234']);

    $personelToken = $this->postJson('http://marka-a.test/panel/login', [
        'email' => 'sahip@marka.test', 'password' => 'panel1234',
    ])->json('token');

    guardOnbelleginiTemizle();

    $this->getJson('http://marka-a.test/api/me', ['Authorization' => "Bearer {$personelToken}"])
        ->assertUnauthorized();
});

// ─────────────────────────────────────── ★ KİRACILIK × KİMLİK kesişimi

it('A markasinin musterisi B markasinda giris YAPAMIYOR', function () {
    kiraciOlustur('marka-a.test');
    kiraciOlustur('marka-b.test');

    // Hesap yalnızca A markasında açılıyor.
    $this->postJson('http://marka-a.test/api/register', [
        'name' => 'Ali', 'email' => 'ali@site.test', 'password' => 'sifre1234',
    ])->assertCreated();

    // Aynı bilgilerle B markasında giriş denemesi.
    // B'nin şemasında böyle bir müşteri yok — şemalar ayrı (M-2).
    $this->postJson('http://marka-b.test/api/login', [
        'email' => 'ali@site.test', 'password' => 'sifre1234',
    ])->assertStatus(422);
});

it('A markasinin tokeni B markasinda gecersiz', function () {
    kiraciOlustur('marka-a.test');
    kiraciOlustur('marka-b.test');

    $token = $this->postJson('http://marka-a.test/api/register', [
        'name' => 'Ali', 'email' => 'ali@site.test', 'password' => 'sifre1234',
    ])->json('token');

    $basliklar = ['Authorization' => "Bearer {$token}"];

    guardOnbelleginiTemizle();
    $this->getJson('http://marka-a.test/api/me', $basliklar)->assertOk();

    // personal_access_tokens tablosu da marka şemasında (1A.2) — B'de bu
    // token'ın kaydı bile yok.
    guardOnbelleginiTemizle();
    $this->getJson('http://marka-b.test/api/me', $basliklar)->assertUnauthorized();
});

/*
| TÜRKÇE BÜYÜK İ TUZAĞI — 1B için ölçüm yapılırken bulundu.
|
| mb_strtolower('İSMAIL@x.com') → 'i̇smail@x.com' (i + AYRI birleşen nokta)
| PostgreSQL lower()            → 'ismail@x.com'  (düz i)
| İki dizgi FARKLI; CHECK kısıtı da benzersiz indeks de yakalamıyordu.
*/

it('Türkçe büyük İ ile yazılan e-posta AYNI hesaba düşüyor', function () {
    markaKur('eposta-a.test');

    $musteri = Customer::factory()->create(['email' => 'ismail@ornek.test', 'password' => 'sifre1234']);

    // Kullanıcı Türkçe klavyeyle büyük yazdı. Düzeltilmeseydi eşleşme
    // bulunamaz, "parola yanlış" derdik ve hesabı dururken kilitlenirdi.
    guardOnbelleginiTemizle();
    $this->postJson('http://eposta-a.test/api/login', [
        'email' => 'İSMAIL@ornek.test',
        'password' => 'sifre1234',
    ])->assertOk()->assertJsonPath('customer.uuid', $musteri->uuid);
});

it('Türkçe büyük İ ile İKİNCİ hesap açılamıyor', function () {
    markaKur('eposta-b.test');

    Customer::factory()->create(['email' => 'ismail@ornek.test']);

    // Düzeltmeden önce burası 201 dönüyordu: iki ayrı müşteri, aynı kişi.
    guardOnbelleginiTemizle();
    $this->postJson('http://eposta-b.test/api/register', [
        'name' => 'İsmail',
        'email' => 'İSMAIL@ornek.test',
        'password' => 'sifre1234',
        'password_confirmation' => 'sifre1234',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('e-posta normalleştirme PostgreSQL lower ile aynı sonucu veriyor', function () {
    markaKur('eposta-c.test');

    foreach (['İSMAIL@ornek.test', 'ISMAIL@ornek.test', 'Işık@ornek.test', 'ŞÜKRÜ@ornek.test'] as $ham) {
        $php = EmailNormalizer::normallestir($ham);
        $pg = DB::selectOne('SELECT lower(?) AS v', [$ham])->v;

        expect($php)->toBe($pg, "uyuşmazlık: {$ham}");
    }
});
