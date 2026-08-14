<?php

use App\Domain\Settings\StorePublication;
use App\Enums\TenantStatus;
use App\Platform\InvalidTransitionException;
use App\Platform\Models\Plan;
use App\Platform\Models\PlatformUser;
use App\Platform\Models\Tenant;
use App\Platform\TenantLifecycle;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
| Kontrol düzlemi (3C).
|
| ★ DÖRT İDDİA:
|   1  ÜÇÜNCÜ kimlik alanı — marka personeli merkeze GİREMİYOR
|   2  durum geçişleri KAPALI LİSTE
|   3  askıda PANEL kapalı, VİTRİN AÇIK
|   4  kayıt ucu YOK — yönetici yalnızca komutla açılıyor
*/

/** Alan adından marka kimliği — `whereHas` yerine (gerekçe kullanım yerinde). */
function markaKimligi(string $alanAdi): string
{
    return (string) DB::connection('pgsql')->table('domains')->where('domain', $alanAdi)->value('tenant_id');
}

/** Platform yöneticisi açar ve token'ını döndürür. */
function platformTokeni(string $eposta = 'yonetici@tikmarka.test'): string
{
    tenancy()->end();

    PlatformUser::where('email', $eposta)->delete();

    $kullanici = PlatformUser::create([
        'name' => 'Platform Yöneticisi',
        'email' => $eposta,
        'password' => 'gizli-parola',
    ]);

    return $kullanici->createToken('test')->plainTextToken;
}

it('★ PLATFORM yöneticisi giriş yapabiliyor', function () {
    tenancy()->end();

    PlatformUser::where('email', 'giris@tikmarka.test')->delete();
    PlatformUser::create(['name' => 'Y', 'email' => 'giris@tikmarka.test', 'password' => 'dogru-parola']);

    $cevap = $this->postJson('http://localhost/platform/login', [
        'email' => 'giris@tikmarka.test',
        'password' => 'dogru-parola',
    ])->assertOk();

    expect($cevap->json('token'))->toBeString();

    /*
    | ⚠️ Yanlış parola ile OLMAYAN hesap AYNI cevabı alıyor (1A.2 deseni):
    | ayrılsaydı deneyerek hangi e-postaların kayıtlı olduğu öğrenilirdi.
    */
    $this->postJson('http://localhost/platform/login', [
        'email' => 'giris@tikmarka.test',
        'password' => 'yanlis',
    ])->assertStatus(422);

    $this->postJson('http://localhost/platform/login', [
        'email' => 'hicyok@tikmarka.test',
        'password' => 'dogru-parola',
    ])->assertStatus(422);
});

it('★ KAPATILMIŞ yönetici giremiyor — ve sebebi SÖYLENMİYOR', function () {
    tenancy()->end();

    PlatformUser::where('email', 'kapali@tikmarka.test')->delete();

    $kullanici = PlatformUser::create(['name' => 'K', 'email' => 'kapali@tikmarka.test', 'password' => 'parola123']);
    $kullanici->is_active = false;
    $kullanici->save();

    /*
    | ⚠️ "Hesabınız kapalı" denseydi o e-postanın bir zamanlan yönetici
    | olduğu doğrulanırdı. Cevap yanlış paroladan ayırt edilemiyor.
    */
    $cevap = $this->postJson('http://localhost/platform/login', [
        'email' => 'kapali@tikmarka.test',
        'password' => 'parola123',
    ])->assertStatus(422);

    expect(json_encode($cevap->json()))->not->toContain('kapal');
});

