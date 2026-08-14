<?php

namespace App\Console\Commands;

use App\Domain\Settings\DefaultsBackfill;
use Illuminate\Console\Command;

/**
 * Markadaki eksik varsayılanları tamamlar. (3A)
 *
 * ★ Faz 1'den devredilen borç. `tenant:create` yeni markaya varsayılanları
 * kuruyor ama önceden açılmış markalara kimse gitmiyor; yeni bir ayar
 * eklendiğinde eski markalar onsuz kalıyor ve bu çoğu zaman HATA VERMİYOR.
 *
 * ⚠️ MARKA VERİSİNE DOKUNUYOR — `tenants:run` ile:
 *
 *     php artisan tenants:run "marka:eksikleri-tamamla --kuru"   ← önce BAK
 *     php artisan tenants:run marka:eksikleri-tamamla            ← sonra YAP
 *
 * ⚠️ Var olan hiçbir ayarı DEĞİŞTİRMİYOR — gerekçesi [DefaultsBackfill]'de.
 */
class BackfillDefaults extends Command
{
    /**
     * ⚠️ `--kuru` varsayılan DEĞİL ama önce o çalıştırılmalı. Zorunlu
     * yapılsaydı zamanlanmış kullanımda engel olurdu; varsayılan yapılsaydı
     * komut hiçbir şey yapmadan "başarılı" döner ve o daha kötü.
     */
    protected $signature = 'marka:eksikleri-tamamla {--kuru : Yalnızca göster, hiçbir şey yazma}';

    protected $description = 'Markadaki eksik varsayılan ayar, yasal taslak ve rolleri tamamlar (tenants:run ile çalıştırın).';

    public function handle(DefaultsBackfill $tamamlayici): int
    {
        if (! tenancy()->initialized) {
            $this->error('Bu komut marka bağlamında çalışmalı.');
            $this->line('Kullanım: php artisan tenants:run marka:eksikleri-tamamla');

            return self::FAILURE;
        }

        /*
        | ⚠️ Marka adı MERKEZ kayıttan geliyor. `store.name` ayarı eksikse
        | onunla dolduruluyor; buradan alınmasaydı ad "Bilinmeyen" gibi bir
        | yer tutucuyla yazılır ve marka vitrininde onu görürdü.
        */
        $markaAdi = (string) (tenant('name') ?? 'Marka');

        $eksik = $tamamlayici->eksikler($markaAdi);
        $toplam = count($eksik['settings']) + count($eksik['drafts']) + count($eksik['roles']);

        if ($toplam === 0) {
            return self::SUCCESS;
        }

        $this->line(sprintf('%s — %d eksik:', tenant('id'), $toplam));

        foreach (['settings' => 'ayar', 'drafts' => 'yasal taslak', 'roles' => 'rol'] as $alan => $etiket) {
            foreach ($eksik[$alan] as $ad) {
                $this->line("  {$etiket}: {$ad}");
            }
        }

        if ($this->option('kuru')) {
            $this->comment('  (kuru çalışma — hiçbir şey yazılmadı)');

            return self::SUCCESS;
        }

        $yapilan = $tamamlayici->tamamla($markaAdi);

        $this->info(sprintf(
            '  → %d ayar, %d taslak, %d rol tamamlandı.',
            $yapilan['settings'],
            $yapilan['drafts'],
            $yapilan['roles'],
        ));

        return self::SUCCESS;
    }
}
