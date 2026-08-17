<?php

use App\Domain\Settings\StorePublication;
use App\Enums\TenantStatus;
use App\Models\User;
use App\Platform\DomainUnavailableException;
use App\Platform\Models\Tenant;
use App\Platform\ReservedSubdomains;
use App\Platform\TenantProvisioning;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
| Self-servis marka açma (3D).
|
| ★ DÖRT İDDİA:
|   1  ziyaretçi kendi markasını açabiliyor, KENDİ parolasıyla
|   2  ayrılmış alt alan adları alınamıyor (panel, admin, api…)
|   3  haftalık TAVAN gürültülü — sessizce kırık marka üretilmiyor
|   4  kurulum yarıda kalırsa ARKASI TOPLANIYOR
*/

it('★ ZİYARETÇİ marka açabiliyor — kendi parolasıyla', function () {
    tenancy()->end();

    $cevap = $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Ayşe’nin Butiği',
        'email' => 'ayse@ornek.test',
        'password' => 'guclu-parola-123',
    ])->assertCreated();

    /*
    | ⚠️ Adres CEVAPTA dönüyor: kullanıcı nereye gideceğini bilmeli.
    | Dönmeseydi kayıt "başarılı" der, müşteri mağazasını bulamazdı.
    |
    | ⚠️ Türkçe karakterler ölçüldü: `Ayşe’nin Butiği` → `aysenin-butigi`.
    */
    expect($cevap->json('domain'))->toBe('aysenin-butigi.localhost')
        ->and($cevap->json('tenant.status'))->toBe('trial')
        ->and($cevap->json('tenant.trial_ends_at'))->not->toBeNull();

    /*
    | ⚠️ `@var` şart: statik analiz `findOrFail()` dönüşünü
    | `Tenant|TenantCollection` olarak çıkarıyor ve `run()`'ı bulamıyor.
    */
    /** @var Tenant $marka */
    $marka = Tenant::findOrFail($cevap->json('tenant.id'));

    /*
    | ★ SAHİP KENDİ PAROLASINI belirledi — `tenant:create`'teki `123`
    | varsayılanı burada YOK. Olsaydı internetten açılan her marka aynı
    | bilinen parolayla doğardı.
    */
    $marka->run(function (): void {
        $sahip = User::where('email', 'ayse@ornek.test')->firstOrFail();

        expect($sahip->is_owner)->toBeTrue()
            ->and(Hash::check('guclu-parola-123', $sahip->password))->toBeTrue();
    });

    $marka->delete();
});

it('★ MAĞAZA KAPALI doğuyor ve bu SÖYLENİYOR', function () {
    tenancy()->end();

    $cevap = $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Kapalı Doğan',
        'email' => 'kapali@ornek.test',
        'password' => 'guclu-parola-123',
    ])->assertCreated();

    /*
    | ⚠️ Söylenmeseydi marka vitrinine bakar, 503 görür ve bozuk sanardı
    | (1A.4'ün kararı).
    */
    expect($cevap->json('message'))->toContain('kapalı');

    /*
    | ⚠️ `@var` şart: statik analiz `findOrFail()` dönüşünü
    | `Tenant|TenantCollection` olarak çıkarıyor ve `run()`'ı bulamıyor.
    */
    /** @var Tenant $marka */
    $marka = Tenant::findOrFail($cevap->json('tenant.id'));

    $marka->run(function (): void {
        expect(app(StorePublication::class)->yayindaMi())->toBeFalse();
    });

    $marka->delete();
});

it('★ AYRILMIŞ alt alan adı alınamıyor — kendi panelimizi kaybetmeyiz', function () {
    tenancy()->end();

    /*
    | ★ İKİ TEHLİKE: `panel` alınırsa kendi kontrol panelimizin adresini
    | kaybederiz; `www`/`mail` gibi adlar ise müşteriye "burası resmi
    | TıkMarka sayfası" hissi verir — oltalama için hazır zemin.
    */
    foreach (['panel', 'admin', 'api', 'www', 'odeme'] as $ayrilmis) {
        $this->postJson('http://localhost/platform/signup', [
            'brand_name' => 'Deneme',
            'email' => 'deneme@ornek.test',
            'password' => 'guclu-parola-123',
            'subdomain' => $ayrilmis,
        ])->assertStatus(422);
    }

    // ⚠️ Büyük harfle de kaçamıyor — karşılaştırma slug üzerinden.
    $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Deneme',
        'email' => 'deneme@ornek.test',
        'password' => 'guclu-parola-123',
        'subdomain' => 'PANEL',
    ])->assertStatus(422);

    expect(Tenant::where('name', 'Deneme')->count())->toBe(0);
});

