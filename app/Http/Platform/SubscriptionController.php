<?php

namespace App\Http\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use App\Platform\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonelik uçları — kontrol düzleminde. (3E)
 *
 * ⚠️ `auth:platform` arkasında: bugün aboneliği BİZ başlatıyoruz (marka
 * arayüzü Faz 4'te). Marka panelinden başlatılsaydı `auth:staff` gerekirdi
 * ve kart verisi marka şemasından geçerdi.
 *
 * ⚠️ KART VERİSİ HİÇBİR YERE YAZILMIYOR — ne veritabanına ne günlüğe.
 * Saklamak bizi PCI kapsamına sokardı; saklayan taraf sağlayıcı.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $abonelik) {}

    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => Plan::where('is_active', true)->orderBy('position')->get()->map(fn (Plan $p) => [
                'code' => $p->code,
                'name' => $p->name,
                'price' => $p->price,
                'currency' => $p->currency,
                'interval' => $p->interval,

                // ⚠️ `null` = sınırsız; panel bunu böyle göstermeli.
                'max_products' => $p->max_products,
                'max_staff' => $p->max_staff,
                'features' => $p->features,
            ]),
        ]);
    }

    /** Abonelik başlatır — kart alınır, saklanmaz. */
    public function subscribe(Request $istek, string $tenant): JsonResponse
    {
        $marka = Tenant::find($tenant);

        if ($marka === null) {
            return response()->json(['message' => 'Marka bulunamadı.'], 404);
        }

        $veri = $istek->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
            'card.number' => ['required', 'string'],
            'card.holder' => ['required', 'string'],
            'card.expiry' => ['required', 'string'],
            'card.cvc' => ['required', 'string'],
        ]);

        $plan = Plan::where('code', $veri['plan_code'])->firstOrFail();

        /** @var array<string, string> $kart */
        $kart = $istek->input('card');

        $marka = $this->abonelik->baslat($marka, $plan, $kart);

        return response()->json(['tenant' => [
            'id' => $marka->id,
            'status' => $marka->status?->value,
            'plan' => $marka->plan?->code,

            /*
            | ⚠️ `subscription_ref` CEVAPTA YOK: sağlayıcı referansı
            | panelin işine yaramıyor ve dışarı sızmasının bir sebebi yok.
            */
        ]]);
    }

    public function cancel(string $tenant): JsonResponse
    {
        $marka = Tenant::find($tenant);

        if ($marka === null) {
            return response()->json(['message' => 'Marka bulunamadı.'], 404);
        }

        $marka = $this->abonelik->iptal($marka);

        return response()->json(['tenant' => [
            'id' => $marka->id,
            'status' => $marka->status?->value,
        ]]);
    }
}
