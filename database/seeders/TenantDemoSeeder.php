<?php

namespace Database\Seeders;

use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\OptionService;
use App\Domain\Catalog\ProductImageService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Option;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
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
        $this->katalog();
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

    /**
     * Katalog: kategori ağacı · eksenler · üç ürün.
     *
     * ⚠️ Ürünlerden biri TASLAK bırakılıyor. Sebebi gösterim değil sınav:
     * 1C'de sepet yazarken "taslak ürün sepete eklenebiliyor mu" sorusunu
     * elle veri üretmeden deneyebilelim. Aynı ürün 1B.5'in vitrin
     * süzgecinin de canlı kanıtı.
     */
    private function katalog(): void
    {
        $kategoriler = app(CategoryService::class);
        $eksenler = app(OptionService::class);
        $urunler = app(ProductService::class);
        $varyantlar = app(VariantService::class);

        $giyim = Category::where('slug', 'giyim')->first() ?? $kategoriler->olustur('Giyim');
        $tisort = Category::where('slug', 'tisort')->first() ?? $kategoriler->olustur('Tişört', $giyim);

        $renk = Option::where('slug', 'renk')->first() ?? $eksenler->olustur('Renk');
        $beden = Option::where('slug', 'beden')->first() ?? $eksenler->olustur('Beden');

        foreach ([['Kırmızı', '#cc0000'], ['Mavi', '#0044cc'], ['Siyah', '#111111']] as [$deger, $kod]) {
            if (! $renk->values()->where('slug', str($deger)->slug())->exists()) {
                $eksenler->degerEkle($renk, $deger, $kod);
            }
        }

        foreach (['S', 'M', 'L'] as $deger) {
            if (! $beden->values()->where('slug', str($deger)->slug())->exists()) {
                $eksenler->degerEkle($beden, $deger);
            }
        }

        // 1) Çok varyantlı, satışta — vitrinin ana örneği.
        if (! Product::where('slug', 'basic-tisort')->exists()) {
            $urun = $urunler->olustur([
                'title' => 'Basic Tişört',
                'description' => '%100 pamuk, bisiklet yaka.',
                'brand' => 'Demo',
            ], $tisort);

            $urunler->eksenleriAyarla($urun, [$renk, $beden]);
            $varyantlar->tumKombinasyonlariUret($urun, ['price' => 249.90, 'stock' => 10], 'BT');

            // ⚠️ Bir varyant bilerek TÜKENMİŞ: vitrin "en düşük fiyat"
            // hesabının tükenmişi atladığını canlı görebilelim.
            $urun->variants()->orderBy('id')->first()?->update(['stock' => 0, 'price' => 99.90]);

            $this->gorselEkle($urun, '#cc0000');
            $urunler->durumDegistir($urun->refresh(), ProductStatus::Active);
        }

        // 2) Tek varyantlı (eksensiz) — 1B-K1'in canlı örneği.
        if (! Product::where('slug', 'deri-cuzdan')->exists()) {
            $urun = $urunler->olustur([
                'title' => 'Deri Cüzdan',
                'description' => 'Hakiki deri, 6 kart gözü.',
            ], $giyim);

            $varyantlar->ekle($urun, ['sku' => 'DC-1', 'price' => 449.90, 'cost_price' => 180, 'stock' => 4]);
            $this->gorselEkle($urun, '#8b5a2b');
            $urunler->durumDegistir($urun->refresh(), ProductStatus::Active);
        }

        // 3) TASLAK — vitrinde görünmemeli.
        if (! Product::where('slug', 'yaklasan-koleksiyon')->exists()) {
            $urun = $urunler->olustur(['title' => 'Yaklaşan Koleksiyon'], $tisort);
            $varyantlar->ekle($urun, ['sku' => 'YK-1', 'price' => 599.90, 'stock' => 3]);
        }

        $this->command?->line('  katalog  : 2 satışta + 1 taslak ürün · '.Category::count().' kategori');
    }

    /**
     * Tek renk bir PNG üretip ürüne ekler.
     *
     * Gerçek dosya üretiliyor çünkü görsel yolu (marka klasörü) da
     * gösterim verisinin parçası — 1C/1D'de vitrin denerken boş kare
     * görmeyelim.
     */
    private function gorselEkle(Product $urun, string $renkKodu): void
    {
        /** @var list<int> $parcalar */
        $parcalar = sscanf($renkKodu, '#%02x%02x%02x') ?? [0, 0, 0];

        // ⚠️ `max/min` yalnızca statik analiz için değil: bozuk bir renk
        // kodu geldiğinde imagecolorallocate sessizce siyah döner, burada
        // aralığa çekiliyor.
        $kanal = fn (int $sira): int => max(0, min(255, $parcalar[$sira] ?? 0));

        $resim = imagecreatetruecolor(600, 600);
        imagefill($resim, 0, 0, (int) imagecolorallocate($resim, $kanal(0), $kanal(1), $kanal(2)));

        $gecici = tempnam(sys_get_temp_dir(), 'tohum').'.png';
        imagepng($resim, $gecici);
        imagedestroy($resim);

        app(ProductImageService::class)->yukle(
            $urun,
            new UploadedFile($gecici, 'demo.png', 'image/png', test: true),
            alt: $urun->title,
        );
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
