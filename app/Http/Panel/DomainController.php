<?php

namespace App\Http\Panel;

use App\Http\Controllers\Controller;
use App\Platform\Domains\CustomDomainService;
use App\Platform\Models\Domain;
use App\Platform\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Markanın kendi alan adını bağlaması — PANEL ucu. (3H)
 *
 * ⚠️ `izin:settings.write` arkasında: alan adı mağazanın kimliği, katalog
 * değil. Yanlış bağlanan bir alan adı mağazayı erişilemez yapabilir.
 *
 * ⚠️ Alan adları MERKEZ tabloda (`domains`) ama bu uç MARKA panelinde:
 * marka yalnızca KENDİ alan adlarını görüyor ve `tenant_id` isteğe göre
 * değil bağlamdan geliyor — istekten alınsaydı marka başka markanın alan
 * adını yönetebilirdi.
 */
class DomainController extends Controller
{
    public function __construct(private readonly CustomDomainService $alanAdlari) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'domains' => $this->markaninAlanAdlari()->map(fn (Domain $d) => $this->goster($d))->values(),
        ]);
    }

    public function store(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'domain' => ['required', 'string', 'max:253'],
        ]);

        $marka = $this->marka();

        $kayit = $this->alanAdlari->ekle($marka, (string) $veri['domain']);

        return response()->json([
            'domain' => $this->goster($kayit),

            /*
            | ⚠️ Talimat CEVAPTA dönüyor. Dönmeseydi marka ne yapacağını
            | bilemez ve "ekledim ama çalışmıyor" derdi — bu adım İNSAN
            | İŞİ ve destek yükünün tamamı burada.
            */
            'instructions' => $this->alanAdlari->talimat($kayit),
            'message' => 'Alan adı eklendi. Aşağıdaki kayıtlardan BİRİNİ DNS panelinize ekleyip "kontrol et" deyin.',
        ], 201);
    }

    /** Markanın DNS kaydını ekleyip eklemediğini kontrol eder. */
    public function verify(string $domain): JsonResponse
    {
        $kayit = $this->markaninAlanAdlari()->firstWhere('domain', strtolower(trim($domain)));

        if (! $kayit instanceof Domain) {
            return response()->json(['message' => 'Alan adı bulunamadı.'], 404);
        }

        $dogrulandi = $this->alanAdlari->dogrula($kayit);

        /*
        | ⚠️ Başarısız kontrol HATA DEĞİL — 200 dönüyor. 4xx dönseydi panel
        | "bir şeyler bozuk" gösterirdi; oysa en olağan durum bu: DNS
        | değişikliği yayılmamış olabiliyor.
        */
        return response()->json([
            'verified' => $dogrulandi,
            'domain' => $this->goster($kayit->refresh()),
            'message' => $dogrulandi
                ? 'Alan adı doğrulandı. Siteniz kısa süre içinde bu adresten açılacak.'
                : 'Kaydı henüz göremiyoruz. DNS değişikliklerinin yayılması birkaç saat sürebilir.',
            'instructions' => $dogrulandi ? null : $this->alanAdlari->talimat($kayit),
        ]);
    }

    public function destroy(string $domain): JsonResponse
    {
        $kayit = $this->markaninAlanAdlari()->firstWhere('domain', strtolower(trim($domain)));

        if (! $kayit instanceof Domain) {
            return response()->json(['message' => 'Alan adı bulunamadı.'], 404);
        }

        /*
        | ⚠️ SON alan adı silinemiyor: silinirse marka hiçbir adresten
        | erişilemez hâle gelir ve paneline girip düzeltemez.
        */
        if ($this->markaninAlanAdlari()->count() <= 1) {
            return response()->json([
                'message' => 'Son alan adı silinemez — markanız erişilemez hâle gelirdi.',
            ], 409);
        }

        $kayit->delete();

        return response()->json(status: 204);
    }

    /** @return Collection<int, Domain> */
    private function markaninAlanAdlari(): Collection
    {
        return Domain::where('tenant_id', $this->marka()->id)->orderBy('id')->get();
    }

    private function marka(): Tenant
    {
        $marka = tenant();

        // Kapı görevlisi kiracıyı zaten çözmüş olmalı.
        assert($marka instanceof Tenant);

        return $marka;
    }

    /** @return array<string, mixed> */
    private function goster(Domain $kayit): array
    {
        return [
            'domain' => $kayit->domain,
            'verified' => $kayit->verified_at !== null,
            'verified_at' => $kayit->verified_at?->toIso8601String(),
        ];
    }
}
