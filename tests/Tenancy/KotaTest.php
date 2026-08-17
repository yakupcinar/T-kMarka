<?php

use App\Domain\Catalog\CollectionService;
use App\Domain\Catalog\ProductService;
use App\Domain\Identity\StaffService;
use App\Domain\Quota\QuotaExceededException;
use App\Domain\Quota\QuotaGuard;
use App\Enums\CollectionType;
use App\Enums\TenantStatus;
use App\Models\Product;
use App\Models\User;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use App\Platform\PlanQuotaGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Plan kotaları (3F).
|
| ★ DÖRT İDDİA:
|   1  sınır GERÇEKTEN uygulanıyor — plan satmanın anlamı bu
|   2  `null` = SINIRSIZ, sıfır değil
|   3  özellik kapalıysa YENİ işlem engelleniyor, VAR OLAN silinmiyor
|   4  kontrol SERVİSTE — artisan/tohumlayıcı da atlayamıyor
*/

/**
 * Markayı belirli bir plana bağlar ve o markada kalır.
 *
 * @param  array<string, bool>  $ozellikler
 */
function planliMarka(string $alanAdi, ?int $urunLimiti, ?int $personelLimiti = 5, array $ozellikler = ['collections' => true, 'reviews' => true]): Tenant
{
    $marka = markaKur($alanAdi);
    magazayiHazirla();

    tenancy()->end();

    $kod = 'kota-'.substr(md5($alanAdi), 0, 8);

    /*
    | ⚠️ `updateOrCreate` — `firstOrCreate` DEĞİL. Testlerden biri planın
    | özelliklerini DEĞİŞTİRİYOR (plan düşürme senaryosu) ve
    | `tests/Tenancy/` paketinde `RefreshDatabase` yok; plan kaydı test
    | veritabanında kalıyor. `firstOrCreate` bir sonraki koşuda o bozuk
    | planı bulur ve test GERÇEK SEBEPTEN DEĞİL kalıntıdan kırmızı olurdu.
    |
    | 3B'de aynı kalıntı sorunu yaşanmıştı.
    */
    $plan = Plan::updateOrCreate(['code' => $kod], [
        'name' => 'Kota Planı',
        'price' => '100.00',
        'max_products' => $urunLimiti,
        'max_staff' => $personelLimiti,
        'features' => $ozellikler,
    ]);

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);
    $kayit->plan()->associate($plan);

    /*
    | ⚠️ Durum `active` YAPILIYOR — deneme durumunda plan OKUNMUYOR
    | (denemede kendi sınırları var). Bu satır olmadan test plan
    | sınırlarını değil deneme sınırlarını ölçerdi.
    */
    $kayit->status = TenantStatus::Active;
    $kayit->save();

    tenancy()->initialize($kayit);

    return $kayit;
}

it('★ ÜRÜN SINIRI gerçekten uygulanıyor', function () {
    planliMarka('kota-a.test', urunLimiti: 2);

    $urunler = app(ProductService::class);

    $urunler->olustur(['title' => 'Bir']);
    $urunler->olustur(['title' => 'İki']);

    /*
    | ★ PLAN SATMANIN ANLAMI BU SATIRDA. Sınır uygulanmasaydı marka
    | "100 ürün" planına para verir, sınırsız kullanır ve bu hiçbir yerde
    | görünmezdi.
    */
    expect(fn () => $urunler->olustur(['title' => 'Üç']))
        ->toThrow(QuotaExceededException::class);

    expect(Product::count())->toBe(2);
});

it('★ SINIRSIZ plan gerçekten sınırsız — null, sıfır DEĞİL', function () {
    planliMarka('kota-b.test', urunLimiti: null);

    $urunler = app(ProductService::class);

    /*
    | ⚠️ `0` kullanılsaydı "sıfır ürün" ile "sınırsız" aynı değerle
    | anlatılırdı ve bir gün biri `>= $limit` yazıp sınırsız planın
    | kataloğunu KİLİTLERDİ.
    */
    foreach (['Bir', 'İki', 'Üç', 'Dört', 'Beş'] as $ad) {
        $urunler->olustur(['title' => $ad]);
    }

    expect(Product::count())->toBe(5);
});

