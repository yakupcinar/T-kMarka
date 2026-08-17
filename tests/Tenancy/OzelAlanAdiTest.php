<?php

use App\Platform\Domains\CustomDomainService;
use App\Platform\Domains\DnsChecker;
use App\Platform\Domains\FakeDnsChecker;
use App\Platform\DomainUnavailableException;
use App\Platform\Models\Domain;
use App\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;

/*
| Özel alan adı ve on-demand TLS (3H).
|
| ★ DÖRT İDDİA:
|   1  DOĞRULANMAMIŞ alan adı `ask` ucundan 404 alıyor
|   2  DNS'i marka ekliyor, biz kontrol ediyoruz
|   3  merkez alan adlarımız ve ayrılmış adlar ALINAMIYOR
|   4  son alan adı silinemiyor — marka erişilemez kalmasın
|
| ⚠️ 1 olmadan on-demand TLS AÇILAMAZ: doğrulanmamış her alan adı için
| sertifika istenir ve Let's Encrypt kotamız yanar (haftada 50).
*/

/** Testler için sahte DNS okuyucusu kurar. */
function sahteDns(): FakeDnsChecker
{
    $sahte = new FakeDnsChecker;

    app()->instance(DnsChecker::class, $sahte);

    return $sahte;
}

it('★ DOĞRULANMAMIŞ alan adı ask ucundan 404 alıyor — kota koruması', function () {
    $marka = markaKur('oad-a.test');
    tenancy()->end();

    sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);

    $ozel = app(CustomDomainService::class)->ekle($kayit, 'ozel-adres.example');

    /*
    | ★ BU TESTİN SEBEBİ: `ask` ucu Caddy'nin sertifika almadan önce
    | sorduğu yer. Doğrulanmamış alan adına 200 deseydik, marka paneline
    | `google.com` yazan biri yüzünden Caddy o alan adı için ACME
    | doğrulaması dener, düşer ve KOTAMIZ YANARDI — haftada 50 sertifika
    | (3-K5).
    */
    $this->get('http://localhost/tenancy/domain-check?domain=ozel-adres.example')
        ->assertNotFound();

    // Doğrulandıktan sonra 200.
    $ozel->verified_at = now();
    $ozel->save();

    $this->get('http://localhost/tenancy/domain-check?domain=ozel-adres.example')
        ->assertOk();
});

it('★ MEVCUT alan adları geriye dönük DOĞRULANDI', function () {
    markaKur('oad-b.test');
    tenancy()->end();

    /*
    | ⚠️ `verified_at` kolonu 3H'de SONRADAN eklendi (dördüncü kez: 2C ·
    | 2F · 3B). Doldurulmasaydı bugünkü markalar "doğrulanmamış" sayılır,
    | ask ucu onlara 404 döner ve on-demand TLS açıldığı an ÇALIŞAN SİTELER
    | sertifika alamaz hâle gelirdi.
    */
    /*
    | ⚠️ YALNIZCA `markaKur` ile açılan alan adına bakılıyor. Tüm tabloya
    | bakılsaydı test, başka testlerin eklediği DOĞRULANMAMIŞ özel alan
    | adlarını da sayar ve gerçek sebepten değil kalıntıdan kırmızı
    | kalırdı (3B ve 3F'de aynı kalıntı sorunu yaşandı).
    */
    $kayit = Domain::where('domain', 'oad-b.test')->firstOrFail();

    expect($kayit->verified_at)->not->toBeNull();

    $this->get('http://localhost/tenancy/domain-check?domain=oad-b.test')->assertOk();
});

it('★ DNS kaydı YOKSA doğrulama BAŞARISIZ — ve bu hata değil', function () {
    $marka = markaKur('oad-c.test');
    tenancy()->end();

    $dns = sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);
    $ozel = app(CustomDomainService::class)->ekle($kayit, 'kayitsiz.example');

    /*
    | ⚠️ Sahte DNS varsayılan olarak HİÇBİR KAYIT döndürmüyor. Boş sonuç
    | "doğrulandı" sayılsaydı testler doğrulamanın çalıştığını sanır,
    | gerçekte HER alan adı kabul edilirdi.
    */
    expect(app(CustomDomainService::class)->dogrula($ozel))->toBeFalse()
        ->and($ozel->refresh()->verified_at)->toBeNull();

    // Marka kaydı ekleyince doğrulanıyor.
    $dns->ayarla('kayitsiz.example', cname: [strtolower((string) config('tenancy.custom_domain_cname'))]);

    expect(app(CustomDomainService::class)->dogrula($ozel))->toBeTrue()
        ->and($ozel->refresh()->verified_at)->not->toBeNull();
});

