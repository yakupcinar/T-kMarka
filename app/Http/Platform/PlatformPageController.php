<?php

namespace App\Http\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use App\Platform\TenantDataExport;
use App\Platform\TenantLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kontrol düzlemi sayfaları — TıkMarka'yı işletenin ekranı. (4F)
 *
 * ⚠️ Buradaki her işlem BÜTÜN MARKALARA uzanıyor; sistemdeki en tehlikeli
 * yetki (3C). Rotalar `auth:platform-web` arkasında ve marka panelinden
 * AYRI guard kullanıyor.
 */
class PlatformPageController extends Controller
{
    public const SAYFA = 25;

    public function __construct(
        private readonly TenantLifecycle $yasamDongusu,
        private readonly TenantDataExport $disaAktarim,
    ) {}

    public function pano(): Response
    {
        /*
        | ⚠️ Sayımlar TEK SORGUDA. Durum başına ayrı sorgu yazmak kolaydı
        | ve marka sayısı arttıkça pano yavaşlardı.
        */
        $sayimlar = Tenant::query()
            ->selectRaw('status, count(*) as adet')
            ->groupBy('status')
            ->pluck('adet', 'status')
            ->all();

        return Inertia::render('Pano', [
            'sayimlar' => $sayimlar,
            'toplam' => array_sum($sayimlar),
        ]);
    }

    public function markalar(Request $istek): Response
    {
        $sorgu = Tenant::query()->with('plan');

        $ad = $istek->query('q');

        if (is_string($ad) && trim($ad) !== '') {
            /*
            | ⚠️ Arama GERÇEK KOLONDA (3B). `data->>'name'` üzerinden
            | yapılsaydı her satır taranırdı — ve 3B'den önce bu sorgu
            | hiçbir şey bulamıyordu.
            */
            $sorgu->where('name', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], trim($ad)).'%');
        }

        $durum = $istek->query('durum');

        if (is_string($durum) && TenantStatus::tryFrom($durum) !== null) {
            $sorgu->where('status', $durum);
        }

        $markalar = $sorgu->orderByDesc('id')->paginate(self::SAYFA)->withQueryString();

        return Inertia::render('Markalar/Liste', [
            'markalar' => $markalar->through(fn (Tenant $m) => $this->satir($m)),
            'arama' => is_string($ad) && trim($ad) !== '' ? trim($ad) : null,
            'durum' => is_string($durum) && $durum !== '' ? $durum : null,
            'durumlar' => $this->durumlar(),
        ]);
    }

    public function marka(Tenant $tenant): Response
    {
        $tenant->load(['plan', 'domains']);

        return Inertia::render('Markalar/Ayrinti', [
            'marka' => $this->ayrinti($tenant),
            'planlar' => Plan::query()->orderBy('id')->get()
                ->map(fn (Plan $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            'durumlar' => $this->durumlar(),
        ]);
    }

    public function durumDegistir(Request $istek, Tenant $tenant): RedirectResponse
    {
        $veri = $istek->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(TenantStatus::cases(), 'value'))],
        ]);

        /*
        | ⚠️ Geçiş kuralları [TenantLifecycle]'da (3C). Burada doğrudan
        | `update()` yazılsaydı "kapatılmış markayı askıya al" gibi
        | anlamsız geçişler mümkün olurdu.
        */
        $this->yasamDongusu->gecir($tenant, TenantStatus::from((string) $veri['status']));

        return back()->with('mesaj', 'Marka durumu güncellendi.');
    }

    public function planAta(Request $istek, Tenant $tenant): RedirectResponse
    {
        $veri = $istek->validate(['plan_id' => ['required', 'integer', 'exists:plans,id']]);

        /** @var int<0, max> $planId */
        $planId = (int) $veri['plan_id'];

        $tenant->plan_id = $planId;
        $tenant->save();

        return back()->with('mesaj', 'Plan atandı.');
    }

    /**
     * ★ MARKA VERİSİNİN DIŞA AKTARIMI — Faz 3'ten devredilen borç. (4F)
     *
     * ⚠️ KVKK: veri işleyen, sözleşme bitince veriyi İADE EDİP siler.
     * Silme 3G'de vardı, iade yoktu — yükümlülüğün yarısı eksikti.
     *
     * ⚠️ Kiracı bağlamı BURADA açılıyor ve İŞ BİTİNCE KAPANIYOR. Açık
     * bırakılsaydı sonraki istek yanlış şemada koşardı.
     */
    public function disaAktar(Tenant $tenant): JsonResponse
    {
        tenancy()->initialize($tenant);

        try {
            $dokum = $this->disaAktarim->dokum($tenant);
        } finally {
            tenancy()->end();
        }

        /*
        | ⚠️ `Content-Disposition: attachment` — tarayıcı dosyayı
        | GÖSTERMEK yerine indiriyor. Olmasaydı bütün marka verisi
        | ekranda açılır, tarayıcı geçmişinde ve önbelleğinde kalırdı.
        */
        return response()
            ->json($dokum, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', 'attachment; filename="'.$this->disaAktarim->dosyaAdi($tenant).'"');
    }

    /** @return array<string, mixed> */
    private function satir(Tenant $marka): array
    {
        return [
            'id' => (string) $marka->id,
            'name' => $marka->name,
            'status' => $marka->status?->value,
            'plan' => $marka->plan?->name,
            'trial_ends_at' => $marka->trial_ends_at?->toIso8601String(),
            'created_at' => $marka->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function ayrinti(Tenant $marka): array
    {
        return $this->satir($marka) + [
            'grace_ends_at' => $marka->grace_ends_at?->toIso8601String(),
            'suspended_at' => $marka->suspended_at?->toIso8601String(),
            'closed_at' => $marka->closed_at?->toIso8601String(),
            'subscription_ref' => $marka->subscription_ref,

            'domains' => $marka->domains->map(fn ($d) => [
                'domain' => $d->domain,
                'verified' => $d->verified_at !== null,
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, string>> */
    private function durumlar(): array
    {
        return array_map(
            fn (TenantStatus $d) => ['deger' => $d->value, 'ad' => $d->value],
            TenantStatus::cases(),
        );
    }
}
