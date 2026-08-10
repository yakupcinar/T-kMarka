<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * MERKEZ şema tohumlayıcısı — `php artisan db:seed`.
 *
 * ⚠️ Burada marka verisi ÜRETİLMEZ. Merkez şemada yalnızca `tenants` ve
 * `domains` var; `users`, `customers`, `settings` gibi tablolar marka
 * şemasında. Buraya `User::factory()` yazılsaydı "tablo yok" hatası
 * alınırdı — ya da merkezde bir gün aynı adda bir tablo doğarsa veri
 * sessizce YANLIŞ ŞEMAYA yazılırdı.
 *
 * Marka verisi için: `php artisan tenants:seed`
 * (config/tenancy.php → seeder_parameters, [TenantDemoSeeder]'ı çağırır).
 *
 * Yeni marka açmak zaten bir tohumlama işi değil, bir kurulum işi:
 * `php artisan tenant:create "Ad" alan-adi.localhost`
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->command?->info('Merkez şemada tohumlanacak veri yok.');
        $this->command?->line('  marka verisi  : php artisan tenants:seed');
        $this->command?->line('  yeni marka    : php artisan tenant:create "Ad" alan-adi.localhost');
    }
}