it('★ ÜÇ YOLDAN BİRİ yeterli: CNAME · A · TXT', function () {
    $marka = markaKur('oad-d.test');
    tenancy()->end();

    $dns = sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);
    $servis = app(CustomDomainService::class);

    /*
    | ⚠️ Tek yol dayatılsaydı markaların bir kısmı alan adını hiç
    | bağlayamazdı: bazı sağlayıcılar KÖK alan adında CNAME'e izin
    | vermiyor, o zaman A ya da TXT tek çıkış.
    */
    $aIle = $servis->ekle($kayit, 'a-kaydi.example');
    $dns->ayarla('a-kaydi.example', a: [(string) config('tenancy.custom_domain_ip')]);

    expect($servis->dogrula($aIle))->toBeTrue();

    $txtIle = $servis->ekle($kayit, 'txt-kaydi.example');
    $dns->ayarla('txt-kaydi.example', txt: [(string) $txtIle->verification_token]);

    expect($servis->dogrula($txtIle))->toBeTrue();
});

it('★ BAŞKASININ belirteci işe yaramıyor', function () {
    $marka = markaKur('oad-e.test');
    tenancy()->end();

    $dns = sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);
    $servis = app(CustomDomainService::class);

    $birinci = $servis->ekle($kayit, 'birinci.example');
    $ikinci = $servis->ekle($kayit, 'ikinci.example');

    /*
    | ⚠️ Belirteç ALAN ADI BAŞINA rastgele. Sabit olsaydı bir markanın
    | belirtecini gören başkası kendi alan adını doğrulatabilirdi.
    */
    expect($birinci->verification_token)->not->toBe($ikinci->verification_token);

    // İkincinin belirteci birincinin DNS'inde — doğrulanmamalı.
    $dns->ayarla('birinci.example', txt: [(string) $ikinci->verification_token]);

    expect($servis->dogrula($birinci))->toBeFalse();
});

it('★ MERKEZ alan adımız alınamıyor — kontrol düzlemini kaybetmeyiz', function () {
    $marka = markaKur('oad-f.test');
    tenancy()->end();

    sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);

    /*
    | ★ Alınabilseydi marka kendi paneline `localhost` yazar, kapı
    | görevlisi merkez isteklerini o markaya yönlendirir ve KONTROL
    | DÜZLEMİMİZİ kaybederdik.
    */
    /*
    | ⚠️ `127.0.0.1` KULLANILIYOR, `localhost` DEĞİL — ve bunu bir kırma
    | denemesi ortaya çıkardı: merkez kontrolü tamamen kaldırıldığında test
    | YEŞİL kalıyordu, çünkü `localhost` nokta içermediği için zaten
    | "geçerli alan adı değil" diye eleniyordu. Yani test merkez korumasını
    | değil biçim kontrolünü ölçüyormuş.
    |
    | `127.0.0.1` nokta içeriyor ve biçim kontrolünden geçiyor — merkez
    | listesindeki gerçek koruma ancak böyle sınanıyor.
    */
    expect(fn () => app(CustomDomainService::class)->ekle($kayit, '127.0.0.1'))
        ->toThrow(DomainUnavailableException::class);

    // Biçimsiz ad da reddediliyor — ama AYRI sebeple.
    expect(fn () => app(CustomDomainService::class)->ekle($kayit, 'noktasizad'))
        ->toThrow(DomainUnavailableException::class);
});

it('★ AYRILMIŞ alt alan adı özel alan adı yoluyla da ALINAMIYOR', function () {
    $marka = markaKur('oad-g.test');
    tenancy()->end();

    sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);

    /*
    | ⚠️ Kendi kök alan adımızın ALTINA özel alan adı eklemek, 3D'deki
    | ayrılmış adlar listesini dolaşmanın ARKA KAPISI olurdu.
    */
    expect(fn () => app(CustomDomainService::class)->ekle($kayit, 'panel.localhost'))
        ->toThrow(DomainUnavailableException::class);
});

it('★ DOLU alan adı ikinci kez eklenemiyor', function () {
    $marka = markaKur('oad-h.test');
    tenancy()->end();

    sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);
    $servis = app(CustomDomainService::class);

    $servis->ekle($kayit, 'tekrar.example');

    /*
    | ⚠️ Veritabanı kısıtına bırakılsaydı marka 500 görürdü. Kontrol
    | kısıtın YERİNE değil ÖNÜNDE.
    */
    expect(fn () => $servis->ekle($kayit, 'tekrar.example'))
        ->toThrow(DomainUnavailableException::class);
});

