<?php

namespace App\Tenancy\Commands;

use App\Platform\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Yeni bir marka (kiracı) açar.
 *
 * M-1'in şartı: "her yeni müşteri elle kurulum gerektiriyorsa ürün değil,
 * taslaktır." Bu komut o kurulumun tamamı olacak.
 */
class CreateTenant extends Command
{
    /**
     * Komutun adı ve alacağı bilgiler.
     *
     * Süslü parantez içindekiler ARGÜMAN: komutu çalıştıran kişi bunları
     * vermek zorunda. İki nokta üstünden sonrası `--help` çıktısında görünen
     * açıklama.
     */
    protected $signature = 'tenant:create
                            {ad : Markanın adı (ör. "A Markası")}
                            {alan-adi : Markanın alan adı (ör. marka-a.localhost)}';

    protected $description = 'Yeni marka açar: şema oluşturur, tablolarını kurar, alan adını bağlar.';

    /**
     * Komut çalıştırıldığında burası koşar.
     * Dönüş değeri kabuk için: 0 başarılı, 1 hatalı.
     */
    public function handle(): int
    {
        $ad = trim((string) $this->argument('ad'));
        $alanAdi = strtolower(trim((string) $this->argument('alan-adi')));

        if ($ad === '' || $alanAdi === '') {
            $this->error('Marka adı ve alan adı boş olamaz.');

            return self::FAILURE;
        }

        // Aynı alan adı iki markaya bağlanamaz: kapı görevlisi (middleware)
        // hangisine gideceğini bilemez. Veritabanında da unique kısıtı var,
        // ama hatayı burada yakalayıp anlaşılır mesaj veriyoruz.
        if (Domain::where('domain', $alanAdi)->exists()) {
            $this->error("Bu alan adı zaten başka bir markaya kayıtlı: {$alanAdi}");

            return self::FAILURE;
        }

        $this->info("Marka oluşturuluyor: {$ad}");

        // Tenant::create() sadece bir satır eklemiyor — paketin olay zinciri
        // devreye giriyor ve arkasından ŞEMA oluşturuluyor, marka
        // migration'ları çalıştırılıyor.
        // Zincir: app/Providers/TenancyServiceProvider.php → events()
        $tenant = Tenant::create(['name' => $ad]);

        $tenant->domains()->create(['domain' => $alanAdi]);

        // TODO(1A): varsayılan ayarlar — KDV oranı, kargo ücreti, yasal metinler
        // TODO(1A): sahip kullanıcı oluştur ve e-posta ile davet gönder
        // TODO(Faz 3): durum alanı (provisioning → active) ve abonelik kaydı
        // TODO(Faz 3): tenant:delete komutu — kiracı silinince şeması düşüyor
        //              ama storage/tenant<kimlik>/ klasörü diskte kalıyor.

        $this->newLine();
        $this->line("  kimlik   : {$tenant->id}");
        $this->line('  şema     : '.$tenant->database()->getName());
        $this->line("  adres    : https://{$alanAdi}");
        $this->newLine();
        $this->warn('Eksik: varsayılan ayarlar ve sahip kullanıcı (Faz 1A).');

        return self::SUCCESS;
    }
}
