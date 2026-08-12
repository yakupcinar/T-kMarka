<?php

namespace App\Tenancy\Commands;

use App\Platform\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Var olan bir markaya İKİNCİ bir alan adı bağlar.
 *
 * İlk kullanım yeri 1E.7.3: ngrok tüneli geçici bir alan adı veriyor ve
 * webhook ucu kiracıyı ALAN ADINDAN çözüyor (1E.4).
 *
 * ⚠️ Adres kayıtlı değilse istek 404 alır ve TAHSİLAT HİÇ İŞLENMEZ —
 * sağlayıcı üç kez dener, üçü de düşer, para çekilmiş kalır.
 *
 * ⚠️ Alan adı KÜÇÜK HARF olmak zorunda: `domains` tablosunda
 * `CHECK (domain = lower(domain))` var (0.5). Komut kendisi küçültüyor.
 */
class AttachDomain extends Command
{
    protected $signature = 'tenant:domain
                            {alan-adi : Markanın MEVCUT alan adı (ör. marka-a.localhost)}
                            {yeni-alan-adi : Eklenecek alan adı (ör. abc.ngrok-free.app)}
                            {--kaldir : Eklemek yerine KALDIR}';

    protected $description = 'Var olan markaya ikinci bir alan adı bağlar (ör. ngrok tüneli).';

    public function handle(): int
    {
        $mevcut = mb_strtolower((string) $this->argument('alan-adi'));
        $yeni = mb_strtolower((string) $this->argument('yeni-alan-adi'));

        /*
        | ⚠️ `whereHas('domains', …)` yerine iki adım: ilişki paketin
        | trait'inden geliyor ve statik analiz onu göremiyor. Zaten bu
        | biçim daha açık — önce alan adı satırını bul, sonra markasını.
        */
        $kayit = Domain::where('domain', $mevcut)->first();

        $marka = $kayit === null
            ? null
            : Tenant::find($kayit->tenant_id);

        if ($marka === null) {
            $this->error("'{$mevcut}' alan adına sahip bir marka yok.");

            return self::FAILURE;
        }

        if ($this->option('kaldir')) {
            Domain::where('domain', $yeni)->where('tenant_id', $marka->getTenantKey())->delete();
            $this->info("Kaldırıldı: {$yeni}");

            return self::SUCCESS;
        }

        /*
        | ⚠️ Alan adı BAŞKA markada olabilir — paket bunu istisnayla
        | engelliyor ama mesajı teknik. Önce bakıp anlaşılır hata veriyoruz.
        */
        $sahibi = Domain::where('domain', $yeni)->first();

        if ($sahibi !== null) {
            if ($sahibi->tenant_id === $marka->getTenantKey()) {
                $this->info("Zaten bağlı: {$yeni}");

                return self::SUCCESS;
            }

            $this->error("'{$yeni}' başka bir markaya bağlı.");

            return self::FAILURE;
        }

        $marka->domains()->create(['domain' => $yeni]);

        $this->info("Bağlandı: {$yeni} → {$mevcut}");
        $this->line('⚠️ Caddy de bu adresi tanımalı; docker/Caddyfile ve NGROK_DOMAIN değişkenine bak.');

        return self::SUCCESS;
    }
}
