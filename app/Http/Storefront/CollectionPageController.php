<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\CollectionQuery;
use App\Http\Controllers\Controller;
use App\Models\ProductCollection;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vitrinde koleksiyon gezinme. (4.5H)
 *
 * ★ GERÇEK KULLANIMDA BULUNAN EKSİK: marka koleksiyon kuruyordu ama
 * müşteri onu HİÇBİR YERDEN göremiyordu. Uçları 2D'de vardı
 * (`/api/collections`), sayfası yoktu.
 *
 * ⚠️ 4.5F'nin kapsam testi bunu YAKALAYAMAZDI: o yalnızca PANEL uçlarına
 * bakıyordu. Vitrin API'sinin ekran karşılığı hiç ölçülmüyordu — test
 * 4.5H'de vitrini de kapsayacak şekilde genişletildi.
 */
class CollectionPageController extends Controller
{
    public function __construct(private readonly CollectionQuery $sorgu) {}

    public function index(): View
    {
        return view('storefront.koleksiyonlar', [
            /*
            | ⚠️ Yalnızca YAYINDAKİLER. Kapalı koleksiyon listede
            | görünseydi müşteri tıklar ve 404 alırdı.
            */
            'koleksiyonlar' => ProductCollection::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function show(string $slug): View
    {
        $koleksiyon = ProductCollection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($koleksiyon === null) {
            throw new NotFoundHttpException('Koleksiyon bulunamadı.');
        }

        /*
        | ★ ÜYELER `CollectionQuery`'den — manuel ve kurallı AYRIMI ORADA.
        | Kurallıda üyeler sorgu anında hesaplanıyor (2D), yani fiyat
        | değişince liste kendiliğinden güncelleniyor.
        |
        | ⚠️ Sorgu `forStorefront()` üzerinden gidiyor: taslak ve arşiv
        | ürün koleksiyonda da çıkmıyor (1B-K10).
        */
        $urunler = $this->sorgu->urunler($koleksiyon)
            ->with(['images', 'variants'])
            ->limit(HomeController::LIMIT)
            ->get();

        return view('storefront.koleksiyon', [
            'koleksiyon' => $koleksiyon,
            'urunler' => $urunler,
        ]);
    }
}
