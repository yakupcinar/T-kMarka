<?php

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
