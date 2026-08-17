<?php

namespace App\Console\Commands;

use App\Platform\TenantPurge;
use Illuminate\Console\Command;

/**
 * Kapatılmış ve saklama süresi dolmuş markaları KALICI siler. (3G)
 *
 * ⚠️ MERKEZ bağlamda çalışıyor — `tenants:run` ile SARILMAZ.
 *
 * ★ VARSAYILAN: HİÇBİR ŞEY SİLMEZ, yalnızca gösterir.
 *
 * ⚠️ Diğer komutlarımızda kuru çalışma ayrı bir bayraktı (3A) çünkü
 * yaptıkları iş geri alınabilirdi. Burada değil: silme geri ALINAMAZ.
 * Bu yüzden güvenli taraf varsayılan, silmek için `--onayla` gerekiyor.
 */
class PurgeClosedTenants extends Command
{
    protected $signature = 'marka:silinecekleri-temizle
                            {--onayla : GERÇEKTEN sil — bu bayrak olmadan yalnızca gösterir}';

    protected $description = 'Saklama süresi dolmuş kapalı markaları kalıcı siler (varsayılan: yalnızca gösterir).';

    public function handle(TenantPurge $temizlik): int
    {
        $silinecekler = $temizlik->silinecekler();

        if ($silinecekler === []) {
            return self::SUCCESS;
        }

        $this->line(sprintf('%d marka silinmeye hazır (saklama süresi: %d gün):', count($silinecekler), TenantPurge::SAKLAMA_GUN));

        foreach ($silinecekler as $marka) {
            $this->line(sprintf(
                '  %s  %s  (kapatılma: %s)',
                $marka->id,
                $marka->name ?? '?',
                $marka->closed_at?->toDateString() ?? '?',
            ));
        }

        if (! $this->option('onayla')) {
            /*
            | ⚠️ Onaysız koşu SESSİZ DEĞİL — ne yapacağını söylüyor.
            | Sessiz olsaydı komutu çalıştıran kişi "bir şey yok" sanır,
            | oysa silinmeyi bekleyen markalar var.
            */
            $this->newLine();
            $this->comment('  Hiçbir şey silinmedi. Silmek için: --onayla');

            return self::SUCCESS;
        }

        foreach ($silinecekler as $marka) {
            $temizlik->sil($marka);
        }

        $this->info(sprintf('%d marka KALICI olarak silindi.', count($silinecekler)));

        return self::SUCCESS;
    }
}
