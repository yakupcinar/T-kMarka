<?php

use App\Console\Commands\SeedPlans;
use App\Enums\TenantStatus;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use App\Platform\Subscription\AlreadySubscribedException;
use App\Platform\Subscription\FakeSubscriptionProvider;
use App\Platform\Subscription\SubscriptionOutcome;
use App\Platform\Subscription\SubscriptionProvider;
use App\Platform\Subscription\SubscriptionProviderException;
use App\Platform\Subscription\SubscriptionService;
use App\Platform\Subscription\SubscriptionState;
use Illuminate\Support\Facades\DB;

/*
| Abonelik (3E).
|
| ★ DÖRT İDDİA:
|   1  kart girilince deneme biter, abonelik başlar
|   2  ödeme başarısız → NEZAKET, askı DEĞİL
|   3  iptal SAĞLAYICIDA da yapılıyor
|   4  denetim sağlayıcıyla kendi kaydımızı karşılaştırıyor
|
| ⚠️ Bu abonelik BİZİM markadan tahsilatımız (3E) — 1E'deki markanın kendi
| müşterisinden tahsilatıyla karıştırılmamalı.
*/

/** Ödeyen markaya hazır bir kayıt kurar. */
function abonelikMarkasi(string $ad = 'Abone Marka'): Tenant
{
    tenancy()->end();

    return Tenant::create([
        'name' => $ad,
        'status' => TenantStatus::Trial,
        'trial_ends_at' => now()->addDays(3),
    ]);
}

function testPlani(): Plan
{
    tenancy()->end();

    return Plan::firstOrCreate(
        ['code' => 'test-abonelik'],
        ['name' => 'Test', 'price' => '499.00', 'max_products' => 100, 'max_staff' => 1],
    );
}

/** @return array<string, string> */
function gecerliKart(): array
{
    return ['number' => '5528790000000008', 'holder' => 'Test Kart', 'expiry' => '12/30', 'cvc' => '123'];
}

it('★ KART girilince DENEME biter, abonelik başlar', function () {
    $marka = abonelikMarkasi();
    $plan = testPlani();

    $marka = app(SubscriptionService::class)->baslat($marka, $plan, gecerliKart());

    expect($marka->status)->toBe(TenantStatus::Active)
        ->and($marka->subscription_ref)->not->toBeNull()
        ->and($marka->plan?->code)->toBe('test-abonelik');

    /*
    | ★ DENEME BİTİŞİ TEMİZLENİYOR. Kalsaydı "denemesi bitmiş markaları
    | askıya al" görevi ÖDEYEN markayı da toplardı.
    */
    expect($marka->refresh()->trial_ends_at)->toBeNull();

    $marka->delete();
});

it('★ İKİNCİ ABONELİK açılamıyor', function () {
    $marka = abonelikMarkasi('İki Kez');
    $plan = testPlani();
    $servis = app(SubscriptionService::class);

    $servis->baslat($marka, $plan, gecerliKart());

    /*
    | ⚠️ Açılabilseydi marka İKİ KEZ ücretlendirilir ve ilk abonelik
    | sağlayıcıda öksüz kalırdı — kimse iptal etmediği için her ay
    | çekmeye devam ederdi.
    */
    expect(fn () => $servis->baslat($marka->refresh(), $plan, gecerliKart()))
        ->toThrow(AlreadySubscribedException::class);

    $marka->delete();
});

it('★ EKSİK KART reddediliyor — sahte sağlayıcı da gerçeği taklit ediyor', function () {
    $marka = abonelikMarkasi('Kartsız');
    $plan = testPlani();

    /*
    | ⚠️ 1E.7'nin dersi: sahte sağlayıcı KOLAYLIK SAĞLAMAMALI. Sahte cevapta
    | token yoktu ve `status` kontrolü hiç sınanmıyordu — test yeşildi,
    | gerçek sağlayıcıda patladı.
    */
    expect(fn () => app(SubscriptionService::class)->baslat($marka, $plan, ['number' => '', 'holder' => 'X', 'expiry' => '12/30', 'cvc' => '123']))
        ->toThrow(SubscriptionProviderException::class);

    // ⚠️ Marka DOKUNULMAMIŞ kalıyor.
    expect($marka->refresh()->status)->toBe(TenantStatus::Trial)
        ->and($marka->subscription_ref)->toBeNull();

    $marka->delete();
});