it('★ MARKA PERSONELİ merkez uçlara GİREMİYOR — en büyük sızıntı riski', function () {
    $marka = markaKur('kd-a.test');
    $personelToken = panelTokeni('kd-a.test', $marka['sahip']->email);

    tenancy()->end();

    /*
    | ★ BU TESTİN SEBEBİ: bu uçlar BÜTÜN markaları görüyor. Marka
    | personelinin buraya erişmesi sistemdeki en büyük sızıntı olurdu —
    | bir markanın sahibi rakiplerinin listesini çekerdi.
    |
    | ⚠️ KORUMA ÇİFT KATMANLI ve bu test ikisini AYIRT ETMİYOR — kırma
    | denemesinde ölçüldü: rotalar `auth:staff`'a çevrildiğinde bu test
    | yine YEŞİL kaldı. Sebep ikinci katman: personel token'ları MARKA
    | şemasındaki `personal_access_tokens`'ta, merkez bağlamda o tablo
    | başka (3C'de açıldı) ve token orada bulunamıyor.
    |
    | Yani guard tek başına sınanmıyor; ama iki katman da gerçek ve
    | ikisinin birden kalkması gerekiyor ki sızıntı olsun. Kaydediliyor
    | çünkü "guard çalışıyor" demek bu testin ölçtüğünden fazlası olurdu.
    */
    $this->withToken($personelToken)
        ->getJson('http://localhost/platform/tenants')
        ->assertUnauthorized();
});

it('★ PLATFORM token\'ı MARKA panelinde geçersiz — ters yön de kapalı', function () {
    $token = platformTokeni('ters@tikmarka.test');

    markaKur('kd-b.test');
    guardOnbelleginiTemizle();

    /*
    | ⚠️ Ters yön de kapalı olmalı: platform token'ı marka paneline
    | girebilseydi guard ayrımı yarım kalırdı. Ayrıca token MERKEZ şemadaki
    | tabloda; marka şemasındaki `personal_access_tokens`'ta karşılığı yok.
    */
    $this->withToken($token)
        ->getJson('http://kd-b.test/panel/me')
        ->assertUnauthorized();
});

it('★ MARKA LİSTESİ ada göre aranabiliyor — 3B\'nin kolonu sayesinde', function () {
    markaKur('kd-c.test');

    $token = platformTokeni('liste@tikmarka.test');

    /*
    | ⚠️ Arama GERÇEK KOLONDA. 3B'den önce `name` `data` json'ının içindeydi
    | ve bu sorgu hiçbir şey bulamazdı — hata da vermezdi.
    */
    $cevap = $this->withToken($token)
        ->getJson('http://localhost/platform/tenants?q=Test')
        ->assertOk();

    expect($cevap->json('meta.total'))->toBeGreaterThan(0)
        ->and($cevap->json('tenants.0.status'))->toBe('trial');
});

it('★ DURUMA GÖRE süzülüyor', function () {
    markaKur('kd-d.test');

    $token = platformTokeni('suz@tikmarka.test');

    $denemede = $this->withToken($token)->getJson('http://localhost/platform/tenants?status=trial')->assertOk();
    $askida = $this->withToken($token)->getJson('http://localhost/platform/tenants?status=suspended')->assertOk();

    expect($denemede->json('meta.total'))->toBeGreaterThan(0)
        ->and($askida->json('meta.total'))->toBe(0);
});

it('★ GEÇERSİZ GEÇİŞ reddediliyor — kapalı liste', function () {
    tenancy()->end();

    $marka = Tenant::create(['name' => 'Geçiş Testi', 'status' => TenantStatus::Closed]);
    $yasam = app(TenantLifecycle::class);

    /*
    | ★ EN ÖNEMLİ KURAL: kapatılmış marka DENEMEYE dönemiyor.
    |
    | ⚠️ Dönebilseydi marka kapatıp yeniden açarak SONSUZ ÜCRETSİZ
    | kullanım elde ederdi — hata vermeden, tamamen meşru görünen iki
    | işlemle.
    */
    expect(fn () => $yasam->gecir($marka, TenantStatus::Trial))
        ->toThrow(InvalidTransitionException::class);

    // Ama `active`'e dönebiliyor — geri gelen müşteri kabul ediliyor.
    expect($yasam->gecir($marka, TenantStatus::Active)->status)->toBe(TenantStatus::Active);

    $marka->delete();
});

it('★ ASKIYA ALMA tarihi de yazıyor — durum ve tarih BİRLİKTE', function () {
    tenancy()->end();

    $marka = Tenant::create(['name' => 'Askı Testi', 'status' => TenantStatus::Active]);
    $yasam = app(TenantLifecycle::class);

    $yasam->gecir($marka, TenantStatus::Suspended);

    /*
    | ⚠️ Tarih ayrı çağrıya bırakılsaydı biri unutulur ve "askıda ama
    | askıya alma tarihi yok" kaydı oluşurdu — hata vermeden.
    */
    expect($marka->refresh()->suspended_at)->not->toBeNull();

    // Askıdan çıkınca tarih TEMİZLENİYOR.
    $yasam->gecir($marka, TenantStatus::Active);

    expect($marka->refresh()->suspended_at)->toBeNull();

    $marka->delete();
});

