<?php

namespace App\Http\Panel;

use App\Domain\Payment\PaymentProviderFactory;
use App\Domain\Payment\PaymentReadiness;
use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ödeme sağlayıcı ayarları — panel ucu. (1E-K11)
 *
 * `izin:settings.write` arkasında.
 *
 * ⚠️ Genel ayar ucundan (`/panel/settings`) AYRI. Sebebi: bu grup serbest
 * biçimli olamaz. Genel uçta marka istediği anahtarı yazabiliyor; burada
 * anahtarlar SAĞLAYICININ BİLDİRDİĞİ listeyle sınırlı, çünkü `iyzico_api`
 * gibi bir yazım hatası sessizce kabul edilirse ödeme "ayarlandı" görünür
 * ve ilk gerçek müşteride patlar.
 */
class PaymentSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly PaymentReadiness $hazirlik,
        private readonly PaymentProviderFactory $saglayicilar,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['payment' => $this->hazirlik->ozet()]);
    }

    public function update(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'provider' => ['nullable', 'string', 'in:'.implode(',', PaymentProviderFactory::tanimliAdlar())],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['nullable', 'string', 'max:255'],
        ]);

        /*
        | ⚠️ Sağlayıcı ÖNCE yazılıyor: gerekli anahtar listesi sağlayıcıya
        | göre değişiyor. Sonra yazılsaydı marka iyzico'ya geçerken
        | anahtarları eski sağlayıcının listesine göre doğrulanırdı.
        */
        if (isset($veri['provider']) && is_string($veri['provider'])) {
            $this->ayarlar->yaz(SettingGroup::Payment, 'provider', $veri['provider']);
        }

        $saglayici = $this->saglayicilar->coz();
        $gecerli = $saglayici->gerekliAnahtarlar();

        /** @var array<string, mixed> $anahtarlar */
        $anahtarlar = $veri['keys'] ?? [];

        /*
        | ★ TANINMAYAN ANAHTAR REDDEDİLİYOR — 1E-K11'in kalbi.
        |
        | ⚠️ Sessizce kabul edilseydi `iyzico_api` yazan marka hata almaz,
        | anahtar hiçbir zaman okunmayan bir satıra yazılır, panel de
        | "tanımlı" gösterirdi. Yanlış olduğu ancak ilk gerçek müşteride
        | anlaşılırdı — ve o anda para çekilmemiş olurdu.
        */
        $tanimsiz = array_diff(array_keys($anahtarlar), $gecerli);

        if ($tanimsiz !== []) {
            return response()->json([
                'message' => 'Bu sağlayıcıda tanımlı olmayan anahtar gönderildi.',
                'errors' => ['keys' => array_values($tanimsiz)],
                'expected' => $gecerli,
            ], 422);
        }

        foreach ($anahtarlar as $anahtar => $deger) {
            if (! is_string($deger) || trim($deger) === '') {
                continue;   // boş gönderim mevcut anahtarı SİLMİYOR
            }

            // ⚠️ Her zaman şifreli — düz metin kaydedilseydi veritabanı
            // yedeğini gören herkes markanın hesabını kullanabilirdi.
            $this->ayarlar->yaz(SettingGroup::Payment, (string) $anahtar, $deger, sifreli: true);
        }

        return $this->index();
    }
}
