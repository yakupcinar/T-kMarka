<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\SettingsService;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;
use App\Models\Customer;
use App\Models\User;
use App\Platform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    Tenant::all()->each->delete();

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
    $tenant = Tenant::create(['name' => $ad]);
    $tenant->domains()->create(['domain' => $alanAdi]);

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