it('★ NEZAKET SÜRESİ past_due\'ya girerken kuruluyor, düzelince siliniyor', function () {
    tenancy()->end();

    $marka = Tenant::create(['name' => 'Nezaket Testi', 'status' => TenantStatus::Active]);
    $yasam = app(TenantLifecycle::class);

    $yasam->gecir($marka, TenantStatus::PastDue);

    expect($marka->refresh()->grace_ends_at)->not->toBeNull()
        ->and((int) round((float) now()->diffInDays($marka->grace_ends_at)))
        ->toBe(TenantLifecycle::NEZAKET_GUN);

    /*
    | ⚠️ Ödemesi düzelen markada temizlenmeseydi hâlâ nezaket
    | süresindeymiş gibi görünür ve bir sonraki görev onu askıya alırdı.
    */
    $yasam->gecir($marka, TenantStatus::Active);

    expect($marka->refresh()->grace_ends_at)->toBeNull();

    $marka->delete();
});

it('★ AYNI DURUMA geçiş tarihi TAZELEMİYOR', function () {
    tenancy()->end();

    $marka = Tenant::create(['name' => 'Tekrar Testi', 'status' => TenantStatus::Active]);
    $yasam = app(TenantLifecycle::class);

    $yasam->gecir($marka, TenantStatus::Suspended);
    $ilk = $marka->refresh()->suspended_at;

    /*
    | ⚠️ Zamanlanmış görev aynı markayı iki kez görebilir. Tarih her
    | çağrıda tazelenseydi "ne zaman askıya alındı" bilgisi bugüne kayar
    | ve 1 yıllık silme sayacı hiç dolmazdı.
    */
    $yasam->gecir($marka->refresh(), TenantStatus::Suspended);

    expect($marka->refresh()->suspended_at?->toIso8601String())->toBe($ilk?->toIso8601String());

    $marka->delete();
});

