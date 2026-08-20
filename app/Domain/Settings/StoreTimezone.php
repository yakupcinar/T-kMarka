<?php

namespace App\Domain\Settings;

use App\Enums\SettingGroup;

/**
 * Markanın GÖSTERİM saat dilimi. (4.5M)
 *
 * ★ NEDEN VAR: gerçek kullanımda bildirildi — *"vitrinde ödendi yazıyor,
 * panele baktım saati yanlış düşmüş."* Ölçüldü ve iddia yarı doğruydu:
 *
 * ```
 * depolama → timestamptz, +00                  ✅ doğru
 * panel    → new Date(iso).toLocaleString()    ✅ 11:34 (tarayıcı çevirdi)
 * vitrin   → format('d.m.Y H:i'), app.timezone ❌ 08:34 (UTC basıldı)
 * ```
 *
 * Yani panel doğruydu, **vitrin üç saat geriydi**.
 *
 * ⚠️ ÇÖZÜM `config/app.php`'de `timezone` DEĞİŞTİRMEK DEĞİL. Laravel
 * `now()`'ı sorguya OFİSSİZ metin bağlıyor; uygulama saat dilimi
 * `Europe/Istanbul` olsaydı PostgreSQL o metni oturumun `TimeZone`'una
 * (UTC) göre yorumlar ve 15 dakikalık rezervasyonlar üç saat kayardı —
 * `CLAUDE.md`'de yazılı, WooCommerce'te (#43593) yaşanmış tuzağın
 * aynısı. Depolama ve hesap UTC kalıyor; DEĞİŞEN yalnızca GÖSTERİM.
 *
 * ⚠️ Okuma yolu BEYAZ LİSTE — [ThemeSettings] ile aynı gerekçe: değer
 * `setTimezone()`'a giriyor ve geçersiz metin istisna fırlatır. Ayar
 * veritabanına tohumlayıcı, artisan komutu ya da elle SQL ile de
 * girebiliyor; okurken doğrulamak hepsini kapatıyor.
 */
class StoreTimezone
{
    public const ANAHTAR = 'timezone';

    /** ⚠️ Türkiye'de yaz saati uygulaması yok; ofset sabit +03. */
    public const VARSAYILAN = 'Europe/Istanbul';

    /**
     * Seçilebilir saat dilimleri.
     *
     * ⚠️ Bugün kısa ve bu bilinçli — [ThemeSettings::DUZENLER]'deki
     * gerekçenin aynısı: sonradan satır eklemek, kavramı sonradan icat
     * etmekten kolay. Markaların tamamı Türkiye'de.
     *
     * @var list<string>
     */
    public const SECENEKLER = [
        'Europe/Istanbul',
        'Europe/London',
        'Europe/Berlin',
        'UTC',
    ];

    public function __construct(private readonly SettingsService $ayarlar) {}

    public function oku(): string
    {
        $deger = $this->ayarlar->al(SettingGroup::Store, self::ANAHTAR);

        return is_string($deger) && in_array($deger, self::SECENEKLER, true)
            ? $deger
            : self::VARSAYILAN;
    }
}