it('★ ADI "Panel" olan marka SONEK alıyor — reddedilmiyor', function () {
    tenancy()->end();

    /*
    | ⚠️ Markanın adı gerçekten "Panel" olabilir. Ayrılmış olan `panel`
    | alt alan adı; markanın kendisi değil. Reddetseydik meşru bir
    | müşteriyi kapıda çevirirdik.
    */
    $kurulum = app(TenantProvisioning::class);

    expect($kurulum->altAlanAdiUret('Panel', 'localhost'))->toBe('panel-magaza.localhost');
});

it('★ AYNI ADDA iki marka — ikincisi SONEK alıyor', function () {
    tenancy()->end();

    $ilk = $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Çakışan Ad',
        'email' => 'ilk@ornek.test',
        'password' => 'guclu-parola-123',
    ])->assertCreated();

    $ikinci = $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Çakışan Ad',
        'email' => 'ikinci@ornek.test',
        'password' => 'guclu-parola-123',
    ])->assertCreated();

    /*
    | ⚠️ Sonek olmasaydı ikinci kayıt veritabanı kısıtına çarpar ve
    | kullanıcı 500 görürdü. Ayrıca farklı iki ad AYNI slug'a düşebiliyor
    | (`Işıl` ve `İsil` → `isil`), yani bu yalnızca birebir aynı adla
    | ilgili değil.
    */
    expect($ilk->json('domain'))->toBe('cakisan-ad.localhost')
        ->and($ikinci->json('domain'))->toBe('cakisan-ad-2.localhost');

    Tenant::whereIn('id', [$ilk->json('tenant.id'), $ikinci->json('tenant.id')])->get()->each->delete();
});

it('★ DOLU alan adı reddediliyor — 500 değil 422', function () {
    tenancy()->end();

    markaKur('dolu-adres.test');
    tenancy()->end();

    /*
    | ⚠️ Veritabanı UNIQUE kısıtına bırakılsaydı kullanıcı 500 görürdü.
    | Kontrol kısıtın YERİNE değil ÖNÜNDE.
    */
    expect(fn () => app(TenantProvisioning::class)->ac('X', 'dolu-adres.test', 'x@ornek.test', 'parola-123'))
        ->toThrow(DomainUnavailableException::class);
});

it('★ HAFTALIK TAVAN gürültülü — sessizce kırık marka açılmıyor', function () {
    tenancy()->end();

    /*
    | ★ 3-K5'in uygulaması. Let's Encrypt kayıtlı alan adı başına HAFTADA
    | 50 sertifika veriyor ve `*.tikmarka.com` altındaki her marka aynı
    | kotadan yiyor.
    |
    | ⚠️ Tavan olmasaydı marka açılır, panel çalışır ama SİTE AÇILMAZDI —
    | bugünkü Caddyfile tuzağının ölçekli hâli, tamamen sessiz.
    |
    | Tavanı doldurmak için sahte kayıt yazılıyor (şema açmadan, hızlı).
    */
    $idler = [];

    for ($i = 0; $i < TenantProvisioning::HAFTALIK_TAVAN; $i++) {
        $id = (string) Str::uuid();
        DB::connection('pgsql')->table('tenants')->insert([
            'id' => $id,
            'name' => 'Tavan '.$i,
            'status' => TenantStatus::Active->value,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idler[] = $id;
    }

    $cevap = $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Tavanı Aşan',
        'email' => 'tavan@ornek.test',
        'password' => 'guclu-parola-123',
    ])->assertStatus(503);

    /*
    | ⚠️ `Retry-After` başlığı: "bu geçici" demenin standart yolu (1A.4'te
    | mağaza kapalıyken de aynı desen). Kullanıcı ne zaman deneyeceğini
    | bilmeli.
    */
    expect($cevap->headers->get('Retry-After'))->not->toBeNull()
        ->and($cevap->json('message'))->toContain('sınır');

    DB::connection('pgsql')->table('tenants')->whereIn('id', $idler)->delete();
});

