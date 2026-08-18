<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ürün detay sayfası. (4B)
 *
 * ★ 4-K3: API controller'ı değil `app/Domain/` sorgusu çağrılıyor.
 */
class ProductPageController extends Controller
{
    public function __construct(private readonly ProductQuery $sorgu) {}

    public function __invoke(string $slug): View
    {
        /*
        | ⚠️ `vitrindeBul()` — panel sorgusu DEĞİL. Taslak, arşiv ve
        | satılamayan ürün vitrinde 404 vermeli; panel sorgusu kullanılsaydı
        | yayınlanmamış ürünün sayfası adresi bilen herkese açık olurdu
        | (1B-K10).
        */
        $urun = $this->sorgu->vitrindeBul($slug);

        if ($urun === null) {
            throw new NotFoundHttpException('Ürün bulunamadı.');
        }

        $urun->load(['images', 'variants']);

        return view('storefront.sade.urun', ['urun' => $urun]);
    }
}
