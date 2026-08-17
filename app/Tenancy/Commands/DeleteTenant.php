<?php

namespace App\Tenancy\Commands;

use App\Enums\TenantStatus;
use App\Platform\Models\Tenant;
use App\Platform\TenantPurge;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Bir markayı KALICI olarak siler. (3G)
 *
 * ★ 1A'dan devredilen TODO. Şema + dosyalar + merkez kayıt, tek yoldan.
 *
 * ⚠️ `marka:silinecekleri-temizle` ile AYNI servisi kullanıyor: iki ayrı
 * silme yolu yazılsaydı biri dosyaları unutur ve öksüz klasör bırakırdı —
 * bugün diskte tam bundan 38 tane var (ölçüldü).
 *
 * ⚠️ Saklama süresine BAKMIYOR: bu komut elle, bilerek çalıştırılıyor.
 * Zamanlanmış temizlik ayrı komut ve o süreye bakıyor.
 */
class DeleteTenant extends Command
{
    protected $signature = 'tenant:delete
                            {alan-adi : Silinecek markanın alan adı}
                            {--onayla : GERÇEKTEN sil — bu bayrak olmadan yalnızca gösterir}';

    protected $description = 'Markayı kalıcı siler: şema, dosyalar ve merkez kayıt (varsayılan: yalnızca gösterir).';

    public function handle(TenantPurge $temizlik): int
    {
        $alanAdi = strtolower(trim((string) $this->argument('alan-adi')));

        $kayit = Domain::where('domain', $alanAdi)->first();

        if ($kayit === null) {
            $this->error("Bu alan adına sahip marka yok: {$alanAdi}");

            return self::FAILURE;
        }

        $marka = Tenant::find($kayit->tenant_id);

        if ($marka === null) {
            $this->error('Alan adı var ama marka kaydı yok — veri tutarsız.');

            return self::FAILURE;
        }

        $this->line("  kimlik : {$marka->id}");
        $this->line('  ad     : '.($marka->name ?? '?'));
        $this->line('  durum  : '.($marka->status instanceof TenantStatus ? $marka->status->value : '?'));
        $this->line("  adres  : {$alanAdi}");

        if (! $this->option('onayla')) {
            $this->newLine();

            /*
            | ⚠️ Silme GERİ ALINAMAZ, bu yüzden varsayılan güvenli taraf.
            | Diğer komutlarda kuru çalışma ayrı bayraktı; burada tersi.
            */
            $this->comment('  Hiçbir şey silinmedi. Silmek için: --onayla');

            return self::SUCCESS;
        }

        $temizlik->sil($marka);

        $this->warn('  Marka KALICI olarak silindi: şema, dosyalar ve merkez kayıt.');

        return self::SUCCESS;
    }
}
