<?php

namespace App\Console\Commands;

use App\Domain\Search\ProductSearch;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Arama alanlarını baştan yazar. (2C)
 *
 * ★ NEDEN VAR: `search_text` ve `search_vector` ürün DEĞİŞTİĞİNDE
 * tazeleniyor. Kolonlar sonradan eklendiği için, migration'dan ÖNCE var
 * olan ürünlerin arama alanları BOŞ kaldı — ve bu HATA VERMEDİ:
 * ürünler duruyor, vitrin çalışıyor, yalnızca arama onları bulmuyor.
 *
 * Gerçek iki markada ölçüldü: kolonlar eklendi, tek bir ürün bile
 * aranabilir değildi.
 *
 * ⚠️ MARKA VERİSİNE DOKUNUYOR — `tenants:run` ile sarılmadan
 * çalıştırılırsa merkez bağlamda koşar ve hiçbir şey yapmaz:
 *
 * ```
 * php artisan tenants:run "search:reindex"
 * ```
 */
class ReindexSearch extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Ürünlerin arama alanlarını baştan yazar (marka bağlamında).';

    public function handle(ProductSearch $arama): int
    {
        $sayac = 0;

        /*
        | ⚠️ `chunkById` — `all()` büyük katalogda belleği tüketirdi ve bu
        | ancak üretimde patlardı.
        |
        | ⚠️ `withTrashed` YOK: silinmiş ürün aramada zaten çıkmamalı.
        */
        Product::query()->with('variants')->chunkById(200, function ($urunler) use ($arama, &$sayac) {
            foreach ($urunler as $urun) {
                $arama->tazele($urun);
                $sayac++;
            }
        });

        $this->info("{$sayac} ürünün arama alanı tazelendi.");

        return self::SUCCESS;
    }
}
