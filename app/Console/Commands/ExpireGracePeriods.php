<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Platform\Models\Tenant;
use App\Platform\TenantLifecycle;
use Illuminate\Console\Command;

/**
 * Nezaket süresi dolan markaları askıya alır. (3E)
 *
 * ★ 4 numaralı kararın son adımı:
 * ```
 * gün 0-7   past_due   her şey açık, hatırlatma
 * gün 7+    suspended  panel kapalı, VİTRİN AÇIK   ← bu komut
 * ```
 *
 * ⚠️ MERKEZ bağlamda çalışıyor — `tenants:run` ile SARILMAZ.
 */
class ExpireGracePeriods extends Command
{
    protected $signature = 'abonelik:nezaket-denetle {--kuru : Yalnızca göster}';

    protected $description = 'Nezaket süresi dolan markaları askıya alır (merkez bağlamda).';

    public function handle(TenantLifecycle $yasam): int
    {
        $bekleyenler = Tenant::query()
            ->where('status', TenantStatus::PastDue)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->orderBy('id')
            ->get()
            /*
            | ⚠️ `all()` şart: `get()` paketin `TenantCollection`'ını
            | döndürüyor ve statik analiz eleman tipini `Tenant` olarak
            | çıkaramıyor (3C'de aynı sorun `whereHas`'te çıkmıştı).
            */
            ->all();

        if ($bekleyenler === []) {
            return self::SUCCESS;
        }

        /** @var Tenant $marka */
        foreach ($bekleyenler as $marka) {
            $this->line(sprintf('  %s (%s) — nezaket süresi doldu', $marka->name ?? '?', $marka->id));

            if ($this->option('kuru')) {
                continue;
            }

            $yasam->gecir($marka, TenantStatus::Suspended);
        }

        $this->info(sprintf('%d marka %s.', count($bekleyenler), $this->option('kuru') ? 'bulundu (kuru)' : 'askıya alındı'));

        return self::SUCCESS;
    }
}