it('★ PERSONEL SINIRI sahibi de sayıyor', function () {
    planliMarka('kota-c.test', urunLimiti: null, personelLimiti: 2);

    // ⚠️ Marka açılırken SAHİP oluşuyor → zaten 1 personel var.
    expect(User::count())->toBe(1);

    app(StaffService::class)->olustur(
        ['name' => 'İkinci', 'email' => 'ikinci@kota.test', 'password' => 'parola-123'],
        ['Katalog'],
    );

    /*
    | ★ Sahip HARİÇ tutulsaydı her plan sessizce BİR KİŞİ FAZLA verirdi ve
    | bu asla fark edilmezdi.
    */
    expect(fn () => app(StaffService::class)->olustur(
        ['name' => 'Üçüncü', 'email' => 'ucuncu@kota.test', 'password' => 'parola-123'],
        ['Katalog'],
    ))->toThrow(QuotaExceededException::class);

    expect(User::count())->toBe(2);
});

it('★ ÖZELLİK KAPALIYSA yeni koleksiyon açılamıyor', function () {
    planliMarka('kota-d.test', urunLimiti: null, personelLimiti: 5, ozellikler: ['collections' => false, 'reviews' => true]);

    expect(fn () => app(CollectionService::class)->olustur(['title' => 'Yasak'], CollectionType::Manual))
        ->toThrow(QuotaExceededException::class);
});

it('★ VAR OLAN koleksiyon SİLİNMİYOR — plan düşürmek veri kaybı değil', function () {
    $marka = planliMarka('kota-e.test', urunLimiti: null, personelLimiti: 5, ozellikler: ['collections' => true]);

    $koleksiyon = app(CollectionService::class)->olustur(['title' => 'Eski Koleksiyon'], CollectionType::Manual);

    /*
    | ★ Marka planını DÜŞÜRÜYOR — özellik kapanıyor.
    |
    | ⚠️ Var olan koleksiyon silinseydi ya da gizlenseydi plan düşürmek
    | VERİ KAYBI olurdu. Kota YENİ işlemi engelliyor, geçmişi değil.
    */
    tenancy()->end();

    $marka->plan?->update(['features' => ['collections' => false]]);

    tenancy()->initialize($marka);

    expect(app(CollectionService::class)->listele())->toHaveCount(1)
        ->and(app(CollectionService::class)->listele()->first()?->title)->toBe('Eski Koleksiyon');

    // Ama YENİSİ açılamıyor.
    expect(fn () => app(CollectionService::class)->olustur(['title' => 'Yeni'], CollectionType::Manual))
        ->toThrow(QuotaExceededException::class);
});

it('★ TANIMSIZ özellik KAPALI sayılıyor — sessizce kazanılmıyor', function () {
    planliMarka('kota-f.test', urunLimiti: null, personelLimiti: 5, ozellikler: ['collections' => true]);

    $kota = app(QuotaGuard::class);

    /*
    | ⚠️ Açık sayılsaydı plana yeni bir özellik eklendiğinde ESKİ planlar
    | onu SESSİZCE kazanırdı — hiçbir yerde görünmeden.
    */
    expect($kota->ozellikAcikMi('collections'))->toBeTrue()
        ->and($kota->ozellikAcikMi('reviews'))->toBeFalse()
        ->and($kota->ozellikAcikMi('henuz-olmayan-ozellik'))->toBeFalse();
});