it('★ ÖDEME BAŞARISIZ → NEZAKET, askı DEĞİL', function () {
    $marka = abonelikMarkasi('Ödeyemeyen');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());
    $referans = (string) $marka->subscription_ref;

    $servis->bildirimiIsle(new SubscriptionOutcome($referans, SubscriptionState::Unpaid));

    /*
    | ★ 4 NUMARALI KARARIN KALBİ. Çoğu başarısız ödeme KASITLI DEĞİL (kart
    | yenilenmiş, limit dolmuş); ilk günde kapatmak müşteriyi kaybetmenin
    | en hızlı yolu.
    */
    expect($marka->refresh()->status)->toBe(TenantStatus::PastDue)
        ->and($marka->grace_ends_at)->not->toBeNull()
        ->and($marka->suspended_at)->toBeNull();

    $marka->delete();
});

it('★ TEKRARLAYAN BAŞARISIZLIK nezaket süresini UZATMIYOR', function () {
    $marka = abonelikMarkasi('Tekrar Eden');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());
    $referans = (string) $marka->subscription_ref;

    $servis->bildirimiIsle(new SubscriptionOutcome($referans, SubscriptionState::Unpaid));
    $ilk = $marka->refresh()->grace_ends_at;

    $servis->bildirimiIsle(new SubscriptionOutcome($referans, SubscriptionState::Unpaid));

    /*
    | ⚠️ Uzatılsaydı her başarısız denemede sayaç sıfırlanır ve marka
    | SONSUZA KADAR askıya alınmazdı — ödemeden kullanmaya devam ederdi.
    |
    | ⚠️ KORUMANIN YERİ ÖLÇÜLDÜ: servis tarafında bir "zaten past_due ise
    | dokunma" kontrolü vardı ve kırma denemesinde kaldırıldığında hiçbir
    | test düşmedi — ölü koddu, kaldırıldı. Asıl koruyan
    | [TenantLifecycle::gecir()]: aynı duruma geçişte erken dönüyor ve
    | tarihleri yeniden yazmıyor. Bu test artık onu ölçüyor.
    */
    expect($marka->refresh()->grace_ends_at?->toIso8601String())->toBe($ilk?->toIso8601String());

    $marka->delete();
});

it('★ ÖDEME DÜZELİNCE geri dönüyor ve nezaket TEMİZLENİYOR', function () {
    $marka = abonelikMarkasi('Düzelen');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());
    $referans = (string) $marka->subscription_ref;

    $servis->bildirimiIsle(new SubscriptionOutcome($referans, SubscriptionState::Unpaid));
    expect($marka->refresh()->status)->toBe(TenantStatus::PastDue);

    $servis->bildirimiIsle(new SubscriptionOutcome($referans, SubscriptionState::Active));

    /*
    | ⚠️ `grace_ends_at` temizlenmeseydi marka aktif görünür ama nezaket
    | görevi onu yine askıya alırdı.
    */
    expect($marka->refresh()->status)->toBe(TenantStatus::Active)
        ->and($marka->grace_ends_at)->toBeNull();

    $marka->delete();
});

it('★ İPTAL SAĞLAYICIDA da yapılıyor — en pahalı sessiz hata', function () {
    $marka = abonelikMarkasi('İptal Eden');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());
    $referans = (string) $marka->subscription_ref;

    $servis->iptal($marka);

    /*
    | ★ Yalnızca kendi kaydımızı kapatsaydık iyzico HER AY ÇEKMEYE DEVAM
    | ederdi: marka ayrıldığını sanarken parası gitmeye devam ederdi.
    |
    | Sağlayıcıya SORARAK doğruluyoruz — kendi kaydımıza bakmak bu iddiayı
    | hiç ölçmezdi.
    */
    expect(app(SubscriptionProvider::class)->sorgula($referans))->toBe(SubscriptionState::Canceled);

    expect($marka->refresh()->status)->toBe(TenantStatus::Closed)
        ->and($marka->subscription_ref)->toBeNull();

    $marka->delete();
});

it('★ SAĞLAYICI İPTAL EDERSE marka askıya alınıyor ve referans temizleniyor', function () {
    $marka = abonelikMarkasi('Sağlayıcı İptali');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());
    $referans = (string) $marka->subscription_ref;

    $servis->bildirimiIsle(new SubscriptionOutcome($referans, SubscriptionState::Canceled));

    /*
    | ⚠️ Referans kalsaydı "aboneliği var" sanıp yeni abonelik açılmasını
    | engellerdik — marka geri dönmek istese açamazdı.
    */
    expect($marka->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($marka->subscription_ref)->toBeNull();

    $marka->delete();
});

it('★ BİLİNMEYEN REFERANS 200 dönüyor — webhook zinciri kırılmasın', function () {
    tenancy()->end();

    /*
    | ⚠️ 404 dönseydi sağlayıcı tekrar tekrar denerdi. 1E.6'da webhook
    | zinciri tam böyle kırılmış ve TAHSİLAT HİÇ KAYDEDİLMEMİŞTİ.
    */
    $sonuc = app(SubscriptionService::class)->bildirimiIsle(
        new SubscriptionOutcome('YOK-BOYLE-BIR-REF', SubscriptionState::Active)
    );

    expect($sonuc)->toBeNull();
});