it('★ DOĞRULAMA TARİHİ tazelenmiyor', function () {
    $marka = markaKur('oad-i.test');
    tenancy()->end();

    $dns = sahteDns();

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);
    $servis = app(CustomDomainService::class);

    $ozel = $servis->ekle($kayit, 'tarih.example');
    $dns->ayarla('tarih.example', a: [(string) config('tenancy.custom_domain_ip')]);

    $servis->dogrula($ozel);

    /*
    | ⚠️ TARİH GERİYE ÇEKİLİYOR — ve bunu bir kırma denemesi ortaya
    | çıkardı: tazeleme koruması kaldırıldığında test YEŞİL kalıyordu,
    | çünkü iki çağrı AYNI SANİYEDE oluyor ve fark görünmüyordu.
    */
    DB::connection('pgsql')->table('domains')->where('id', $ozel->id)
        ->update(['verified_at' => now()->subDays(10)]);

    $ilk = $ozel->refresh()->verified_at;

    $servis->dogrula($ozel->refresh());

    /*
    | ⚠️ Her kontrolde tazelenseydi "ne zaman doğrulandı" bilgisi bugüne
    | kayar ve destek "bu alan adı ne zamandır çalışıyor" sorusunu
    | cevaplayamazdı.
    */
    expect($ozel->refresh()->verified_at?->toIso8601String())->toBe($ilk?->toIso8601String());
});

it('★ UÇTAN: marka alan adı ekliyor, TALİMAT alıyor, doğruluyor', function () {
    $marka = markaKur('oad-j.test');
    $token = panelTokeni('oad-j.test', $marka['sahip']->email);

    guardOnbelleginiTemizle();

    /*
    | ⚠️ Sahte DNS `panelTokeni`'nden SONRA kuruluyor: önce kurulsaydı
    | araya giren istekler konteyneri tazeleyip bağlamayı düşürebilirdi.
    */
    $dns = sahteDns();

    $ekle = $this->withToken($token)
        ->postJson('http://oad-j.test/panel/domains', ['domain' => 'magazam.example'])
        ->assertCreated();

    /*
    | ⚠️ TALİMAT cevapta dönüyor. Dönmeseydi marka ne yapacağını bilemez
    | ve "ekledim ama çalışmıyor" derdi — bu adım İNSAN İŞİ ve destek
    | yükünün tamamı orada.
    */
    expect($ekle->json('domain.verified'))->toBeFalse()
        ->and($ekle->json('instructions.cname.type'))->toBe('CNAME')
        ->and($ekle->json('instructions.txt.value'))->toBeString();

    // Kontrol: DNS henüz yok.
    guardOnbelleginiTemizle();
    $ilk = $this->withToken($token)
        ->postJson('http://oad-j.test/panel/domains/magazam.example/verify')
        ->assertOk();

    /*
    | ⚠️ Başarısız kontrol 200 dönüyor, 4xx DEĞİL: en olağan durum bu,
    | DNS değişikliği yayılmamış olabiliyor. 4xx dönseydi panel "bir
    | şeyler bozuk" gösterirdi.
    */
    expect($ilk->json('verified'))->toBeFalse()
        ->and($ilk->json('instructions'))->not->toBeNull();

    // Marka kaydı ekliyor.
    $dns->ayarla('magazam.example', cname: [strtolower((string) config('tenancy.custom_domain_cname'))]);

    guardOnbelleginiTemizle();
    $ikinci = $this->withToken($token)
        ->postJson('http://oad-j.test/panel/domains/magazam.example/verify')
        ->assertOk();

    expect($ikinci->json('verified'))->toBeTrue()
        ->and($ikinci->json('instructions'))->toBeNull();

    // ★ Artık ask ucu 200 diyor — sertifika alınabilir.
    $this->get('http://localhost/tenancy/domain-check?domain=magazam.example')->assertOk();
});

it('★ SON alan adı silinemiyor — marka erişilemez kalmasın', function () {
    $marka = markaKur('oad-k.test');
    $token = panelTokeni('oad-k.test', $marka['sahip']->email);

    guardOnbelleginiTemizle();

    /*
    | ⚠️ Silinebilseydi marka hiçbir adresten erişilemez hâle gelir ve
    | paneline girip düzeltemezdi — kendini dışarıda bırakma tuzağı
    | (1A.3'teki "sahip kendi rolünden staff.manage'i kaldıramaz" ile
    | aynı düşünce).
    */
    $this->withToken($token)
        ->deleteJson('http://oad-k.test/panel/domains/oad-k.test')
        ->assertStatus(409);

    expect(Domain::where('domain', 'oad-k.test')->exists())->toBeTrue();
});

it('★ BAŞKA markanın alan adı yönetilemiyor', function () {
    markaKur('oad-l.test');
    tenancy()->end();

    $marka = markaKur('oad-m.test');
    $token = panelTokeni('oad-m.test', $marka['sahip']->email);

    guardOnbelleginiTemizle();

    /*
    | ⚠️ `tenant_id` istekten DEĞİL bağlamdan geliyor. İstekten alınsaydı
    | marka başka markanın alan adını silebilir ve onu erişilemez
    | yapabilirdi.
    */
    $this->withToken($token)
        ->deleteJson('http://oad-m.test/panel/domains/oad-l.test')
        ->assertNotFound();

    expect(Domain::where('domain', 'oad-l.test')->exists())->toBeTrue();
});
