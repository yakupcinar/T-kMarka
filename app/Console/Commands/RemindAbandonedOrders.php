<?php

namespace App\Console\Commands;

use App\Domain\Order\AbandonedOrderService;
use Illuminate\Console\Command;

/**
 * Ödemesi yarım kalmış siparişlere hatırlatma gönderir. (2F)
 *
 * ⚠️ MARKA VERİSİNE DOKUNUYOR — `tenants:run` ile:
 *
 *     php artisan tenants:run siparis:terk-hatirlat
 *
 * Doğrudan çalıştırılırsa merkez bağlamda koşar, hiçbir markanın siparişini
 * görmez ve "başarılı" döner (0.5, 5. tuzak).
 */
class RemindAbandonedOrders extends Command
{
    protected $signature = 'siparis:terk-hatirlat';

    protected $description = 'Ödemesi yarım kalan siparişlere bir kez hatırlatma gönderir (tenants:run ile çalıştırın).';

    public function handle(AbandonedOrderService $terk): int
    {
        if (! tenancy()->initialized) {
            $this->error('Bu komut marka bağlamında çalışmalı.');
            $this->line('Kullanım: php artisan tenants:run siparis:terk-hatirlat');

            return self::FAILURE;
        }

        $gonderilen = $terk->hatirlat();

        if ($gonderilen > 0) {
            $this->info("{$gonderilen} siparişe hatırlatma gönderildi.");
        }

        return self::SUCCESS;
    }
}
