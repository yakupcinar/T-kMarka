<?php

namespace App\Http\Panel;

use App\Domain\Payment\PaymentProviderFactory;
use App\Domain\Payment\PaymentReadiness;
use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ödeme sağlayıcısı ayarları. (4.5B)
 *
 * ★ FAZ 4'ÜN EN CİDDİ BOŞLUĞUYDU: marka panelden ödeme sağlayıcısını
 * kuramıyordu, yani **gerçek para tahsil edemiyordu**. Uçları 1E'de
 * yazılmıştı, ekranı yoktu.
 *
 * ⚠️ Anahtarlar `settings.payment` grubunda ve o grup ŞİFRELİ saklanıyor
 * (1E.1). Ekran mevcut değeri GÖSTERMİYOR, yalnızca "girilmiş mi"
 * bilgisini veriyor — gerekçesi [SettingsService::paneleGorunen]'de.
 */
class PaymentSettingsPageController extends Controller
{
    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly PaymentReadiness $hazirlik,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Odeme', [
            'odeme' => $this->hazirlik->ozet(),
        ]);
    }

    public function kaydet(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', PaymentProviderFactory::tanimliAdlar())],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['nullable', 'string', 'max:255'],
        ]);

        /*
        | ⚠️ Sağlayıcı ÖNCE yazılıyor: gerekli anahtar listesi sağlayıcıya
        | göre değişiyor. Sonra yazılsaydı marka iyzico'ya geçerken
        | anahtarları ESKİ sağlayıcının listesine göre doğrulanırdı.
        | Kural panel API'siyle aynı — iki yüzey aynı sırayı izliyor.
        */
        $this->ayarlar->yaz(SettingGroup::Payment, 'provider', (string) $veri['provider']);

        /** @var array<string, string|null> $anahtarlar */
        $anahtarlar = $veri['keys'] ?? [];

        foreach ($anahtarlar as $anahtar => $deger) {
            /*
            | ⚠️ BOŞ GÖNDERİLEN ANAHTAR ATLANIYOR, silinmiyor.
            |
            | Ekran mevcut değeri göstermiyor (şifreli); marka formu
            | açıp yalnızca sağlayıcıyı değiştirdiğinde anahtar alanları
            | BOŞ gider. Boşu yazsaydık marka farkında olmadan
            | anahtarlarını SİLER ve tahsilat dururdu.
            */
            if ($deger === null || trim($deger) === '') {
                continue;
            }

            $this->ayarlar->yaz(SettingGroup::Payment, $anahtar, trim($deger), sifreli: true);
        }

        return back()->with('mesaj', 'Ödeme ayarları kaydedildi.');
    }
}
