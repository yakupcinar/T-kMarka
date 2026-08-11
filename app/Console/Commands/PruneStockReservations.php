<?php

namespace App\Console\Commands;

use App\Domain\Stock\StockService;
use Illuminate\Console\Command;

/**
 * Süresi dolan stok rezervasyonlarını düşürür. (1D-K3)
 *
 * ⚠️ MARKA VERİSİNE DOKUNUYOR — `tenants:run` ile çalıştırılmak ZORUNDA:
 *
 *     php artisan tenants:run stok:rezervasyon-temizle
 *
 * Doğrudan çalıştırılırsa merkez bağlamda koşar ve hiçbir markanın
 * rezervasyonuna ulaşamaz. Bu, 0.5'te ölçtüğümüz beşinci tuzak.
 */
class PruneStockReservations extends Command
{
    protected $signature = 'stok:rezervasyon-temizle';

    protected $description = 'Süresi dolan stok rezervasyonlarını serbest bırakır (tenants:run ile çalıştırın).';

    public function handle(StockService $stok): int
    {
        /*
        | ★ SESSİZ HİÇLİK YERİNE GÜRÜLTÜLÜ HATA.
        |
        | Bu kontrol olmasaydı komut merkez bağlamda sorunsuz "başarılı"
        | döner, hiçbir rezervasyonu düşürmez ve kimse fark etmezdi —
        | rezervasyonlar birikir, stok sonsuza kadar bağlı kalırdı.
        |
        | Zamanlamayı `tenants:run` olmadan yazan kişi artık ANINDA
        | öğreniyor. Kuralı belgeye yazmak yetmiyor; makinenin de
        | söylemesi gerekiyor.
        */
        if (! tenancy()->initialized) {
            $this->error('Bu komut marka bağlamında çalışmalı.');
            $this->line('Kullanım: php artisan tenants:run stok:rezervasyon-temizle');

            return self::FAILURE;
        }

        $dusen = $stok->suresiDolanlariDusur();

        if ($dusen > 0) {
            $this->info("{$dusen} rezervasyon serbest bırakıldı.");
        }

        return self::SUCCESS;
    }
}
