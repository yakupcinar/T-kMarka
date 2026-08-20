<?php

namespace App\Http\Platform;

use App\Http\Controllers\Controller;
use App\Platform\ReservedSubdomains;
use App\Platform\TenantProvisioning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Self-servis marka kaydı. (3D)
 *
 * ★ M-1'in şartı: "her yeni müşteri elle kurulum gerektiriyorsa ürün değil,
 * taslaktır." Bu uç o şartın karşılığı — ziyaretçi kendi markasını açıyor.
 *
 * ⚠️ KİMLİK DOĞRULAMASI YOK, olması da gerekmiyor: henüz hesabı olmayan
 * biri kaydoluyor. Korumalar başka yerde:
 *   · hız sınırı (rota tanımında)
 *   · haftalık tavan (TenantProvisioning — sertifika kotası, 3-K5)
 *   · ayrılmış alt alan adları (ReservedSubdomains)
 *
 * ⚠️ KURULUM SENKRON. Ölçüldü: şema + 28 migration 240 ms, varsayılanlar
 * 39 ms. Plan kuyruk öngörüyordu; ölçüm yanlışladı. Kuyrukta olsaydı kayıt
 * biter ama mağaza henüz olmazdı — kullanıcıya "hazır" diyemezdik.
 */
class SignupController extends Controller
{
    public function __construct(private readonly TenantProvisioning $kurulum) {}

    public function store(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'brand_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190'],

            /*
            | ⚠️ Parola KULLANICIDAN alınıyor — `tenant:create`'teki `123`
            | varsayılanı self-servis akışta YOK. Varsayılan bırakılsaydı
            | internetten açılan her marka aynı bilinen parolayla doğardı.
            */
            'password' => ['required', 'string', 'min:8', 'max:190'],

            /*
            | ⚠️ İsteğe bağlı: verilmezse marka adından üretiliyor.
            | Zorunlu olsaydı kayıt formu teknik bir soru sormuş olurdu
            | ("alt alan adınız ne olsun?").
            */
            'subdomain' => ['nullable', 'string', 'max:63'],
        ]);

        $kokAlanAdi = (string) config('tenancy.signup_root_domain', 'localhost');

        $alanAdi = isset($veri['subdomain']) && trim((string) $veri['subdomain']) !== ''
            ? strtolower(trim((string) $veri['subdomain'])).'.'.$kokAlanAdi
            : $this->kurulum->altAlanAdiUret((string) $veri['brand_name'], $kokAlanAdi);

        /*
        | ★ ONAY BEKLİYOR (4.5N). Marka kurulup yayına GİRMİYOR; platform
        | yöneticisi onaylayana kadar panel de vitrin de kapalı.
        |
        | ⚠️ M-1'in şartı ("elle kurulum gerektiren ürün değil, taslaktır")
        | BOZULMUYOR: kurulumun tamamı hâlâ otomatik. Onay bir kurulum
        | adımı değil, bir KARAR — ve tek düğme.
        */
        $marka = $this->kurulum->ac(
            (string) $veri['brand_name'],
            $alanAdi,
            (string) $veri['email'],
            (string) $veri['password'],
            onayBekliyor: true,
        );

        return response()->json([
            'tenant' => [
                'id' => $marka->id,
                'name' => $marka->name,
                'status' => $marka->status?->value,
                'trial_ends_at' => $marka->trial_ends_at?->toIso8601String(),
            ],

            /*
            | ⚠️ Adres AÇIKÇA dönüyor: kullanıcı nereye gideceğini bilmeli.
            | Dönmeseydi kayıt "başarılı" der ama müşteri mağazasını
            | bulamazdı.
            */
            'domain' => $marka->domains->first()?->domain,

            /*
            | ⚠️ Mağazanın KAPALI doğduğu söyleniyor. Söylenmeseydi marka
            | vitrinine bakar, 503 görür ve bozuk sanardı (1A.4).
            */
            'message' => 'Markanız oluşturuldu. Mağazanız kapalı başlıyor; panelden şirket bilgilerini doldurup yasal metinleri yayınlayınca satışa açabilirsiniz.',
        ], 201);
    }

    /** Alt alan adı müsait mi? — kayıt formu anlık kontrol için çağırıyor. */
    public function checkSubdomain(Request $istek): JsonResponse
    {
        $veri = $istek->validate(['subdomain' => ['required', 'string', 'max:63']]);

        $kokAlanAdi = (string) config('tenancy.signup_root_domain', 'localhost');
        $aday = strtolower(trim((string) $veri['subdomain']));

        /*
        | ⚠️ Ayrılmış ad ile DOLU ad AYRI sebep döndürüyor: kullanıcı ne
        | yapacağını bilmeli. "Kullanılamaz" demek yeterli olmazdı —
        | "panel" yazan biri neden reddedildiğini anlamazdı.
        */
        $ayrilmis = ReservedSubdomains::ayrilmisMi($aday);
        $dolu = Domain::where('domain', $aday.'.'.$kokAlanAdi)->exists();

        return response()->json([
            'available' => ! $ayrilmis && ! $dolu,
            'reason' => match (true) {
                $ayrilmis => 'reserved',
                $dolu => 'taken',
                default => null,
            },
            'domain' => $aday.'.'.$kokAlanAdi,
        ]);
    }
}
