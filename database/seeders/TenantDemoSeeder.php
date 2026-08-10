<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * MARKA şeması için gösterim verisi — `php artisan tenants:seed`.
 *
 * Amaç: sonraki bloklarda elle veri üretmemek. 1B'de ürün eklerken hazır
 * yetkili personel, 1C'de sepet denerken hazır müşteri ve adres bulunacak.
 *
 * ⚠️ Rol ve sahip kullanıcı BURADA ÜRETİLMEZ — onları `tenant:create`
 * kuruyor. İki yerde üretilseydi biri değişince diğerini güncellemeyi
 * unutmak an meselesiydi; ayrıca "marka nasıl doğar" sorusunun iki farklı
 * cevabı olurdu.
 *
 * ⚠️ Tekrar çalıştırılabilir: her kayıt `firstOrCreate` ile açılıyor.
 * Olmasaydı ikinci çalıştırma ya e-posta benzersizliğinde patlardı ya da
 * kopya müşteri üretirdi.
 */
class TenantDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Gösterim hesaplarının ortak parolası. */
    private const PAROLA = 'sifre1234';

    public function run(): void
    {
        /*
        | ⚠️ CANLIDA ÇALIŞMAZ.
        |
        | Gösterim verisi bilinen parolalarla hesap açıyor. Canlıda
        | koşsaydı gerçek bir markanın paneline "katalog@ornek.test /
        | sifre1234" ile girilebilirdi.
        */
        if (app()->isProduction()) {
            throw new RuntimeException(
                'TenantDemoSeeder canlı ortamda çalıştırılamaz — bilinen parolalarla hesap açıyor.'
            );
        }

        /*
        | Kiracı bağlamı AÇIK olmalı. `tenants:seed` bunu kendisi açıyor;
        | doğrudan `db:seed --class=TenantDemoSeeder` denenirse kayıtlar
        | merkez şemaya gitmeye çalışır ve "tablo yok" hatası alınır.
        | Kontrolü açıkça yapıyoruz ki hata anlaşılır olsun.
        */
        if (! tenancy()->initialized) {
            throw new RuntimeException(
                'Kiracı bağlamı açık değil. Kullanım: php artisan tenants:seed'
            );
        }

        $this->personel();
        $this->musteriler();
    }

    /** İki personel, farklı rollerde — yetki farkı gerçekten görülebilsin. */
    private function personel(): void
    {
        $eslesme = [
            'katalog@ornek.test' => ['Katalogcu Kemal', 'Katalog'],
            'destek@ornek.test' => ['Destekçi Deniz', 'Sipariş & Destek'],
        ];

        foreach ($eslesme as $eposta => [$ad, $rolAdi]) {
            $personel = User::firstOrCreate(
                ['email' => $eposta],
                ['name' => $ad, 'password' => self::PAROLA],
            );

            $rol = Role::where('name', $rolAdi)->first();

            // Rol yoksa sessizce geçmiyoruz: `tenant:create` çalışmamış
            // demektir ve bunu bilmek gerekiyor.
            if ($rol === null) {
                throw new RuntimeException(
                    "'{$rolAdi}' rolü yok. Önce marka `tenant:create` ile kurulmalı."
                );
            }

            $personel->roles()->sync([$rol->id]);
        }
    }

    /** İki müşteri: biri iki adresli, biri adressiz (yeni kayıt hâli). */
    private function musteriler(): void
    {
        $ayse = Customer::firstOrCreate(
            ['email' => 'ayse@ornek.test'],
            ['name' => 'Ayşe Yılmaz', 'password' => self::PAROLA, 'accepts_marketing' => true],
        );

        Customer::firstOrCreate(
            ['email' => 'mehmet@ornek.test'],
            ['name' => 'Mehmet Demir', 'password' => self::PAROLA],
        );

        $adresler = [
            ['title' => 'Ev', 'city' => 'İstanbul', 'district' => 'Kadıköy',
                'neighborhood' => 'Caferağa', 'line1' => 'Moda Cad. No:12 D:4', 'postal_code' => '34710'],
            ['title' => 'İş', 'city' => 'İstanbul', 'district' => 'Şişli',
                'neighborhood' => 'Mecidiyeköy', 'line1' => 'Büyükdere Cad. No:80 Kat:5', 'postal_code' => '34394'],
        ];

        foreach ($adresler as $adres) {
            /*
            | ⚠️ İlişki üzerinden: `customer_id` $fillable dışında ve zaten
            | dışarıdan verilmemeli (1A.1). Aynı desen adres uçlarında da
            | kullanılıyor (1A.5).
            */
            $ayse->addresses()->firstOrCreate(
                ['title' => $adres['title']],
                $adres + ['full_name' => $ayse->name, 'phone' => '+905321112233'],
            );
        }

        $this->command?->info('Gösterim verisi hazır — parola: '.self::PAROLA);
        $this->command?->line('  personel : katalog@ornek.test · destek@ornek.test');
        $this->command?->line('  müşteri  : ayse@ornek.test ('.Address::count().' adres) · mehmet@ornek.test');
    }
}
