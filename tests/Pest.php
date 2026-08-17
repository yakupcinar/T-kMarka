<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Payment\FakePaymentProvider;
use App\Domain\Payment\PaymentService;
use App\Domain\Settings\DefaultSettings;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Enums\TenantStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Platform\Models\Tenant;
use App\Platform\TenantProvisioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/*
| Feature klasöründeki her test:
|
|   - Tests\TestCase'i kullanır  → Laravel uygulaması ayağa kalkar,
|                                  $this->get('/') gibi istekler atılabilir
|   - RefreshDatabase kullanır   → her test transaction içinde koşar,
|                                  bitince ROLLBACK. Testler birbirinin
|                                  verisine bulaşmaz.
|
| Unit klasöründe uygulama ve veritabanı YOK — orası saf PHP mantığı
| içindir, hızlı çalışır.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Kiracı testleri — RefreshDatabase KULLANMAZ.
|
| Sebep: kiracı oluşturmak CREATE SCHEMA + ayrı bir bağlantıda migration
| çalıştırmak demek. RefreshDatabase her testi transaction'a sardığı için
| şema commit edilmemiş oluyor; "tenant" bağlantısı onu göremiyor ve
| "Invalid schema name" hatası alınıyor.
|
| Bunun yerine: transaction yok, temizliği her testten sonra kendimiz
| yapıyoruz. Kiracı silinince şeması da düşüyor (0.5/6'da doğrulandı).
*/
pest()->extend(TestCase::class)->in('Tenancy');

uses()->afterEach(function () {
    tenancy()->end();

    /*
    | ⚠️ Kiracı silinince ŞEMASI düşüyor ama DOSYA KLASÖRÜ diskte kalıyor
    | (`storage/tenant<id>/`). Ölçüldü: 158 klasör birikmişti.
    |
    | Bu, `tenant:delete` için Faz 3'e not düştüğümüz boşluğun test
    | tarafındaki yansıması — orada da aynı temizlik gerekecek.
    */
    $kiracilar = Tenant::all();

    /** @var list<string> $klasorler */
    $klasorler = [];

    foreach ($kiracilar as $kiraci) {
        /** @var Tenant $kiraci */
        $klasorler[] = storage_path("tenant{$kiraci->id}");
    }

    $kiracilar->each->delete();

    foreach ($klasorler as $klasor) {
        if (is_dir($klasor)) {
            File::deleteDirectory($klasor);
        }
    }

    // Testler gerçek Redis kullanıyor (ayrı veritabanı: 15).
    // Kalan anahtarlar sonraki teste sızmasın.
    Cache::flush();
})->in('Tenancy');

/**
 * Test için kiracı açar: tenants satırı + şema + marka tabloları + alan adı.
 * `tenant:create` komutunun test karşılığı.
 */
function kiraciOlustur(string $alanAdi, string $ad = 'Test Markası'): Tenant
{
    /*
    | ⚠️ GERÇEK MARKA NE ALIYORSA TEST MARKASI DA ONU ALMALI — bu satırlar
    | `tenant:create` ile HİZALI tutulmak zorunda (1E.4'te ayrışmışlardı:
    | `markaKur` DefaultSettings'i çalıştırmıyordu ve testler gerçekte
    | olmayan bir markayı ölçüyordu).
    |
    | Durum ve deneme bitişi olmadan açılsaydı test markaları `status`
    | NULL doğar, panel kapısı kontrolleri testte hiç sınanmazdı.
    */
    $tenant = Tenant::create([
        'name' => $ad,
        'status' => TenantStatus::Trial,
        'trial_ends_at' => now()->addDays(TenantProvisioning::DENEME_GUN),
    ]);
    /*
    | ⚠️ `verified_at` DOLU — `TenantProvisioning` ile HİZALI. Boş
    | bırakılsaydı test markaları gerçek markalardan farklı doğar ve
    | `ask` ucu testleri yanlış şeyi ölçerdi (1E.4'ün dersi).
    */
    $tenant->domains()->create(['domain' => $alanAdi, 'verified_at' => now()]);

    return $tenant;
}

