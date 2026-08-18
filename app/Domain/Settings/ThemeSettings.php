<?php

namespace App\Domain\Settings;

use App\Enums\SettingGroup;

/**
 * Vitrin görünümü — 4-K5'in uygulaması. (4A)
 *
 * ★ KARAR: marka AYAR seçer, ŞABLON YAZMAZ.
 *
 * Şablon yazması yapılamaz çünkü Blade PHP'dir ve kum havuzu yoktur;
 * markanın yazdığı Blade'i render etmek doğrudan uzaktan kod çalıştırmadır.
 * Şema bazlı kiracılıkta bunun bedeli tek marka değil: sunucuda kod
 * çalıştıran biri `search_path`'i değiştirip BÜTÜN markaların verisine
 * ulaşır. (4-K5, gerekçesi PLAN.md'de)
 *
 * ⚠️ AMA "ŞABLON YAZAMAZ" YETMİYOR — ayarın kendisi de HTML'e giriyor.
 *
 * Renk doğrudan bir `<style>` bloğuna yazılıyor. Marka panelden şunu
 * kaydedebilseydi:
 *
 * ```
 * primary_color = "red; } body { background: url(https://baskasi.com/x) "
 * ```
 *
 * ...çıkan sayfa markanın yazmadığı CSS'i çalıştırırdı. Yani şablonu
 * kapatmak kapıyı kapatıyor, PENCEREYİ değil.
 *
 * Bu yüzden okuma yolu **beyaz liste**: her değer ya kalıba/listeye uyar
 * ya da varsayılana düşer. Geçersiz değer sayfaya HİÇ ULAŞMAZ.
 *
 * ⚠️ Doğrulama YAZMA yolunda değil OKUMA yolunda: ayar veritabanına
 * başka yollardan da girebiliyor (tohumlayıcı, artisan komutu, elle
 * SQL). Yazarken doğrulamak o yolları açık bırakırdı; okurken
 * doğrulamak hepsini kapatıyor.
 */
class ThemeSettings
{
    public const VARSAYILAN_RENK = '#ea580c';

    public const VARSAYILAN_YAZI_TIPI = 'sistem';

    public const VARSAYILAN_DUZEN = 'sade';

    /**
     * Seçilebilir yazı tipleri.
     *
     * ⚠️ Serbest metin OLAMAZ: değer `font-family` içine giriyor.
     * Listeden seçim, tarayıcıya gidecek metni bizim yazdığımız
     * anlamına geliyor.
     *
     * @var array<string, string>
     */
    public const YAZI_TIPLERI = [
        'sistem' => 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif',
        'serif' => 'Georgia, Cambria, Times New Roman, serif',
        'mono' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
    ];

    /**
     * Vitrin düzenleri. (4G'de ikincisi eklendi)
     *
     * ⚠️ 4A'da tek elemanlıydı ve gerekçesi "sonradan eklemek, kavramı
     * sonradan icat etmekten kolay" diye yazılmıştı. Doğru çıktı: ikinci
     * düzeni eklemek bir klasör açmak ve buraya bir satır yazmaktan
     * ibaret oldu.
     *
     * ⚠️ Marka düzen dosyalarını DÜZENLEYEMEZ (4-K5), yalnızca SEÇER.
     *
     * @var list<string>
     */
    public const DUZENLER = ['sade', 'vitrinli'];

    public function __construct(private readonly SettingsService $ayarlar) {}

    /**
     * Vitrinin ihtiyacı olan her şey — hepsi doğrulanmış.
     *
     * @return array{ad: string, renk: string, yazi_tipi: string, duzen: string, logo: ?string}
     */
    public function goruntu(): array
    {
        return [
            'ad' => $this->magazaAdi(),
            'renk' => $this->renk(),
            'yazi_tipi' => $this->yaziTipi(),
            'duzen' => $this->duzen(),
            'logo' => $this->logo(),
        ];
    }

    /**
     * Marka rengi — yalnızca `#rrggbb`.
     *
     * ⚠️ Kısa biçim (`#f00`) de reddediliyor. Kabul edilseydi kalıbın
     * iki hâli olurdu ve ikincisini doğrulamayı unutmak kolaydı.
     */
    public function renk(): string
    {
        $deger = $this->ayarlar->al(SettingGroup::Theme, 'primary_color');

        if (! is_string($deger) || preg_match('/^#[0-9a-f]{6}$/i', $deger) !== 1) {
            return self::VARSAYILAN_RENK;
        }

        return strtolower($deger);
    }

    /** Yazı tipi — sabit listeden, CSS değeri BİZİM yazdığımız metin. */
    public function yaziTipi(): string
    {
        $deger = $this->ayarlar->al(SettingGroup::Theme, 'font');

        if (! is_string($deger) || ! array_key_exists($deger, self::YAZI_TIPLERI)) {
            return self::YAZI_TIPLERI[self::VARSAYILAN_YAZI_TIPI];
        }

        return self::YAZI_TIPLERI[$deger];
    }

    /** Düzen adı — şablon dosyası seçmekte kullanılıyor. */
    public function duzen(): string
    {
        $deger = $this->ayarlar->al(SettingGroup::Theme, 'layout');

        if (! is_string($deger) || ! in_array($deger, self::DUZENLER, true)) {
            return self::VARSAYILAN_DUZEN;
        }

        return $deger;
    }

    /**
     * Logo yolu — yoksa `null` ve vitrin mağaza adını yazıyor.
     *
     * ⚠️ Yol MARKANIN KENDİ KLASÖRÜNE daraltılıyor. Serbest bırakılsaydı
     * `../` ile başka markanın klasörü ya da sistem dosyası istenebilirdi.
     * Bugün yükleme ucu yok (4G'de gelecek) ama kontrol şimdiden burada:
     * ayarın veritabanına elle girmesi mümkün.
     */
    public function logo(): ?string
    {
        $deger = $this->ayarlar->al(SettingGroup::Theme, 'logo_path');

        if (! is_string($deger) || $deger === '') {
            return null;
        }

        if (str_contains($deger, '..') || str_starts_with($deger, '/')) {
            return null;
        }

        return $deger;
    }

    /**
     * Vitrinde görünen mağaza adı.
     *
     * ⚠️ `legal_name` (ticari unvan) DEĞİL — o sözleşmeye giriyor.
     */
    private function magazaAdi(): string
    {
        $deger = $this->ayarlar->al(SettingGroup::Store, 'name');

        return is_string($deger) && trim($deger) !== '' ? $deger : 'Mağaza';
    }
}
