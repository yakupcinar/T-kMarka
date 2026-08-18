<?php

namespace App\Http\Storefront;

use App\Domain\Cart\CartService;
use App\Domain\Settings\ThemeSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Her vitrin sayfasının ihtiyacı olan ortak veri. (4A)
 *
 * ⚠️ NEDEN GÖRÜNÜM BESLEYİCİ (view composer), NEDEN CONTROLLER DEĞİL:
 * mağaza kapalı sayfasını CONTROLLER değil MIDDLEWARE döndürüyor
 * ([RequirePublishedStore]). Middleware'in görünüm verisi hazırlaması
 * katman karışması olurdu; her controller'da tekrarlamak ise bir gün
 * birinde unutulur ve o sayfa marka rengi olmadan çıkardı.
 *
 * ⚠️ Burada YALNIZCA her sayfada aynı olan şeyler var. Sayfaya özel veri
 * (ürün listesi, arama kelimesi) controller'dan geliyor — buraya konsaydı
 * her sayfa her sorguyu çalıştırırdı.
 */
class StorefrontViewData
{
    public function __construct(
        private readonly ThemeSettings $tema,
        private readonly CartService $sepetler,
    ) {}

    public function compose(View $gorunum): void
    {
        $goruntu = $this->tema->goruntu();

        /*
        | ★ LOGO ADRESİ BURADA ÜRETİLİYOR — [ThemeSettings]'te DEĞİL. (4G)
        |
        | ⚠️ `tenant_asset()` bir KİRACILIK yardımcısı; `app/Domain/`
        | altındaki hiçbir sınıf "hangi kiracıdayım" diye soramaz (M-2.7,
        | ölçülüyor). Domain doğrulanmış YOLU veriyor, adresi HTTP katmanı
        | kuruyor.
        |
        | ⚠️ 4A'da logo yükleme yoktu ve yol doğrudan `src`'ye basılıyordu;
        | 4G'de yükleme gelince o hâliyle KIRIK GÖRSEL çıkardı.
        */
        $goruntu['logo'] = $goruntu['logo'] === null
            ? null
            : tenant_asset($goruntu['logo']);

        $gorunum->with([
            'tema' => $goruntu,
            'sepetAdedi' => $this->sepetAdedi(),
        ]);
    }

    /**
     * Üst bardaki sepet sayısı.
     *
     * ⚠️ Bu sayının SUNUCUDA yazılabilmesi 4A'daki çerez kararının tek
     * görünür sebebi: `X-Cart-Token` başlığı tek yol olarak kalsaydı
     * tarayıcı düz gezinmede onu gönderemez ve burada hep 0 yazardı.
     *
     * ⚠️ Hata YUTULUYOR değil — sepet bulunamazsa 0 doğru cevap. Ama
     * sepet AÇILMIYOR: her sayfa görüntülemesi boş sepet yaratsaydı
     * veritabanı, hiç alışveriş yapmayan ziyaretçilerin sepetleriyle
     * dolardı (terk edilmiş sepet raporunu da bozardı, 2F).
     */
    private function sepetAdedi(): int
    {
        /** @var Request $istek */
        $istek = request();

        $token = CartToken::oku($istek);

        if ($token === null) {
            return 0;
        }

        $sepet = $this->sepetler->misafirSepetiBul($token);

        return $sepet === null ? 0 : (int) $sepet->items()->sum('quantity');
    }
}