/**
 * Guard önbelleğini temizler.
 *
 * ⚠️ Yalnızca TEST ortamında gerekli. Gerçek HTTP'de her istek yeni bir PHP
 * süreci olduğu için guard sıfırdan kurulur. Testlerde ise bütün istekler
 * aynı süreçte koşuyor ve konteynerdeki guard nesnesi, bir önceki istekte
 * çözdüğü kullanıcıyı önbellekte tutuyor — bu da bir sonraki isteğe sızıyor.
 *
 * Doğrulandı: aynı senaryo gerçek HTTP'de (curl) doğru davranıyor;
 * A markasının token'ı B markasında 401 alıyor.
 */
function guardOnbelleginiTemizle(): void
{
    auth()->forgetGuards();
}

/**
 * Marka kurar: kiracı + varsayılan roller + sahip kullanıcı.
 *
 * `tenant:create` komutunun test karşılığı. Kiracı bağlamı AÇIK bırakılır.
 *
 * @return array{tenant: Tenant, sahip: User}
 */
function markaKur(string $alanAdi = 'marka-a.test'): array
{
    $tenant = kiraciOlustur($alanAdi);
    tenancy()->initialize($tenant);

    (new DefaultRoles)->kur();

    /*
    | ⚠️ GERÇEK MARKA NE ALIYORSA TEST MARKASI DA ONU ALIYOR.
    |
    | Önceden yalnızca roller kuruluyordu; `tenant:create`'in kurduğu
    | varsayılan ayarlar ve yasal taslaklar testte YOKTU. Yani testler,
    | canlıda hiç var olmayan bir marka biçimi üzerinde koşuyordu.
    |
    | 1E.1'de ısırdı: sahte sağlayıcının imza anahtarı `DefaultSettings`
    | tarafından kuruluyor, testte kurulmadığı için imza BOŞ ANAHTARLA
    | üretiliyordu — doğrulama "çalışıyor" görünüyordu ama hiçbir şey
    | korumuyordu. 1D.6'nın dersinin aynısı: test ortamı gerçekten
    | ayrılırsa test yeşil kalır, gerçek bozulur.
    */
    app(DefaultSettings::class)->kur('Test Markası');

    $sahip = User::factory()->sahip()->create([
        'email' => 'sahip@'.$alanAdi,
        'password' => 'sifre1234',
    ]);

    return ['tenant' => $tenant, 'sahip' => $sahip];
}

/**
 * Panel token'ı alır.
 *
 * `test()` Pest'in çalışma anındaki test örneğini veriyor. Statik analiz bu
 * bağlamayı göremediği için `postJson` tanımsız görünüyor — `phpstan.neon`'da
 * YALNIZCA BU DOSYA için istisna tanımlı.
 *
 * Test örneğini parametre olarak almak da denendi: Pest testlerinde `$this`
 * `PHPUnit\Framework\TestCase` olarak görünüyor, `Tests\TestCase` beklentisiyle
 * uyuşmuyor ve sorun 1 hatadan 8'e çıkıyor.
 */
function panelTokeni(string $alanAdi, string $eposta, string $parola = 'sifre1234'): string
{
    guardOnbelleginiTemizle();

    return test()->postJson("http://{$alanAdi}/panel/login", [
        'email' => $eposta,
        'password' => $parola,
    ])->json('token');
}

/**
 * Müşteri açar ve token'ını alır.
 *
 * `panelTokeni` ile aynı sebeple BU DOSYADA: `test()` yardımcısını
 * kullanıyor ve statik analiz istisnası yalnızca bu dosyaya tanımlı.
 * Test dosyasına yazılsaydı ya analiz kırılırdı ya da istisnayı tüm
 * testlere yayıp gerçek yazım hatalarını görünmez kılardık.
 *
 * @return array{musteri: Customer, token: string}
 */
function musteriTokeni(string $alanAdi, string $eposta, string $parola = 'sifre1234'): array
{
    guardOnbelleginiTemizle();

    $musteri = Customer::factory()->create(['email' => $eposta, 'password' => $parola]);

    $token = (string) test()->postJson("http://{$alanAdi}/api/login", [
        'email' => $eposta,
        'password' => $parola,
    ])->json('token');

    return ['musteri' => $musteri, 'token' => $token];
}