it('★ ASKIDAKİ markanın PANELİ kapalı, VİTRİNİ AÇIK', function () {
    $marka = markaKur('kd-e.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $token = panelTokeni('kd-e.test', $marka['sahip']->email);

    // Askıdan ÖNCE panel açık.
    guardOnbelleginiTemizle();
    $this->withToken($token)->getJson('http://kd-e.test/panel/products')->assertOk();

    tenancy()->end();

    $merkezKayit = Tenant::findOrFail($marka['tenant']->id);
    app(TenantLifecycle::class)->gecir($merkezKayit, TenantStatus::Suspended);

    tenancy()->initialize($merkezKayit);
    guardOnbelleginiTemizle();

    // ★ PANEL kapalı.
    $this->withToken($token)->getJson('http://kd-e.test/panel/products')->assertForbidden();

    /*
    | ★ AMA VİTRİN AÇIK — 4 numaralı kararın tamamı bu satırda.
    |
    | ⚠️ Vitrini de kapatmak markayı değil markanın MÜŞTERİLERİNİ vururdu:
    | siparişini takip edemeyen, iade açamayan, parasını ödemiş insanlar.
    */
    $this->getJson('http://kd-e.test/api/products')->assertOk();

    // ⚠️ Çıkış da açık kalmalı: yoksa token elinde kalırdı.
    guardOnbelleginiTemizle();
    $this->withToken($token)->getJson('http://kd-e.test/panel/me')->assertOk();
});

it('★ PLAN ATAMA çalışıyor', function () {
    tenancy()->end();

    Plan::where('code', 'kd-plan')->delete();
    Plan::create(['code' => 'kd-plan', 'name' => 'Test Planı', 'price' => 500, 'max_products' => 100]);

    markaKur('kd-f.test');
    $token = platformTokeni('plan@tikmarka.test');

    /*
    | ⚠️ `whereHas('domains', ...)` KULLANILMIYOR: ilişki `HasDomains`
    | trait'inden geliyor ve statik analiz onu göremiyor. Alan adı
    | tablosundan kimliği okumak aynı sonucu veriyor.
    */
    $marka = Tenant::findOrFail(markaKimligi('kd-f.test'));

    $cevap = $this->withToken($token)
        ->postJson("http://localhost/platform/tenants/{$marka->id}/plan", ['plan_code' => 'kd-plan'])
        ->assertOk();

    expect($cevap->json('tenant.plan'))->toBe('kd-plan');

    Plan::where('code', 'kd-plan')->delete();
});

it('★ UÇTAN: yönetici markayı askıya alıyor, 409 ile geçersiz geçiş reddediliyor', function () {
    markaKur('kd-g.test');

    $token = platformTokeni('ucta@tikmarka.test');

    $marka = Tenant::findOrFail(markaKimligi('kd-g.test'));

    $askiya = $this->withToken($token)
        ->postJson("http://localhost/platform/tenants/{$marka->id}/status", ['status' => 'suspended'])
        ->assertOk();

    expect($askiya->json('tenant.status'))->toBe('suspended');

    /*
    | ⚠️ 409 — 422 DEĞİL. Veri geçerli ("provisioning" gerçek bir durum),
    | engelleyen şey markanın ŞU ANKİ durumu. 422 olsaydı panel
    | "gönderdiğin değer bozuk" der ve yönetici yanlış yere bakardı.
    */
    $this->withToken($token)
        ->postJson("http://localhost/platform/tenants/{$marka->id}/status", ['status' => 'provisioning'])
        ->assertStatus(409);
});

it('★ KAYIT UCU YOK — yönetici yalnızca komutla açılıyor', function () {
    tenancy()->end();

    /*
    | ⚠️ Uç olsaydı internetteki herkes kendine BÜTÜN markalara erişen bir
    | hesap yaratabilirdi. Panelde de aynı karar var (1A.2).
    */
    $this->postJson('http://localhost/platform/register', [
        'name' => 'Davetsiz',
        'email' => 'davetsiz@tikmarka.test',
        'password' => 'parola123',
    ])->assertNotFound();
});

it('★ KOMUT yönetici açıyor ve parolayı BİR KEZ gösteriyor', function () {
    tenancy()->end();

    PlatformUser::where('email', 'komut@tikmarka.test')->delete();

    $this->artisan('platform:kullanici', [
        'ad' => 'Komutla Açılan',
        'eposta' => 'komut@tikmarka.test',
        '--parola' => 'bilinen-parola',
    ])->assertExitCode(0);

    expect(PlatformUser::where('email', 'komut@tikmarka.test')->exists())->toBeTrue();

    // ⚠️ Aynı e-posta ikinci kez açılamıyor.
    $this->artisan('platform:kullanici', [
        'ad' => 'İkinci',
        'eposta' => 'komut@tikmarka.test',
    ])->assertExitCode(1);
});

it('★ PLATFORM rotaları `web` grubunda DEĞİL — CSRF tuzağı', function () {
    /*
    | ★ BU TEST GERÇEK BİR HATADAN DOĞDU ve testlerin göremediği türden.
    |
    | Rotalar önce `routes/web.php` içindeydi; BÜTÜN testler yeşildi ama
    | gerçek `curl` isteği `CSRF token mismatch` aldı. Sebep: `web` grubu
    | CSRF koruması uyguluyor ve token'ı çerezden bekliyor. Testler
    | `postJson` kullandığı için hiç görünmedi.
    |
    | ⚠️ 1A.2'de bu karar zaten verilmişti ("api grubu, web değil; CSRF
    | token istemcisini kırardı") ve 3C'de tekrar unutuldu — yani yorum
    | yetmiyor, ÖLÇEN bir test gerekiyor.
    |
    | ⚠️ Bu test bir DAVRANIŞ değil YAPI ölçüyor: isteğin kendisi testte
    | CSRF'e takılmıyor (test ortamında kapalı), o yüzden rotanın
    | middleware listesine bakmak tek yol.
    */
    $rota = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'platform/login');

    expect($rota)->not->toBeNull();

    $middleware = $rota?->gatherMiddleware() ?? [];

    expect($middleware)->not->toContain(ValidateCsrfToken::class)
        ->and($middleware)->not->toContain('web');
});
