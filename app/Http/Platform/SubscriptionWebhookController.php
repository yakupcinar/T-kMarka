<?php

namespace App\Http\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Subscription\MissingSubscriptionSecretException;
use App\Platform\Subscription\SubscriptionProvider;
use App\Platform\Subscription\SubscriptionProviderException;
use App\Platform\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Abonelik bildirimi — sağlayıcının SUNUCUSU çağırıyor. (3E)
 *
 * ⚠️ KİMLİK DOĞRULAMASI YOK, olamaz da: çağıran iyzico'nun sunucusu, bizim
 * token'ımız yok. Koruma İMZA (1E.4'ün aynısı).
 *
 * ⚠️ CSRF YOK — `routes/platform.php` `api` grubunda (3C'de gerçek curl
 * koşusu bunu yakalamıştı).
 */
class SubscriptionWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionProvider $saglayici,
        private readonly SubscriptionService $abonelik,
    ) {}

    public function __invoke(Request $istek): JsonResponse
    {
        /** @var array<string, mixed> $govde */
        $govde = $istek->all();

        $imza = (string) $istek->header('X-Subscription-Signature', '');

        try {
            if (! $this->saglayici->webhookuDogrula($govde, $imza)) {
                /*
                | ⚠️ 401 — 400 DEĞİL. İmza tutmuyorsa bu bir yetki sorunu;
                | sağlayıcı tekrar denesin diye 5xx de dönmüyoruz, çünkü
                | tekrar denese de imza yine tutmayacak.
                */
                Log::warning('Abonelik bildiriminde imza doğrulanamadı');

                return response()->json(['message' => 'İmza doğrulanamadı.'], 401);
            }

            $sonuc = $this->saglayici->webhookuCoz($govde);
        } catch (MissingSubscriptionSecretException $e) {
            /*
            | ⚠️ YUTULMUYOR — yukarı fırlıyor ve 500 + `Log::critical`
            | oluyor. Burada 400'e çevrilseydi yapılandırma eksikliği
            | "istemci hatası" gibi görünür ve üretimde bütün bildirimler
            | sessizce reddedilirdi.
            */
            throw $e;
        } catch (SubscriptionProviderException $e) {
            Log::warning('Abonelik bildirimi çözülemedi', ['hata' => $e->getMessage()]);

            return response()->json(['message' => 'Bildirim işlenemedi.'], 400);
        }

        $this->abonelik->bildirimiIsle($sonuc);

        /*
        | ⚠️ Bilinmeyen referansta da 200 dönüyor (servis `null` veriyor).
        | 404 dönseydi sağlayıcı tekrar tekrar denerdi — 1E.6'da webhook
        | zinciri tam böyle kırılmış ve tahsilat hiç kaydedilmemişti.
        */
        return response()->json(['ok' => true]);
    }
}
