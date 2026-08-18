<?php

namespace App\Http\Panel;

use App\Domain\Catalog\UnsupportedImageTypeException;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\ThemeLogoService;
use App\Domain\Settings\ThemeSettings;
use App\Enums\SettingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tema ayarları — markanın vitrinini biçimlendirdiği ekran. (4G)
 *
 * ★ 4-K5'İN ARAYÜZÜ: marka AYAR seçer, ŞABLON YAZMAZ.
 *
 * ⚠️ Bu ekran bilerek KISITLI: renk kutusu, sabit yazı tipi listesi, sabit
 * düzen listesi, logo yükleme. Serbest metin alanı (özel CSS, özel HTML)
 * YOK — Blade PHP'dir ve kum havuzu yoktur; markanın yazdığı şablonu
 * render etmek şema bazlı kiracılıkta BÜTÜN markaların verisini riske
 * atardı.
 */
class ThemePageController extends Controller
{
    public function __construct(
        private readonly ThemeSettings $tema,
        private readonly SettingsService $ayarlar,
        private readonly ThemeLogoService $logolar,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Tema', [
            'tema' => [
                'renk' => $this->tema->renk(),
                'yazi_tipi' => $this->ayarKodu('font', ThemeSettings::VARSAYILAN_YAZI_TIPI, array_keys(ThemeSettings::YAZI_TIPLERI)),
                'duzen' => $this->tema->duzen(),
                'logo' => $this->logoAdresi(),
            ],
            'secenekler' => [
                /*
                | ⚠️ Seçenekler SUNUCUDAN geliyor. Arayüze sabit yazılsaydı
                | listeye yeni bir yazı tipi eklendiğinde panel onu
                | göstermez, ya da kaldırılan bir seçenek panelde durmaya
                | devam eder ve kaydedince sessizce varsayılana düşerdi.
                */
                'yazi_tipleri' => array_keys(ThemeSettings::YAZI_TIPLERI),
                'duzenler' => ThemeSettings::DUZENLER,
            ],
            'varsayilan_renk' => ThemeSettings::VARSAYILAN_RENK,
        ]);
    }

    public function kaydet(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            /*
            | ⚠️ Doğrulama BURADA DA VAR ama tek savunma DEĞİL: okuma yolu
            | ([ThemeSettings]) her değeri yeniden doğruluyor. Ayar
            | veritabanına başka yollardan da girebiliyor (tohumlayıcı,
            | artisan, elle SQL) — 4A-K1'in gerekçesi.
            |
            | Buradaki doğrulama markaya ANLAŞILIR HATA vermek için;
            | oradaki güvenlik için.
            */
            'renk' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'yazi_tipi' => ['required', 'string', Rule::in(array_keys(ThemeSettings::YAZI_TIPLERI))],
            'duzen' => ['required', 'string', Rule::in(ThemeSettings::DUZENLER)],
        ]);

        $this->ayarlar->yaz(SettingGroup::Theme, 'primary_color', strtolower((string) $veri['renk']));
        $this->ayarlar->yaz(SettingGroup::Theme, 'font', (string) $veri['yazi_tipi']);
        $this->ayarlar->yaz(SettingGroup::Theme, 'layout', (string) $veri['duzen']);

        return back()->with('mesaj', 'Tema güncellendi.');
    }

    public function logoYukle(Request $istek): RedirectResponse
    {
        $istek->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:'.(ThemeLogoService::MAKS_BAYT / 1024)],
        ]);

        $dosya = $istek->file('logo');

        abort_if(! $dosya instanceof UploadedFile, 422);

        try {
            $this->logolar->yukle($dosya);
        } catch (UnsupportedImageTypeException $hata) {
            /*
            | ⚠️ Laravel'in `mimes:` kuralı UZANTIYA ve istemcinin
            | söylediğine bakıyor; servis dosyanın İÇERİĞİNE bakıyor.
            | İkisi ayrışabilir — o zaman kullanıcıya hata gösteriliyor,
            | 500 değil.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Logo yüklendi.');
    }

    public function logoKaldir(): RedirectResponse
    {
        $this->logolar->kaldir();

        return back()->with('mesaj', 'Logo kaldırıldı.');
    }

    /**
     * Ayarın KOD değeri — [ThemeSettings::yaziTipi] CSS değerini döndürüyor,
     * panelde ise seçili seçeneğin adı gerekiyor.
     *
     * @param  list<string>  $izinliler
     */
    private function ayarKodu(string $anahtar, string $varsayilan, array $izinliler): string
    {
        $deger = $this->ayarlar->al(SettingGroup::Theme, $anahtar);

        return is_string($deger) && in_array($deger, $izinliler, true) ? $deger : $varsayilan;
    }

    private function logoAdresi(): ?string
    {
        $yol = $this->tema->logo();

        // ⚠️ Adres HTTP katmanında kuruluyor — Domain kiracılığı bilemez (M-2.7).
        return $yol === null ? null : tenant_asset($yol);
    }
}
