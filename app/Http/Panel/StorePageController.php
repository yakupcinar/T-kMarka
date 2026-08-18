<?php

namespace App\Http\Panel;

use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StoreNotReadyException;
use App\Domain\Settings\StorePublication;
use App\Domain\Settings\StoreReadiness;
use App\Enums\SettingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mağaza ayarları ve YAYINA ALMA. (4H)
 *
 * ★ BİTİŞ ÖLÇÜTÜNÜN EKSİK HALKASI. Faz 4'ün ölçütü "marka hiç `curl`
 * kullanmadan mağazasını kurar … mağazasını yayına alır" diyordu; 4C-4G
 * giriş, ürün, sipariş ve temayı getirdi ama **yayına alma ekranı yoktu**.
 * Marka `curl` olmadan mağazasını açamıyordu.
 *
 * ⚠️ Mağaza KAPALI doğuyor (1A.4) ve açılması için altı zorunlu bilgi +
 * üç yasal metin gerekiyor. Bu ekran eksikleri TEK SEFERDE gösteriyor;
 * tek tek bildirseydi marka altı kez "yayınla → eksik" turu atardı.
 */
class StorePageController extends Controller
{
    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly StoreReadiness $hazirlik,
        private readonly StorePublication $yayin,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Magaza', [
            'ayarlar' => $this->ayarlar->paneleGorunen(SettingGroup::Store),
            'zorunlular' => StoreReadiness::ZORUNLU_AYARLAR,
            'eksikler' => $this->hazirlik->eksikler(),
            'yayinda' => $this->yayin->yayindaMi(),
        ]);
    }

    public function kaydet(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'tax_number' => ['nullable', 'string', 'max:20'],
            'tax_office' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:190'],
        ]);

        foreach ($veri as $anahtar => $deger) {
            /*
            | ⚠️ Yazma [SettingsService]'ten geçiyor: mağaza AÇIKKEN
            | değiştirilemeyecek ayarlar orada kilitli (1A.4). Doğrudan
            | `Setting::update()` yazılsaydı marka, satışı sürerken
            | sözleşmesindeki şirket bilgisini değiştirebilirdi.
            */
            $this->ayarlar->yaz(SettingGroup::Store, $anahtar, $deger);
        }

        return back()->with('mesaj', 'Mağaza bilgileri kaydedildi.');
    }

    public function yayinla(): RedirectResponse
    {
        try {
            $this->yayin->yayinla();
        } catch (StoreNotReadyException $hata) {
            /*
            | ⚠️ İstisna SAYFAYA taşınıyor, 500 olarak dışarı sızmıyor:
            | eksik bilgiyle yayınlamaya çalışmak markanın hatası değil,
            | sıradan bir sonuç.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Mağaza yayına alındı.');
    }

    public function kapat(): RedirectResponse
    {
        $this->yayin->kapat();

        /*
        | ⚠️ Panel `magaza-acik` kapısının DIŞINDA (4C): marka mağazasını
        | kapatınca kendini de dışarıda bırakmasın — açmanın tek yolu burası.
        */
        return back()->with('mesaj', 'Mağaza satışa kapatıldı.');
    }
}
