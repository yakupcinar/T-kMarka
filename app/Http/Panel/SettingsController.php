<?php

namespace App\Http\Panel;

use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\SettingGroup;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;

/**
 * Mağaza ayarları — panel ucu.
 *
 * `izin:settings.write` arkasında (routes/tenant.php).
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly StorePublication $yayin,
    ) {}

    /**
     * Bütün gruplar.
     *
     * ⚠️ `paneleGorunen()` kullanılıyor, `grup()` değil: şifreli ayarlar
     * (ödeme anahtarları) değer olarak DÖNMEZ, yalnızca "tanımlı mı"
     * bilgisi döner. Panelde okunmalarına gerek yok, yazılmaları yeterli.
     */
    public function index(): JsonResponse
    {
        $cevap = [];

        foreach (SettingGroup::cases() as $grup) {
            $cevap[$grup->value] = $this->ayarlar->paneleGorunen($grup);
        }

        return response()->json([
            'settings' => $cevap,
            'is_published' => $this->yayin->yayindaMi(),

            // Panel hangi alanların şu anda düzenlenemeyeceğini bilsin ki
            // kullanıcıyı 409 ile karşılamak yerine alanı gri gösterebilsin.
            'locked' => $this->yayin->yayindaMi() ? StorePublication::KILITLI : [],
        ]);
    }

    /**
     * Toplu güncelleme.
     *
     * ⚠️ ÖNCE HEPSİ DENETLENİR, SONRA YAZILIR.
     *
     * Sırayla yazılsaydı: altı alanlık bir gönderimde dördüncüsü kilitliyse
     * ilk üçü yazılmış, kalanı yazılmamış olurdu. Marka "hata aldım" diye
     * düşünürken ayarların yarısı değişmiş olacaktı — sessiz yarım durum.
     */
    public function update(UpdateSettingsRequest $istek): JsonResponse
    {
        /** @var array<string, array<string, mixed>> $gonderilen */
        $gonderilen = $istek->validated();

        // 1. AŞAMA — denetim. Kilitli alan varsa istisna fırlar ve
        // hiçbir şey yazılmamış olur (bootstrap/app.php → 409).
        foreach ($gonderilen as $grupAdi => $ayarlar) {
            $grup = SettingGroup::from($grupAdi);

            foreach (array_keys($ayarlar) as $anahtar) {
                $this->yayin->yazmayiDogrula($grup, (string) $anahtar);
            }
        }

        // 2. AŞAMA — yazma.
        foreach ($gonderilen as $grupAdi => $ayarlar) {
            $grup = SettingGroup::from($grupAdi);
            $sifreli = $this->ayarlar->sifreliMi($grup);

            foreach ($ayarlar as $anahtar => $deger) {
                $this->ayarlar->yaz($grup, (string) $anahtar, $deger, $sifreli);
            }
        }

        return $this->index();
    }
}
