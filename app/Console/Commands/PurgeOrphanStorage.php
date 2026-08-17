<?php

namespace App\Console\Commands;

use App\Platform\TenantPurge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Karşılığı olmayan marka klasörlerini siler. (3G)
 *
 * ★ 1A'dan DEVREDİLEN BORÇ: "kiracı silinince şeması düşüyor ama
 * `storage/tenant<kimlik>/` klasörü diskte kalıyor."
 *
 * ⚠️ ÖLÇÜLDÜ: 40 klasör, 2 gerçek marka — 38 öksüz.
 *
 * ★ VARSAYILAN: HİÇBİR ŞEY SİLMEZ. Silme geri alınamaz.
 */
class PurgeOrphanStorage extends Command
{
    protected $signature = 'marka:oksuz-dosyalari-temizle
                            {--onayla : GERÇEKTEN sil — bu bayrak olmadan yalnızca gösterir}
                            {--kok= : Taranacak kök klasör (varsayılan: storage/)}';

    protected $description = 'Veritabanında karşılığı olmayan marka klasörlerini siler (varsayılan: yalnızca gösterir).';

    public function handle(TenantPurge $temizlik): int
    {
        $kok = $this->option('kok');

        $oksuzler = $temizlik->oksuzKlasorler(is_string($kok) && $kok !== '' ? $kok : null);

        if ($oksuzler === []) {
            $this->info('Öksüz klasör yok.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d öksüz klasör:', count($oksuzler)));

        foreach ($oksuzler as $yol) {
            $this->line('  '.basename($yol));
        }

        if (! $this->option('onayla')) {
            $this->newLine();
            $this->comment('  Hiçbir şey silinmedi. Silmek için: --onayla');

            return self::SUCCESS;
        }

        foreach ($oksuzler as $yol) {
            File::deleteDirectory($yol);
        }

        $this->info(sprintf('%d klasör silindi.', count($oksuzler)));

        return self::SUCCESS;
    }
}