/**
 * Mesafeli satış sözleşmesinin içermek zorunda olduğu satıcı bilgilerini
 * doldurur — yer tutucular dolabilsin ve hazırlık denetimi geçebilsin diye.
 *
 * ⚠️ Burada duruyor çünkü ÜÇ test dosyası buna ihtiyaç duyuyor. Önce her
 * dosyada ayrı bir kopya vardı (`magazayiHazirla`, `sirketBilgileriniDoldur`);
 * biri değişince diğerini güncellemeyi unutmak an meselesiydi.
 */
function sirketBilgileriniDoldur(): void
{
    $ayarlar = app(SettingsService::class);

    foreach ([
        'name' => 'Test Markası',
        'legal_name' => 'Test Ticaret Ltd. Şti.',
        'tax_number' => '1234567890',
        'tax_office' => 'Kadıköy',
        'address' => 'Test Cad. No:1',
        'phone' => '+902161112233',
        'contact_email' => 'destek@test.com',
    ] as $anahtar => $deger) {
        $ayarlar->yaz(SettingGroup::Store, $anahtar, $deger);
    }
}

/**
 * Mağazayı yayına hazır hâle getirir: şirket bilgileri + üç yasal metnin
 * yayınlanmış sürümü. Mağazayı AÇMIYOR — açmak testin kendi işi.
 */
function magazayiHazirla(): void
{
    sirketBilgileriniDoldur();

    $belgeler = app(LegalDocumentService::class);

    foreach (LegalDocumentType::cases() as $tur) {
        $belgeler->taslagaYaz($tur, "{$tur->value} metni");
        $belgeler->yayinla($tur);
    }
}

/**
 * Ödemesi BAŞLATILMIŞ sipariş üretir ve sağlayıcı referansını döndürür.
 *
 * ⚠️ Pest.php'de: birden çok dosya kullanıyor. Test dosyasında kalsaydı
 * diğer dosyalar tek başına koşturulunca "tanımsız fonksiyon" verirdi.
 *
 * @return array{siparis: Order, varyant: ProductVariant, referans: string, tutar: string}
 */
function bildirimeHazirSiparis(string $alanAdi): array
{
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi($alanAdi);
    app(StorePublication::class)->yayinla();

    $sonuc = app(PaymentService::class)->baslat($siparis, "http://{$alanAdi}/odeme/donus");

    return [
        'siparis' => $siparis,
        'varyant' => $varyant,
        'referans' => $sonuc->saglayiciReferansi,
        'tutar' => (string) $siparis->grand_total,
    ];
}

/**
 * Sağlayıcının imzalı ödeme bildirimini webhook ucuna gönderir.
 *
 * ⚠️ Pest.php'de duruyor çünkü `test()` çalışma anında test örneğini
 * döndürüyor ve statik analiz bu bağlamayı göremiyor; istisna YALNIZCA
 * bu dosya için tanımlı (phpstan.neon). Test dosyasında kalsaydı ya
 * analiz kırılırdı ya da istisnanın kapsamı genişletilirdi — ikincisi
 * gerçek yazım hatalarını da görünmez yapardı.
 *
 * ⚠️ İmza ÇAĞIRAN BAĞLAMDA üretiliyor: anahtar marka başına ayrı.
 *
 * @return TestResponse<Response>
 */
function bildirimGonder(
    string $alanAdi,
    string $siparisNo,
    string $referans,
    string $tutar,
    bool $basarili = true,
): TestResponse {
    ['yuk' => $yuk, 'imza' => $imza] = app(FakePaymentProvider::class)
        ->bildirim($siparisNo, $referans, $tutar, $basarili);

    /** @var TestResponse<Response> $cevap */
    $cevap = test()->withHeader('X-Fake-Signature', $imza)
        ->postJson("http://{$alanAdi}/webhooks/payment", $yuk);

    return $cevap;
}

/**
 * İki satırlı, ÖDENMİŞ bir sipariş üretir.
 *
 * ⚠️ Pest.php'de: birden çok dosya kullanıyor. Test dosyasında kalsaydı
 * diğerleri tek başına koşturulunca "tanımsız fonksiyon" verirdi —
 * üçüncü kez aynı tuzağa düşüldü.
 */