it('★ DENEMEDE kendi sınırları geçerli — atanan plan OKUNMUYOR', function () {
    $marka = markaKur('kota-g.test');
    magazayiHazirla();

    tenancy()->end();

    $comert = Plan::firstOrCreate(['code' => 'kota-comert'], [
        'name' => 'Cömert', 'price' => '9999.00', 'max_products' => null, 'max_staff' => 99,
    ]);

    /** @var Tenant $kayit */
    $kayit = Tenant::findOrFail($marka['tenant']->id);
    $kayit->plan()->associate($comert);
    $kayit->save();

    tenancy()->initialize($kayit);

    /*
    | ★ Marka DENEMEDE ama cömert bir plan atanmış.
    |
    | ⚠️ Plan okunsaydı, kontrol düzleminden plan atanan bir deneme markası
    | ÖDEMEDEN o planın sınırlarını kullanırdı.
    */
    expect(app(QuotaGuard::class)->ozellikAcikMi('collections'))->toBeTrue();

    $urunler = app(ProductService::class);

    // Deneme sınırı 100 — cömert planın `null`'ı değil.
    for ($i = 0; $i < PlanQuotaGuard::DENEME_URUN; $i++) {
        DB::table('products')->insert([
            'uuid' => (string) Str::uuid(),
            'title' => 'Toplu '.$i,
            'slug' => 'toplu-'.$i,
            'status' => 'draft',
            'tax_rate' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    expect(fn () => $urunler->olustur(['title' => 'Sınırı Aşan']))
        ->toThrow(QuotaExceededException::class);
});

it('★ DENEMEDE personel DAVET EDİLEBİLİYOR — özellik denenebilmeli', function () {
    markaKur('kota-k.test');
    magazayiHazirla();

    /*
    | ★ BU TESTİ MEVCUT BİR TESTİN KIRILMASI DOĞURDU.
    |
    | `DENEME_PERSONEL` önce 1'di (en düşük planla aynı) ve `IzinTest`'teki
    | "sahip personel davet edebiliyor" testi kırıldı: sahip zaten 1 kişi
    | olduğu için deneme markasında kimse davet edilemiyordu.
    |
    | ⚠️ Marka, satın alma kararını vereceği 14 gün boyunca personel
    | yönetimini HİÇ DENEYEMEZDİ — tam da satın alma sebebi olan özelliği.
    */
    expect(User::count())->toBe(1);

    app(StaffService::class)->olustur(
        ['name' => 'Deneme Personeli', 'email' => 'deneme@kota.test', 'password' => 'parola-123'],
        ['Katalog'],
    );

    expect(User::count())->toBe(2);
});

it('★ MERKEZ bağlamda kota YOK — bakım komutları takılmasın', function () {
    tenancy()->end();

    /*
    | ★ BU TESTİ YAZMAK BİR HATA ORTAYA ÇIKARDI.
    |
    | İlk uygulamada "kiracı yok" ile "plan yok" AYNI değere (`null`)
    | biniyordu ve merkez bağlamda DENEME sınırı uygulanıyordu. Yani
    | `tenants:run` ile koşan her bakım komutu, tohumlayıcı ve veri taşıma
    | 100 üründen sonra kırılırdı.
    |
    | ⚠️ İki farklı anlamın aynı değerle temsil edilmesi — `null` = sınırsız
    | tuzağının kardeşi.
    */
    $kota = app(QuotaGuard::class);

    $kota->urunEklenebilirMi(999_999);
    $kota->personelEklenebilirMi(999_999);

    expect($kota->ozellikAcikMi('collections'))->toBeTrue()
        ->and($kota->ozellikAcikMi('henuz-olmayan'))->toBeTrue();
});

it('★ KOTA SERVİSTE — controller atlanabiliyor ama servis atlanamıyor', function () {
    planliMarka('kota-h.test', urunLimiti: 1);

    app(ProductService::class)->olustur(['title' => 'Tek']);

    /*
    | ★ KONTROL SERVİSTE olduğu için HTTP dışından da uygulanıyor.
    | Controller'a yazılsaydı tohumlayıcı, artisan komutu ve içe aktarma
    | yolları sınırı ATLARDI — plan satmanın anlamı kalmazdı.
    |
    | ⚠️ Bu test HTTP'den GEÇMİYOR, doğrudan servisi çağırıyor: kontrolün
    | gerçekten orada olduğunu ancak böyle ölçebiliriz.
    */
    expect(fn () => app(ProductService::class)->olustur(['title' => 'İkinci']))
        ->toThrow(QuotaExceededException::class);
});

it('★ UÇTAN: sınır aşımı 402 dönüyor', function () {
    $marka = planliMarka('kota-i.test', urunLimiti: 1);

    app(ProductService::class)->olustur(['title' => 'Tek']);

    $token = panelTokeni('kota-i.test', User::where('is_owner', true)->firstOrFail()->email);

    guardOnbelleginiTemizle();

    /*
    | ⚠️ 402 Payment Required — 403 DEĞİL. Yetki sorunu yok, marka bu
    | işlemi yapabilir; eksik olan PLAN. Panel "yükselt" ekranını buna göre
    | gösterebiliyor.
    */
    $cevap = $this->withToken($token)
        ->postJson('http://kota-i.test/panel/products', ['title' => 'İkinci'])
        ->assertStatus(402);

    expect($cevap->json('quota'))->toBe('products')
        ->and($cevap->json('limit'))->toBe(1);
});

it('★ SİLİNMİŞ ürün kotadan yer KAPLAMIYOR', function () {
    planliMarka('kota-j.test', urunLimiti: 2);

    $urunler = app(ProductService::class);

    $bir = $urunler->olustur(['title' => 'Bir']);
    $urunler->olustur(['title' => 'İki']);

    expect(fn () => $urunler->olustur(['title' => 'Üç']))
        ->toThrow(QuotaExceededException::class);

    /*
    | ⚠️ Arşive kaldırılan ürün kotadan yer kaplasaydı marka temizlik
    | yapamaz hâle gelirdi: sildiği ürün yerine yenisini ekleyemezdi.
    */
    $urunler->sil($bir);

    $yeni = $urunler->olustur(['title' => 'Üç']);

    expect($yeni->exists)->toBeTrue();
});