it('★ İMZASIZ bildirim reddediliyor', function () {
    tenancy()->end();

    $govde = ['subscriptionReference' => 'X', 'status' => 'active'];

    $this->postJson('http://localhost/platform/subscription/webhook', $govde)
        ->assertUnauthorized();
});

it('★ İKİNCİ ABONELİK UÇTAN 409 dönüyor — 500 değil', function () {
    $marka = abonelikMarkasi('Uçtan İkinci');
    $plan = testPlani();

    app(SubscriptionService::class)->baslat($marka, $plan, gecerliKart());

    $token = platformTokeni('abonelik@tikmarka.test');

    /*
    | ★ BU TEST GERÇEK HTTP KOŞUSUNDAN DOĞDU. 18 test yeşilken gerçek
    | `curl` isteği 500 aldı: istisna `bootstrap/app.php`'de eşlenmemişti.
    |
    | ⚠️ Testler servisi DOĞRUDAN çağırıp istisnayı yakalıyordu, yani
    | uçtan hiç geçmiyorlardı — eşleme eksikliği görünmüyordu.
    */
    $this->withToken($token)
        ->postJson("http://localhost/platform/tenants/{$marka->id}/subscription", [
            'plan_code' => $plan->code,
            'card' => gecerliKart(),
        ])
        ->assertStatus(409);

    $marka->delete();
});

it('★ İMZA ANAHTARI YOKSA 500 — "senin gönderdiğin bozuk" DEMİYOR', function () {
    tenancy()->end();

    /*
    | ★ BU TEST DE GERÇEK KOŞUDAN DOĞDU. Anahtar boşken webhook 400
    | dönüyordu, yani sorumluluğu gönderene atıyordu. Oysa sorun BİZDE:
    | üretimde bütün bildirimler sessizce reddedilir ve kimse sebebini
    | anlamazdı.
    */
    config(['subscription.webhook_secret' => '']);

    $this->postJson('http://localhost/platform/subscription/webhook', [
        'subscriptionReference' => 'X',
        'status' => 'active',
    ])->assertStatus(500);
});

it('★ UÇTAN: imzalı bildirim işleniyor', function () {
    $marka = abonelikMarkasi('Webhook Markası');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());
    $referans = (string) $marka->subscription_ref;

    /** @var FakeSubscriptionProvider $saglayici */
    $saglayici = app(SubscriptionProvider::class);

    $govde = ['subscriptionReference' => $referans, 'status' => 'unpaid'];

    $this->withHeaders(['X-Subscription-Signature' => $saglayici->imzala($govde)])
        ->postJson('http://localhost/platform/subscription/webhook', $govde)
        ->assertOk();

    expect($marka->refresh()->status)->toBe(TenantStatus::PastDue);

    $marka->delete();
});

it('★ NEZAKET SÜRESİ dolunca askıya alınıyor', function () {
    $marka = abonelikMarkasi('Süresi Dolan');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());
    $referans = (string) $marka->subscription_ref;

    $servis->bildirimiIsle(new SubscriptionOutcome($referans, SubscriptionState::Unpaid));

    // Nezaket süresini geçmişe çekiyoruz.
    DB::connection('pgsql')->table('tenants')->where('id', $marka->id)
        ->update(['grace_ends_at' => now()->subDay()]);

    $this->artisan('abonelik:nezaket-denetle')->assertExitCode(0);

    expect($marka->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($marka->suspended_at)->not->toBeNull();

    $marka->delete();
});

it('★ DENEMESİ BİTEN askıya alınıyor — ama ÖDEYEN alınmıyor', function () {
    $bitmis = abonelikMarkasi('Denemesi Bitmiş');
    DB::connection('pgsql')->table('tenants')->where('id', $bitmis->id)
        ->update(['trial_ends_at' => now()->subDay()]);

    $odeyen = abonelikMarkasi('Ödeyen Marka');
    $odeyen = app(SubscriptionService::class)->baslat($odeyen, testPlani(), gecerliKart());

    /*
    | ⚠️ Ödeyen markanın `trial_ends_at`'i temizleniyor; ama sorgudaki
    | `subscription_ref IS NULL` şartı ikinci bir kapı. Biri unutulsa
    | diğeri tutuyor — ödeyen markayı askıya almak felaket olurdu.
    */
    DB::connection('pgsql')->table('tenants')->where('id', $odeyen->id)
        ->update(['trial_ends_at' => now()->subDay()]);

    $this->artisan('abonelik:deneme-denetle')->assertExitCode(0);

    expect($bitmis->refresh()->status)->toBe(TenantStatus::Suspended)
        ->and($odeyen->refresh()->status)->toBe(TenantStatus::Active);

    $bitmis->delete();
    $odeyen->delete();
});

