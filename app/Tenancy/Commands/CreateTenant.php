<?php

namespace App\Tenancy\Commands;

use App\Domain\Identity\DefaultRoles;
use App\Models\User;
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
                            {alan-adi : Markanın alan adı (ör. marka-a.localhost)}
                            {--sahip-eposta= : Sahip kullanıcının e-postası (varsayılan: sahip@<alan-adi>)}
                            {--sahip-parola=123 : Sahip kullanıcının parolası}';

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

        // ⚠️ Buradan sonrası patlarsa ortada ÖKSÜZ kiracı kalır: satır ve şema
        // oluşmuş ama alan adı yok → marka hiçbir adresten erişilemez, üstelik
        // sorun HTTP denenene kadar fark edilmez. (1A.1'de bu gerçekten yaşandı:
        // migration hata verdi, alan adı satırına hiç sıra gelmedi.)
        // Bu yüzden hata olursa yarım kalan kiracıyı temizliyoruz.
        try {
            $tenant->domains()->create(['domain' => $alanAdi]);
        } catch (\Throwable $e) {
            $tenant->delete();   // şemayı da düşürür
            $this->error('Marka oluşturulamadı, yarım kalan kayıt temizlendi:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        /*
        | Roller ve sahip kullanıcı MARKA ŞEMASINDA oluşturulmalı.
        | `run()` kiracı bağlamını açıp kapatıyor; olmasaydı bu kayıtlar
        | merkez şemaya gitmeye çalışır ve "tablo yok" hatası alınırdı.
        */
        $sahipEposta = mb_strtolower(trim(
            (string) ($this->option('sahip-eposta') ?: "sahip@{$alanAdi}")
        ));
        $sahipParola = (string) $this->option('sahip-parola');

        $tenant->run(function () use ($ad, $sahipEposta, $sahipParola) {
            (new DefaultRoles)->kur();

            User::create([
                'name' => $ad.' Sahibi',
                'email' => $sahipEposta,
                'password' => $sahipParola,
            ])->forceFill(['is_owner' => true])->save();
            // ⚠️ `is_owner` $fillable dışında (istekle sahiplik alınamasın diye),
            // bu yüzden forceFill ile atanıyor — güvenilir yerden.
        });

        // TODO(1A.4): varsayılan ayarlar — KDV oranı, kargo ücreti, yasal metinler
        // TODO(Faz 3): durum alanı (provisioning → active) ve abonelik kaydı
        // TODO(Faz 3): tenant:delete komutu — kiracı silinince şeması düşüyor
        //              ama storage/tenant<kimlik>/ klasörü diskte kalıyor.

        $this->newLine();
        $this->line("  kimlik   : {$tenant->id}");
        $this->line("  sahip    : {$sahipEposta}  (parola: {$sahipParola})");
        $this->line('  şema     : '.$tenant->database()->getName());
        $this->line("  adres    : https://{$alanAdi}");
        $this->newLine();
        $this->warn('⚠ Sahip parolası komut satırında görünüyor — ilk girişte değiştirilmeli.');
        $this->warn('Eksik: varsayılan mağaza ayarları (1A.4).');

        return self::SUCCESS;
    }
}
