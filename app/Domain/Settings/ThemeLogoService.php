<?php

namespace App\Domain\Settings;

use App\Domain\Catalog\UnsupportedImageTypeException;
use App\Enums\SettingGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Marka logosunun yüklenmesi. (4G)
 *
 * ★ Kurallar [ProductImageService] ile AYNI ve bilerek öyle: iki yükleme
 * yolunun farklı güvenlik seviyesinde olması, birinden kapatılan kapının
 * diğerinden açık kalması demek olurdu.
 *
 * ⚠️ SVG KABUL EDİLMİYOR. En cazip biçim (vektör, küçük) ama SVG bir XML
 * belgesidir ve `<script>` taşıyabilir; tarayıcı `<img>` içinde onu
 * çalıştırmasa da doğrudan açıldığında çalıştırır. Marka kendi vitrininde
 * betik çalıştırabilseydi 4-K5'in kapattığı kapı yeniden açılırdı.
 */
class ThemeLogoService
{
    /** @var list<string> */
    public const IZINLI_TURLER = ['image/jpeg', 'image/png', 'image/webp'];

    /** Logo küçük bir görsel — 2 MB fazlasıyla yeter. */
    public const MAKS_BAYT = 2 * 1024 * 1024;

    public function __construct(private readonly SettingsService $ayarlar) {}

    /**
     * Logoyu diske yazar ve ayara işler.
     *
     * @throws UnsupportedImageTypeException
     */
    public function yukle(UploadedFile $dosya): string
    {
        /*
        | ⚠️ Tür DOSYANIN İÇERİĞİNDEN okunuyor. `getClientMimeType()`
        | istemcinin söylediği şeydir ve uydurulabilir: `zararli.php`
        | dosyası "image/png" etiketiyle diske yazılabilirdi.
        */
        $tur = $dosya->getMimeType();

        if (! in_array($tur, self::IZINLI_TURLER, strict: true)) {
            throw new UnsupportedImageTypeException((string) $tur, self::IZINLI_TURLER);
        }

        $uzanti = match ($tur) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        /*
        | ⚠️ Dosya adı da uzantısı da İSTEMCİDEN ALINMIYOR: `../../` içeren
        | bir ad yol dışına yazmayı denerdi.
        */
        $ad = Str::uuid7().'.'.$uzanti;
        $yol = "theme/{$ad}";

        Storage::disk('public')->putFileAs('theme', $dosya, $ad);

        /*
        | ⚠️ ESKİ LOGO SİLİNİYOR. Silinmeseydi marka her denemede diske
        | bir dosya bırakır, yıllar sonra kimsenin bakmadığı bir yığın
        | oluşurdu (3G'deki öksüz klasör derdinin küçüğü).
        */
        $eski = $this->ayarlar->al(SettingGroup::Theme, 'logo_path');

        if (is_string($eski) && $eski !== '' && $eski !== $yol) {
            Storage::disk('public')->delete($eski);
        }

        $this->ayarlar->yaz(SettingGroup::Theme, 'logo_path', $yol);

        return $yol;
    }

    /** Logoyu kaldırır — dosya da siliniyor. */
    public function kaldir(): void
    {
        $mevcut = $this->ayarlar->al(SettingGroup::Theme, 'logo_path');

        if (is_string($mevcut) && $mevcut !== '') {
            Storage::disk('public')->delete($mevcut);
        }

        $this->ayarlar->yaz(SettingGroup::Theme, 'logo_path', null);
    }
}