function sevkiyatlikSiparis(string $alanAdi): Order
{
    markaKur($alanAdi);
    magazayiHazirla();

    $urunler = app(ProductService::class);
    $varyantlar = app(VariantService::class);
    $sepetler = app(CartService::class);

    $tisort = $urunler->olustur(['title' => 'Tişört']);
    $vTisort = $varyantlar->ekle($tisort, ['sku' => 'TS-1', 'price' => 100, 'stock' => 20]);
    $urunler->durumDegistir($tisort->refresh(), ProductStatus::Active);

    $kupa = $urunler->olustur(['title' => 'Kupa']);
    $vKupa = $varyantlar->ekle($kupa, ['sku' => 'KP-1', 'price' => 50, 'stock' => 20]);
    $urunler->durumDegistir($kupa->refresh(), ProductStatus::Active);

    $sepet = $sepetler->misafirSepetiOlustur();
    $sepetler->ekle($sepet, $vTisort, 3);
    $sepetler->ekle($sepet, $vKupa, 2);

    $sozlesme = app(LegalDocumentService::class)
        ->guncelSurum(LegalDocumentType::DistanceSales);

    $odeme = app(CheckoutService::class);
    $siparis = $odeme->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    return $odeme->odemeBasarili($siparis);
}

/**
 * Ödenmemiş (pending) tek satırlık sipariş + varyantı üretir.
 *
 * ⚠️ Pest.php'de duruyor çünkü birden çok dosya kullanıyor. Test
 * dosyasında tanımlı kalsaydı diğer dosyalar TEK BAŞINA koşturulunca
 * "tanımsız fonksiyon" verirdi — tüm süitte görünmeyen, dosya yükleme
 * sırasına bağlı sessiz bağımlılık.
 *
 * @return array{siparis: Order, varyant: ProductVariant}
 */
function odemeAsamasiSiparisi(string $alanAdi, int $stok = 5): array
{
    markaKur($alanAdi);
    magazayiHazirla();

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 100, 'stock' => $stok]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    return ['siparis' => $siparis, 'varyant' => $varyant];
}

/**
 * Ödenmemiş sipariş — ama KAYITLI MÜŞTERİYE bağlı.
 *
 * ⚠️ `odemeAsamasiSiparisi` misafir siparişi üretiyor; anonimleştirme
 * testinde müşteri bağının koptuğunu görebilmek için hesap gerekiyor.
 *
 * @return array{siparis: Order, varyant: ProductVariant}
 */
function odemeAsamasiSiparisiMusteriyle(string $alanAdi, Customer $musteri): array
{
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sepet = app(CartService::class)->musteriSepeti($musteri);
    app(CartService::class)->ekle($sepet, $varyant, 1);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    return ['siparis' => $siparis, 'varyant' => $varyant];
}

/**
 * Örnek ödeme gövdesi.
 *
 * ⚠️ Buraya TAŞINDI (1E.1): `CheckoutTest.php` içinde tanımlıydı ve
 * onu kullanan diğer dosyalar tek başına koşturulduğunda "tanımsız
 * fonksiyon" veriyordu. Tüm süit koşarken sorun görünmüyordu çünkü
 * dosyalar alfabetik yükleniyor — tam olarak sessiz türden bir bağımlılık.
 *
 * @return array{email: string, legal_version_id: int, shipping: array<string, string|null>}
 */
function odemeVerisi(int $sozlesmeId): array
{
    return [
        'email' => 'ayse@ornek.test',
        'legal_version_id' => $sozlesmeId,
        'shipping' => [
            'full_name' => 'Ayşe Yılmaz',
            'phone' => '+905321112233',
            'city' => 'İstanbul',
            'district' => 'Kadıköy',
            'neighborhood' => 'Caferağa',
            'line1' => 'Moda Cad. No:12',
            'line2' => null,
            'postal_code' => '34710',
        ],
    ];
}

/**
 * Örnek adres gövdesi.
 *
 * Kütle atama testinde `customer_id` (int) de gönderiliyor; bu yüzden
 * değer tipi `mixed`.
 *
 * @param  array<string, mixed>  $degisiklikler
 * @return array<string, mixed>
 */
function ornekAdres(array $degisiklikler = []): array
{
    return array_merge([
        'title' => 'Ev',
        'full_name' => 'Ayşe Yılmaz',
        'phone' => '+905321112233',
        'city' => 'İstanbul',
        'district' => 'Kadıköy',
        'neighborhood' => 'Caferağa',
        'line1' => 'Moda Cad. No:12 D:4',
        'postal_code' => '34710',
    ], $degisiklikler);
}
