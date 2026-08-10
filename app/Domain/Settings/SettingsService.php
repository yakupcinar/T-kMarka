<?php

namespace App\Domain\Settings;

use App\Enums\SettingGroup;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Mağaza ayarlarını okuma/yazma — tek kapı. (docs/domain-model.md §4)
 *
 * Markaya özel her şey buradan geçiyor: mağaza adı, KDV oranı, kargo ücreti,
 * yasal metinler ve şifreli ödeme anahtarları (M-1).
 *
 * ⚠️ Bu sınıf hangi markada olduğunu BİLMİYOR. Ne veritabanı sorgusu ne de
 * cache anahtarı marka bilgisi taşıyor — `search_path` ve kiracı etiketli
 * cache bunu zaten hallediyor (M-2.1, M-2.4). `app/Domain/` kiracıdan
 * habersizdir (M-2.7).
 */
class SettingsService
{
    /**
     * Tek bir ayarı okur.
     *
     * Grup bazında önbelleğe alınıyor: panelde "kargo ayarları" sekmesi tek
     * sorguyla geliyor, ayrıca her ayar için ayrı sorgu açılmıyor.
     */
    public function al(SettingGroup $grup, string $anahtar, mixed $varsayilan = null): mixed
    {
        return $this->grup($grup)[$anahtar] ?? $varsayilan;
    }

    /**
     * Bir grubun tüm ayarları.
     *
     * ⚠️ Şifreli ayarlar da ÇÖZÜLMÜŞ hâlde döner — bu metot uygulamanın
     * kendi kullanımı içindir (ödeme sağlayıcısını çağırırken anahtar lazım).
     * Panele giden çıktı `paneleGorunen()` ile üretiliyor.
     *
     * @return array<string, mixed>
     */
    public function grup(SettingGroup $grup): array
    {
        return Cache::remember(
            $this->onbellekAnahtari($grup),
            now()->addHour(),
            fn () => Setting::where('group', $grup)
                ->get()
                ->mapWithKeys(fn (Setting $a) => [$a->key => $a->value])
                ->all(),
        );
    }

    /**
     * Ayar yazar veya günceller.
     *
     * ⚠️ `is_encrypted` her zaman `value`'dan ÖNCE veriliyor. Sırası
     * değişirse model istisna fırlatır — ödeme anahtarının sessizce düz metin
     * kaydedilmesini engelleyen koruma (1A.1).
     */
    public function yaz(SettingGroup $grup, string $anahtar, mixed $deger, bool $sifreli = false): void
    {
        $ayar = Setting::firstOrNew(['group' => $grup, 'key' => $anahtar]);

        $ayar->is_encrypted = $sifreli;
        $ayar->value = $deger;
        $ayar->save();

        $this->onbellegiTemizle($grup);
    }

    /**
     * Panele dönecek gösterim — ŞİFRELİ DEĞERLER GİZLENİR.
     *
     * ⚠️ Ödeme sağlayıcı anahtarı panelde okunmamalı. Okunmasına gerek de
     * yok: kullanıcı onu YAZAR, geri okumaz. Düz metin dönseydi tarayıcı
     * geçmişi, hata ayıklama logu, ekran görüntüsü ve omuz üstünden bakış
     * gibi yolların hepsi anahtarı sızdıran bir kanal olurdu.
     *
     * Cevapta değerin yerine "tanımlı mı" bilgisi dönüyor.
     *
     * @return array<string, mixed>
     */
    public function paneleGorunen(SettingGroup $grup): array
    {
        return Setting::where('group', $grup)
            ->get()
            ->mapWithKeys(fn (Setting $a) => [
                $a->key => $a->is_encrypted
                    ? ['is_set' => $a->value !== null, 'encrypted' => true]
                    : $a->value,
            ])
            ->all();
    }

    private function onbellekAnahtari(SettingGroup $grup): string
    {
        return "settings:{$grup->value}";
    }

    private function onbellegiTemizle(SettingGroup $grup): void
    {
        Cache::forget($this->onbellekAnahtari($grup));
    }
}
