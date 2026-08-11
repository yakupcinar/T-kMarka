<?php

namespace App\Console\Commands;

use App\Domain\Stock\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * `committed` sayacı ile aktif rezervasyonların toplamı tutuyor mu? (1D-K1)
 *
 * ★ Materyalleştirilmiş sayacın BEDELİ bu, karşılığı da bu denetim.
 *
 * Shopify'ın "her konumda TUTMASI GEREKEN özdeşlik" dediği şey bizde:
 *
 *     variants.committed  =  SUM(aktif rezervasyonlar)
 *
 * Eşit değilse sayaç bozulmuş demektir — ya bir rezervasyon serbest
 * bırakılırken sayaç güncellenmemiş, ya da tersi. Kendiliğinden düzelmez
 * ve fark edilmezse stok yanlış görünmeye devam eder.
 *
 * ⚠️ MARKA VERİSİNE DOKUNUYOR — `tenants:run` ile:
 *     php artisan tenants:run stok:sayac-denetle
 */
class AuditStockCounters extends Command
{
    protected $signature = 'stok:sayac-denetle';

    protected $description = 'committed sayacı ile rezervasyon toplamını karşılaştırır (tenants:run ile çalıştırın).';

    public function handle(StockService $stok): int
    {
        if (! tenancy()->initialized) {
            $this->error('Bu komut marka bağlamında çalışmalı.');
            $this->line('Kullanım: php artisan tenants:run stok:sayac-denetle');

            return self::FAILURE;
        }

        $tutarsizliklar = $stok->tutarsizliklar();

        if ($tutarsizliklar === []) {
            return self::SUCCESS;
        }

        /*
        | ⚠️ Sayacı KENDİLİĞİNDEN DÜZELTMİYORUZ.
        |
        | Düzeltseydik asıl sebep (hangi kod yolu sayacı bozdu) hiç
        | görünmez, her gece sessizce onarılır ve sorun kalıcı olurdu.
        | Denetimin işi haber vermek; onarım bilinçli bir karar.
        */
        $this->error(count($tutarsizliklar).' varyantta stok sayacı tutmuyor:');

        foreach ($tutarsizliklar as $satir) {
            $this->line("  {$satir['sku']}  committed={$satir['committed']}  rezervasyon={$satir['rezervasyon_toplami']}");
        }

        Log::warning('Stok sayacı tutarsızlığı', [
            'tenant' => tenant('id'),
            'tutarsizliklar' => $tutarsizliklar,
        ]);

        return self::FAILURE;
    }
}
