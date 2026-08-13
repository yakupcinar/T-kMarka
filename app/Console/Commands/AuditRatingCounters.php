<?php

namespace App\Console\Commands;

use App\Domain\Review\RatingCounter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * `rating_avg` / `rating_count` onaylı yorumlarla tutuyor mu? (2E-K3)
 *
 * ★ `stok:sayac-denetle`'nin (1D-K1) ikizi. Materyalleştirilmiş sayacın
 * bedeli denetimdir:
 *
 *     products.rating_count = COUNT(onaylı yorum)
 *     products.rating_avg   = ROUND(AVG(onaylı puan), 2)
 *
 * Tutmuyorsa sayaç bozulmuş demektir — bir onay/red geçişinde tazeleme
 * atlanmış olabilir. Kendiliğinden düzelmez ve fark edilmezse vitrinde
 * yanlış puan görünmeye devam eder.
 *
 * ⚠️ MARKA VERİSİNE DOKUNUYOR — `tenants:run` ile:
 *     php artisan tenants:run puan:sayac-denetle
 */
class AuditRatingCounters extends Command
{
    protected $signature = 'puan:sayac-denetle';

    protected $description = 'Ürün puan sayaçlarını onaylı yorumlarla karşılaştırır (tenants:run ile çalıştırın).';

    public function handle(RatingCounter $sayac): int
    {
        if (! tenancy()->initialized) {
            $this->error('Bu komut marka bağlamında çalışmalı.');
            $this->line('Kullanım: php artisan tenants:run puan:sayac-denetle');

            return self::FAILURE;
        }

        $tutarsizliklar = $sayac->tutarsizliklar();

        if ($tutarsizliklar === []) {
            return self::SUCCESS;
        }

        /*
        | ⚠️ ONARMIYOR. Düzeltseydik sayacı bozan kod yolu hiç görünmez,
        | her gece sessizce onarılır ve sorun kalıcı olurdu.
        */
        $this->error(count($tutarsizliklar).' üründe puan sayacı tutmuyor:');

        foreach ($tutarsizliklar as $satir) {
            $this->line(sprintf(
                '  %s  kayıtlı=%d/%s  gerçek=%d/%s',
                $satir['slug'],
                $satir['rating_count'],
                $satir['rating_avg'] ?? '—',
                $satir['gercek_adet'],
                $satir['gercek_ortalama'] ?? '—',
            ));
        }

        Log::warning('Puan sayacı tutarsızlığı', [
            'tenant' => tenant('id'),
            'tutarsizliklar' => $tutarsizliklar,
        ]);

        return self::FAILURE;
    }
}
