<?php

namespace App\Console\Commands;

use App\Platform\Models\Plan;
use Illuminate\Console\Command;

/**
 * Varsayılan abonelik planlarını kurar. (3E)
 *
 * ⚠️ MERKEZ bağlamda çalışıyor — `tenants:run` ile SARILMAZ. Planlar
 * bütün markaların ortak lobisinde (M-2.7).
 *
 * ⚠️ VAR OLANI EZMİYOR — 3A'nın dersi. Fiyat elle değiştirilmiş bir planı
 * yeniden yazsaydı komutun her koşusu markaların ödediği tutarı sessizce
 * varsayılana döndürürdü.
 */
class SeedPlans extends Command
{
    protected $signature = 'plan:kur {--guncelle : Var olan planların SINIRLARINI da güncelle (fiyata dokunmaz)}';

    protected $description = 'Varsayılan abonelik planlarını kurar (merkez bağlamda, var olanı ezmeden).';

    /**
     * ★ SINIRLAR ÜRÜN ve PERSONEL SAYISINDA — aylık sipariş sınırı YOK.
     *
     * Araştırıldı: İkas ve Shopify'da da yok. Sipariş kısıtlamak markanın
     * satışını, yani cirosunu kesmek demek — en iyi gününde sistemi ona
     * kapatmış olursun.
     *
     * ⚠️ `null` = SINIRSIZ. `0` kullanılsaydı "sıfır ürün" ile "sınırsız"
     * aynı değerle anlatılırdı.
     *
     * @return list<array<string, mixed>>
     */
    public static function tanimlar(): array
    {
        return [
            [
                'code' => 'baslangic',
                'name' => 'Başlangıç',
                'price' => '499.00',
                'max_products' => 100,
                'max_staff' => 1,
                'features' => ['collections' => false, 'reviews' => false],
                'position' => 1,
            ],
            [
                'code' => 'buyume',
                'name' => 'Büyüme',
                'price' => '1499.00',
                'max_products' => 1000,
                'max_staff' => 5,
                'features' => ['collections' => true, 'reviews' => true],
                'position' => 2,
            ],
            [
                'code' => 'olcek',
                'name' => 'Ölçek',
                'price' => '3999.00',

                // ⚠️ `null` = sınırsız.
                'max_products' => null,
                'max_staff' => 15,
                'features' => ['collections' => true, 'reviews' => true],
                'position' => 3,
            ],
        ];
    }

    public function handle(): int
    {
        $eklenen = 0;
        $guncellenen = 0;

        foreach (self::tanimlar() as $tanim) {
            $mevcut = Plan::where('code', $tanim['code'])->first();

            if ($mevcut === null) {
                Plan::create($tanim);
                $eklenen++;

                continue;
            }

            if (! $this->option('guncelle')) {
                continue;
            }

            /*
            | ⚠️ `--guncelle` ile bile FİYATA DOKUNULMUYOR. Fiyat değişimi
            | yürüyen abonelikleri etkiliyor ve iyzico tarafında ayrı bir
            | işlem gerektiriyor; sessizce değiştirilemez.
            */
            $mevcut->fill([
                'name' => $tanim['name'],
                'max_products' => $tanim['max_products'],
                'max_staff' => $tanim['max_staff'],
                'features' => $tanim['features'],
                'position' => $tanim['position'],
            ])->save();

            $guncellenen++;
        }

        $this->info("{$eklenen} plan eklendi, {$guncellenen} plan güncellendi.");

        return self::SUCCESS;
    }
}
