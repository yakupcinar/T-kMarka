<?php

namespace App\Http\Storefront;

use App\Domain\Catalog\ProductQuery;
use App\Domain\Catalog\VariantSelector;
use App\Domain\Settings\ThemeSettings;
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
    public function __construct(
        private readonly ProductQuery $sorgu,
        private readonly ThemeSettings $tema,
        private readonly VariantSelector $secici,
    ) {}

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

        /*
        | ⚠️ `options.values` de yükleniyor (4.6A): varyant seçicisi eksen
        | adlarını ve değerlerini gösteriyor. Yüklenmezse her eksen için
        | ayrı sorgu açılırdı — ürün sayfası N+1'e düşerdi.
        */
        $urun->load(['images', 'variants', 'options.values']);

        /*
        | ⚠️ Görünüm adı `match` ile SABİT metne çevriliyor, birleştirmeyle
        | değil: ayardan gelen metnin görünüm yoluna girmesi, o metin bir
        | gün doğrulanmadan geçerse sunucudaki BAŞKA bir Blade dosyasının
        | render edilmesi demek (4A'da PHPStan da uyarmıştı).
        */
        $gorunum = match ($this->tema->duzen()) {
            'vitrinli' => 'storefront.vitrinli.urun',
            default => 'storefront.sade.urun',
        };

        return view($gorunum, [
            'urun' => $urun,

            /*
            | ★ VARYANT SEÇİCİSİ (4.6A) — veri DOMAIN'den.
            |
            | ⚠️ "Bu değer seçilebilir mi" sorusu satılabilirlik kuralına
            | bağlı (`stock − committed` + aktiflik). Ekran kendi hesabını
            | yapsaydı müşteri, başka bir siparişe BAĞLI stoğu seçer ve
            | sepete eklerken hata alırdı — 4.5J'deki "iki formül"
            | tuzağının aynısı.
            */
            'secici' => $this->secici->coz($urun),
            'listeEsigi' => VariantSelector::LISTE_ESIGI,
        ]);
    }
}