it('★ ABONELİĞİ OLAN marka trial görünse bile askıya ALINMIYOR', function () {
    $marka = abonelikMarkasi('Tuhaf Durum');

    /*
    | ★ BU TEST BİR KIRMA DENEMESİNDEN DOĞDU.
    |
    | Sorgudaki `subscription_ref IS NULL` şartı kaldırıldığında hiçbir
    | test düşmedi — çünkü ödeyen marka zaten `active` durumda ve sorgu
    | `status = trial` diyor. Yani şart o testte ÖLÜ görünüyordu.
    |
    | Şart yine de duruyor ve ASIL koruduğu şey bu: durum ile abonelik
    | arasında tutarsızlık olan bir kayıt. `baslat()` transaction içinde
    | ikisini birlikte yazıyor ama bir gün başka bir yol `trial` bırakırsa
    | (elle düzeltme, veri taşıma, yeni bir akış) ödeyen marka askıya
    | alınırdı.
    |
    | ⚠️ Test bu yüzden durumu ELLE tutarsız kuruyor — gerçek hayatta
    | nasıl oluşacağını varsaymak yerine, oluştuğunda ne olduğunu ölçüyor.
    */
    DB::connection('pgsql')->table('tenants')->where('id', $marka->id)->update([
        'trial_ends_at' => now()->subDay(),
        'subscription_ref' => 'ELLE-GIRILMIS-REF',
    ]);

    $this->artisan('abonelik:deneme-denetle')->assertExitCode(0);

    expect($marka->refresh()->status)->toBe(TenantStatus::Trial);

    $marka->delete();
});

it('★ KURU ÇALIŞMA hiçbir şey değiştirmiyor', function () {
    $marka = abonelikMarkasi('Kuru Deneme');
    DB::connection('pgsql')->table('tenants')->where('id', $marka->id)
        ->update(['trial_ends_at' => now()->subDay()]);

    $this->artisan('abonelik:deneme-denetle --kuru')->assertExitCode(0);

    expect($marka->refresh()->status)->toBe(TenantStatus::Trial);

    $marka->delete();
});

it('★ DENETİM sağlayıcıyla kendi kaydımızı karşılaştırıyor', function () {
    $marka = abonelikMarkasi('Denetim Markası');
    $servis = app(SubscriptionService::class);

    $marka = $servis->baslat($marka, testPlani(), gecerliKart());

    expect($servis->tutarsizliklar())->toBe([]);

    /*
    | ★ `committed` (1D) ve `rating_avg` (2E) denetimlerinin aynısı:
    | materyalleştirilmiş bir durumun bedeli denetimdir.
    |
    | Sağlayıcıda iptal edip bizim kaydı bilerek `active` bırakıyoruz —
    | gerçek hayatta bu, kaçırılmış bir bildirim demek.
    */
    app(SubscriptionProvider::class)->iptal((string) $marka->subscription_ref);

    $tutarsiz = $servis->tutarsizliklar();

    expect($tutarsiz)->toHaveCount(1)
        ->and($tutarsiz[0]['bizdeki'])->toBe('active')
        ->and($tutarsiz[0]['saglayicida'])->toBe('canceled');

    $marka->delete();
});

it('★ PLANLAR kuruluyor ve var olan EZİLMİYOR', function () {
    tenancy()->end();

    Plan::whereIn('code', ['baslangic', 'buyume', 'olcek'])->delete();

    $this->artisan('plan:kur')->assertExitCode(0);

    expect(Plan::whereIn('code', ['baslangic', 'buyume', 'olcek'])->count())->toBe(3)
        ->and(Plan::where('code', 'olcek')->first()?->max_products)->toBeNull();

    // Marka fiyatı elle değiştirmiş.
    Plan::where('code', 'baslangic')->update(['price' => '299.00']);

    $this->artisan('plan:kur --guncelle')->assertExitCode(0);

    /*
    | ⚠️ 3A'nın dersi. `--guncelle` ile bile FİYATA dokunulmuyor: fiyat
    | değişimi yürüyen abonelikleri etkiliyor ve sağlayıcı tarafında ayrı
    | işlem gerektiriyor.
    */
    expect((string) Plan::where('code', 'baslangic')->first()?->price)->toBe('299.00');
});

it('★ SINIRLAR ürün ve personelde — sipariş sınırı YOK', function () {
    /*
    | ⚠️ Araştırıldı: İkas ve Shopify'da da aylık sipariş sınırı yok.
    | Sipariş kısıtlamak markanın satışını, yani cirosunu kesmek demek.
    */
    foreach (SeedPlans::tanimlar() as $tanim) {
        expect($tanim)->toHaveKeys(['max_products', 'max_staff'])
            ->and($tanim)->not->toHaveKey('max_orders');
    }
});
