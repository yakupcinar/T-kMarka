<?php

namespace App\Console\Commands;

use App\Platform\Models\PlatformUser;
use Illuminate\Console\Command;

/**
 * Platform yöneticisi açar. (3C)
 *
 * ⚠️ HTTP UCU YOK, bilerek: uç olsaydı internetteki herkes kendine bütün
 * markalara erişen bir hesap yaratabilirdi. Bu kimlik yalnızca sunucuya
 * erişebilen kişi tarafından açılıyor.
 *
 * ⚠️ MERKEZ bağlamda çalışıyor — `tenants:run` ile SARILMAZ. Diğer
 * komutlarımızın tersi: onlar marka verisine dokunuyordu, bu merkeze.
 */
class CreatePlatformUser extends Command
{
    protected $signature = 'platform:kullanici
                            {ad : Yöneticinin adı}
                            {eposta : Giriş e-postası}
                            {--parola= : Parola (verilmezse rastgele üretilir)}';

    protected $description = 'Platform yöneticisi açar (merkez bağlamda).';

    public function handle(): int
    {
        $eposta = strtolower(trim((string) $this->argument('eposta')));

        if (PlatformUser::where('email', $eposta)->exists()) {
            $this->error("Bu e-posta zaten kayıtlı: {$eposta}");

            return self::FAILURE;
        }

        /*
        | ⚠️ Parola verilmezse RASTGELE üretiliyor, sabit varsayılan YOK.
        | `tenant:create`'te sahip parolası `123` — o geliştirme kolaylığı
        | ve Faz 3'te kaldırılacak; burada en baştan yapmıyoruz çünkü bu
        | hesap bütün markalara erişiyor.
        */
        $parola = (string) ($this->option('parola') ?: bin2hex(random_bytes(12)));

        $kullanici = PlatformUser::create([
            'name' => (string) $this->argument('ad'),
            'email' => $eposta,
            'password' => $parola,
        ]);

        $this->newLine();
        $this->line("  kimlik : {$kullanici->uuid}");
        $this->line("  eposta : {$kullanici->email}");
        $this->line("  parola : {$parola}");
        $this->newLine();
        $this->warn('⚠ Parola bir daha gösterilmeyecek — şimdi kaydedin.');
        $this->warn('⚠ Bu hesap BÜTÜN markalara erişiyor.');

        return self::SUCCESS;
    }
}
