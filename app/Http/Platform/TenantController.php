<?php

namespace App\Http\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use App\Platform\TenantLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Marka yönetimi — kontrol düzlemi. (3C)
 *
 * ⚠️ `auth:platform` arkasında. Bu uçlar BÜTÜN MARKALARI görüyor; marka
 * personelinin buraya erişmesi sistemdeki en büyük sızıntı olurdu.
 *
 * ⚠️ Durum geçiş kuralları [App\Platform\TenantLifecycle]'da, burada DEĞİL:
 * aynı geçişler zamanlanmış görevlerden de tetiklenecek ve HTTP'den
 * geçmeyecekler.
 */
class TenantController extends Controller
{
    public function __construct(private readonly TenantLifecycle $yasam) {}

    /** Marka listesi — ada göre arama ve duruma göre süzme. */
    public function index(Request $istek): JsonResponse
    {
        $sorgu = Tenant::query()->with('plan');

        $ad = $istek->query('q');

        if (is_string($ad) && trim($ad) !== '') {
            /*
            | ⚠️ Arama GERÇEK KOLONDA (3B). `data->>'name'` üzerinden
            | yapılsaydı her satır taranırdı — ve zaten 3B'den önce bu
            | sorgu hiçbir şey bulamazdı.
            */
            $sorgu->where('name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], trim($ad)).'%');
        }

        $durum = $istek->query('status');

        if (is_string($durum) && TenantStatus::tryFrom($durum) !== null) {
            $sorgu->where('status', $durum);
        }

        $sayfa = $sorgu->orderBy('name')->paginate(25);

        return response()->json([
            'tenants' => collect($sayfa->items())->map(fn (Tenant $t) => $this->goster($t)),
            'meta' => ['page' => $sayfa->currentPage(), 'total' => $sayfa->total()],
        ]);
    }

    public function show(string $tenant): JsonResponse
    {
        $marka = Tenant::with('plan')->find($tenant);

        if ($marka === null) {
            return response()->json(['message' => 'Marka bulunamadı.'], 404);
        }

        return response()->json(['tenant' => $this->goster($marka, ayrintili: true)]);
    }

    /** Durum değiştirir — geçiş kuralları serviste. */
    public function status(Request $istek, string $tenant): JsonResponse
    {
        $marka = Tenant::find($tenant);

        if ($marka === null) {
            return response()->json(['message' => 'Marka bulunamadı.'], 404);
        }

        $veri = $istek->validate([
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ]);

        $yeni = TenantStatus::from((string) $veri['status']);

        return response()->json(['tenant' => $this->goster($this->yasam->gecir($marka, $yeni))]);
    }

    public function assignPlan(Request $istek, string $tenant): JsonResponse
    {
        $marka = Tenant::find($tenant);

        if ($marka === null) {
            return response()->json(['message' => 'Marka bulunamadı.'], 404);
        }

        $veri = $istek->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
        ]);

        $plan = Plan::where('code', $veri['plan_code'])->firstOrFail();

        return response()->json(['tenant' => $this->goster($this->yasam->planAta($marka, $plan))]);
    }

    /** @return array<string, mixed> */
    private function goster(Tenant $marka, bool $ayrintili = false): array
    {
        $temel = [
            'id' => $marka->id,
            'name' => $marka->name,
            'status' => $marka->status?->value,
            'plan' => $marka->plan?->code,
            'trial_ends_at' => $marka->trial_ends_at?->toIso8601String(),
            'created_at' => $marka->created_at->toIso8601String(),
        ];

        if (! $ayrintili) {
            return $temel;
        }

        return $temel + [
            'grace_ends_at' => $marka->grace_ends_at?->toIso8601String(),
            'suspended_at' => $marka->suspended_at?->toIso8601String(),
            'closed_at' => $marka->closed_at?->toIso8601String(),
            'domains' => $marka->domains->pluck('domain'),

            /*
            | ⚠️ `subscription_ref` ve `data` DIŞARIDA. Sağlayıcı referansı
            | ve paketin iç alanları panelin işine yaramıyor; cevaba
            | konsaydı gereksiz yere dışarı sızarlardı.
            */
        ];
    }
}