it('★ KURULUM YARIDA KALIRSA arkası TOPLANIYOR', function () {
    tenancy()->end();

    $once = Tenant::count();

    /*
    | ★ 1A.1'de GERÇEKTEN yaşandı: migration hata verdi, alan adı satırına
    | sıra gelmedi ve ortada şeması olan ama erişilemeyen bir marka kaldı.
    |
    | ⚠️ BU TEST BİR KIRMA DENEMESİNDEN DÜZELTİLDİ. Önce boş alan adıyla
    | yazılmıştı ve temizlik bloğu tamamen silindiğinde bile YEŞİL
    | kalıyordu — çünkü boş alan adı doğrulamada yakalanıyor, yani marka
    | HİÇ OLUŞMUYOR. Test "arkası toplandı"yı değil "hiç başlamadı"yı
    | ölçüyordu.
    |
    | Şimdi gerçek yarıda kalma taklit ediliyor: alan adı doğrulamadan
    | GEÇİYOR (boş değil, dolu değil, ayrılmış değil) ama veritabanına
    | yazılamıyor — kolon 255 karakter. Yani marka satırı ve şeması
    | oluştuktan SONRA patlıyor.
    */
    $cokUzun = str_repeat('a', 260).'.localhost';

    /*
    | ⚠️ Şema SAYISI karşılaştırılıyor, "hiç şema yok" DEĞİL. İlk yazımda
    | `count(...)->toBe(0)` yazılmıştı: tek başına koşarken geçiyor, TAM
    | SÜİTTE kırılıyordu çünkü başka testlerin şemaları da duruyor.
    */
    $semaSayisi = fn (): int => count(DB::connection('pgsql')->select(
        "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'tenant_%'"
    ));

    $semaOnce = $semaSayisi();

    /*
    | ⚠️ `toThrow(Throwable::class)` ÇALIŞMIYOR — Pest somut sınıf istiyor.
    | 2C'de aynı şeye takılmıştık.
    */
    expect(fn () => app(TenantProvisioning::class)->ac('Yarım Kalan', $cokUzun, 'x@ornek.test', 'parola-123'))
        ->toThrow(QueryException::class);

    /*
    | ⚠️ Hiçbir kalıntı YOK — ne merkez kayıt ne şema. Temizlik olmasaydı
    | şeması olan ama alan adı olmayan bir marka kalırdı: hiçbir adresten
    | erişilemez, üstelik sorun HTTP denenene kadar fark edilmez.
    */
    expect(Tenant::count())->toBe($once)
        ->and(Tenant::where('name', 'Yarım Kalan')->exists())->toBeFalse();

    expect($semaSayisi())->toBe($semaOnce);
});

it('★ ALT ALAN ADI MÜSAİT Mİ ucu sebebi de söylüyor', function () {
    tenancy()->end();

    $bos = $this->getJson('http://localhost/platform/signup/check?subdomain=bos-adres')->assertOk();

    expect($bos->json('available'))->toBeTrue()
        ->and($bos->json('reason'))->toBeNull();

    /*
    | ⚠️ "Kullanılamaz" demek yetmezdi: `panel` yazan biri neden
    | reddedildiğini anlamazdı. Sebep ayrı alan olarak dönüyor.
    */
    $ayrilmis = $this->getJson('http://localhost/platform/signup/check?subdomain=panel')->assertOk();

    expect($ayrilmis->json('available'))->toBeFalse()
        ->and($ayrilmis->json('reason'))->toBe('reserved');
});

it('★ ZAYIF PAROLA reddediliyor', function () {
    tenancy()->end();

    $this->postJson('http://localhost/platform/signup', [
        'brand_name' => 'Zayıf',
        'email' => 'zayif@ornek.test',
        'password' => '123',
    ])->assertStatus(422);
});

it('★ KOMUT ve KAYIT UCU aynı yolu kullanıyor — ayrışamazlar', function () {
    tenancy()->end();

    /*
    | ★ 1E.4'ün dersi: `markaKur` ile `tenant:create` ayrışmış ve testler
    | gerçekte var olmayan bir markayı ölçmüştü. İkisi de artık
    | [TenantProvisioning]'i çağırıyor.
    |
    | ⚠️ Bu test YAPI ölçüyor: komutun kurulumu kendi yazıp yazmadığını.
    */
    $kaynak = file_get_contents(base_path('app/Tenancy/Commands/CreateTenant.php'));

    expect($kaynak)->toContain('TenantProvisioning')
        ->and($kaynak)->not->toContain('DefaultRoles')
        ->and($kaynak)->not->toContain('DefaultSettings');
});

it('★ KOMUTLA açılan marka da DENEME durumunda', function () {
    tenancy()->end();

    $this->artisan('tenant:create', ['ad' => 'Komut Markası', 'alan-adi' => 'komut-marka.test'])
        ->assertExitCode(0);

    $marka = Tenant::where('name', 'Komut Markası')->firstOrFail();

    expect($marka->status)->toBe(TenantStatus::Trial)
        ->and($marka->trial_ends_at)->not->toBeNull();

    $marka->delete();
});

it('★ AYRILMIŞ LİSTE kendi panelimizi ve ödeme çağrışımlarını kapsıyor', function () {
    /*
    | ⚠️ Liste KAPALI: "şüpheliyse engelle" gibi bir kural yazılsaydı
    | hangi adın geçtiği tahmin edilemezdi.
    */
    expect(ReservedSubdomains::ayrilmisMi('panel'))->toBeTrue()
        ->and(ReservedSubdomains::ayrilmisMi('platform'))->toBeTrue()
        ->and(ReservedSubdomains::ayrilmisMi('odeme'))->toBeTrue()
        ->and(ReservedSubdomains::ayrilmisMi('secure'))->toBeTrue()
        ->and(ReservedSubdomains::ayrilmisMi('butik'))->toBeFalse();
});
