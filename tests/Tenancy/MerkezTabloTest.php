<?php

use App\Enums\TenantStatus;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use App\Tenancy\Commands\CreateTenant;
use Illuminate\Support\Facades\DB;

/*
| Merkez tablo ve abonelik alanları (3B).
|
| ★ ÜÇ İDDİA:
|   1  tipler DOĞRU: timestamptz + jsonb
|   2  yeni alanlar GERÇEK KOLONA yazılıyor (json'a değil)
|   3  `data`'da KOPYA yok — tek kaynak
|
| ⚠️ 2 ve 3 ölçülmeden bilinemezdi ve ikisi de SESSİZ hata üretiyordu.
*/

it('★ MERKEZ tablolarda timestamptz ve jsonb kullanılıyor', function () {
    /*
    | ⚠️ Marka şemalarında `timestampsTz()` disiplinini uyguladık ama merkez
    | tablo paketin migration'ından geliyordu ve `timestamp` (ofissiz) idi —
    | yani kendi kuralımız kendi evimizde ihlal ediliyordu (CLAUDE.md, 2. kural).
    */
    $tipler = DB::connection('pgsql')->select("
        SELECT table_name, column_name, data_type
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name IN ('tenants', 'domains', 'plans')
          AND column_name IN ('created_at', 'updated_at', 'data', 'features', 'trial_ends_at')
    ");

    foreach ($tipler as $t) {
        $beklenen = in_array($t->column_name, ['data', 'features'], true) ? 'jsonb' : 'timestamp with time zone';

        expect($t->data_type)->toBe($beklenen, "{$t->table_name}.{$t->column_name}");
    }

    expect($tipler)->not->toBeEmpty();
});

it('★ YENİ ALANLAR GERÇEK KOLONA yazılıyor — data json\'ına DEĞİL', function () {
    /*
    | ★ BU TESTİN SEBEBİ ÖLÇÜLMÜŞ BİR SESSİZ HATA.
    |
    | Paketin `getCustomColumns()` varsayılanı `['id']`; geri kalan her alan
    | `data` json'ına gidiyor. Kolonları açmak TEK BAŞINA yetmiyordu:
    |
    |   kolon name=NULL  status=NULL        ← boş
    |   data  {"name":"X","status":"trial"} ← veri burada
    |   $tenant->name → 'X'                 ← model DOĞRU okuyor (!)
    |
    | ⚠️ Sinsi olan son satır: kod çalışıyor gibi görünüyor. Kırılan tek
    | şey SORGU — "denemesi bugün biten markalar" hep boş dönerdi.
    */
    $marka = Tenant::create([
        'name' => 'Kolon Testi',
        'status' => TenantStatus::Trial,
        'trial_ends_at' => now()->addDays(14),
    ]);

    $ham = DB::connection('pgsql')->table('tenants')->where('id', $marka->id)->first();

    expect($ham?->name)->toBe('Kolon Testi')
        ->and($ham?->status)->toBe('trial')
        ->and($ham?->trial_ends_at)->not->toBeNull();

    /*
    | ⚠️ Ve `data`'da KOPYASI YOK. Olsaydı iki kaynak sessizce ayrışırdı —
    | ölçüldü: iki yerde birden duran alanda MODEL `data`'yı okuyor, yani
    | kolonu güncelleyen panel değişikliği hiç görünmezdi.
    */
    $veri = json_decode((string) $ham?->data, true);

    expect($veri)->not->toHaveKey('name')
        ->and($veri)->not->toHaveKey('status');

    $marka->delete();
});

it('★ SORGU çalışıyor — sessiz hatanın asıl bedeli buydu', function () {
    $bitmis = Tenant::create([
        'name' => 'Denemesi Bitmiş',
        'status' => TenantStatus::Trial,
        'trial_ends_at' => now()->subDay(),
    ]);

    $suruyor = Tenant::create([
        'name' => 'Denemesi Sürüyor',
        'status' => TenantStatus::Trial,
        'trial_ends_at' => now()->addDays(5),
    ]);

    /*
    | ★ Faz 3'ün BÜTÜN zamanlanmış görevleri bu biçimde sorgu yazacak.
    | Alanlar `data` json'ında kalsaydı bu sorgu HİÇBİR ŞEY bulmazdı ve
    | hata da vermezdi.
    */
    $bulunanlar = Tenant::query()
        ->where('status', TenantStatus::Trial)
        ->where('trial_ends_at', '<=', now())
        ->pluck('name')
        ->all();

    expect($bulunanlar)->toContain('Denemesi Bitmiş')
        ->and($bulunanlar)->not->toContain('Denemesi Sürüyor');

    $bitmis->delete();
    $suruyor->delete();
});

it('★ tenant:create markayı DENEME durumunda açıyor — GERÇEK KOMUTLA', function () {
    /*
    | ⚠️ `markaKur` DEĞİL, gerçek `tenant:create` çağrılıyor. Yardımcıyla
    | ölçülseydi test kendi yardımcısını doğrulardı, komutu değil — 1E.4'te
    | ikisi ayrışmış ve testler gerçekte olmayan bir markayı ölçmüştü.
    */
    $this->artisan('tenant:create', ['ad' => 'Komut Markası', 'alan-adi' => 'mrk-a.test'])
        ->assertExitCode(0);

    $kayit = Tenant::where('name', 'Komut Markası')->first();

    /*
    | ⚠️ Durum AÇIKÇA yazılıyor; kolonun varsayılanı yok. Varsayılan
    | `active` olsaydı durum vermeyi unutan her yol sessizce "ödeyen
    | müşteri" üretirdi.
    */
    expect($kayit?->status)->toBe(TenantStatus::Trial)
        ->and($kayit?->trial_ends_at)->not->toBeNull();

    // ⚠️ 14 gün — kartsız deneme (3 numaralı karar).
    expect((int) round((float) now()->diffInDays($kayit?->trial_ends_at)))
        ->toBe(CreateTenant::DENEME_GUN);

    expect($kayit?->trial_ends_at?->isFuture())->toBeTrue();

    $kayit?->delete();
});

it('★ DENEME BİTTİ Mİ: null "deneme yok" demek, "bitmiş" değil', function () {
    $odeyen = Tenant::create(['name' => 'Ödeyen', 'status' => TenantStatus::Active]);
    $bitmis = Tenant::create(['name' => 'Bitmiş', 'status' => TenantStatus::Trial, 'trial_ends_at' => now()->subDay()]);

    /*
    | ⚠️ Ayrım yapılmasaydı ödeyen marka "denemesi bitmiş" sayılır ve
    | askıya alma görevi onu da toplardı.
    */
    expect($odeyen->denemesiBittiMi())->toBeFalse()
        ->and($bitmis->denemesiBittiMi())->toBeTrue();

    $odeyen->delete();
    $bitmis->delete();
});

it('★ PLAN SINIRI: null SINIRSIZ demek, sıfır değil', function () {
    $plan = new Plan;

    /*
    | ⚠️ `0` kullanılsaydı "sıfır ürün" ile "sınırsız" aynı değerle
    | anlatılırdı ve bir gün biri `>= $limit` yazıp sınırsız planın
    | kataloğunu kilitlerdi.
    */
    expect($plan->asildiMi(null, 999_999))->toBeFalse()
        ->and($plan->asildiMi(0, 0))->toBeTrue()
        ->and($plan->asildiMi(5, 4))->toBeFalse()
        ->and($plan->asildiMi(5, 5))->toBeTrue();
});

it('★ PLAN merkez bağlantıda — marka bağlamında da okunabiliyor', function () {
    tenancy()->end();

    /*
    | ⚠️ ÖNCE TEMİZLİK. `tests/Tenancy/` paketinde `RefreshDatabase` YOK
    | (transaction şema oluşturmayı bozuyor) — yani merkez tabloya yazan
    | test kendi kalıntısını kendi toplamak zorunda.
    |
    | Bu satır bir KIRMA DENEMESİNDEN doğdu: test hata verince alttaki
    | `delete()` hiç çalışmadı, plan test veritabanında kaldı ve `code`
    | benzersizlik kısıtı yüzünden test SONRAKİ koşularda da kırmızı
    | kaldı — hata artık gerçek sebepten değil kalıntıdan geliyordu.
    */
    Plan::where('code', 'test-plan')->delete();

    $plan = Plan::create(['code' => 'test-plan', 'name' => 'Test', 'price' => 100, 'max_products' => 50]);

    markaKur('mrk-b.test');

    /*
    | ⚠️ Marka bağlamındayken merkez tabloyu okumak: `CentralConnection`
    | olmasaydı bu sorgu MARKA şemasında `plans` arar ve "tablo yok"
    | hatası verirdi (0.5'in `search_path` tuzağı).
    |
    | ★ Kota kontrolü (3F) tam olarak burada, marka bağlamında çalışacak.
    */
    expect(Plan::where('code', 'test-plan')->first()?->max_products)->toBe(50);

    $plan->delete();
});

it('★ DURUM kapıları: askıda panel kapalı, denemede açık', function () {
    /*
    | ⚠️ Askıda VİTRİN açık kalıyor (4 numaralı karar) — o ayrım uçlarda
    | (3G). Burada ölçülen: panelin kapandığı.
    */
    expect(TenantStatus::Trial->panelAcikMi())->toBeTrue()
        ->and(TenantStatus::Active->panelAcikMi())->toBeTrue()
        ->and(TenantStatus::PastDue->panelAcikMi())->toBeTrue()
        ->and(TenantStatus::Suspended->panelAcikMi())->toBeFalse()
        ->and(TenantStatus::Closed->panelAcikMi())->toBeFalse()
        ->and(TenantStatus::Provisioning->panelAcikMi())->toBeFalse();

    expect(TenantStatus::PastDue->satisAcikMi())->toBeTrue()
        ->and(TenantStatus::Suspended->satisAcikMi())->toBeFalse();
});

it('★ MEVCUT markalar geriye dönük dolduruldu', function () {
    /*
    | ⚠️ Kolon sonradan eklendiğinde mevcut satırlar BOŞ kalıyor ve bu hata
    | VERMİYOR (2C ve 2F'de ısırdı, bu üçüncü). Migration `name`'i json'dan
    | taşıdı ve `status`'ü `active` yazdı.
    |
    | ⚠️ `status` null kalan bir marka, panel kapısı kontrollerinde
    | "bilinmeyen durum" olurdu.
    */
    $bossuz = Tenant::query()->whereNull('status')->count();
    $adsiz = Tenant::query()->whereNull('name')->count();

    expect($bossuz)->toBe(0)
        ->and($adsiz)->toBe(0);
});
