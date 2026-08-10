<?php

namespace App\Http\Panel;

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\SettingLockedException;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yasal metinler — panel ucu. `izin:settings.write` arkasında.
 *
 * İki farklı işlem, iki farklı kural:
 *   taslağa yazmak   → HER ZAMAN serbest (taslak kimseye görünmüyor)
 *   yayınlamak       → mağaza AÇIKKEN yasak (müşterinin gördüğünü değiştirir)
 */
class LegalController extends Controller
{
    public function __construct(
        private readonly LegalDocumentService $belgeler,
        private readonly StorePublication $yayin,
    ) {}

    /** Üç belgenin taslak ve yayın durumu. */
    public function index(): JsonResponse
    {
        $cevap = [];

        foreach (LegalDocumentType::cases() as $tur) {
            $surum = $this->belgeler->guncelSurum($tur);

            $cevap[$tur->value] = [
                'label' => $tur->etiket(),
                'draft' => $this->belgeler->taslak($tur),
                'published_version' => $surum?->version_no,
                'published_at' => $surum?->published_at,
                'published_by' => $surum?->published_by_name,

                // Panelde "yayınlanmamış değişiklikleriniz var" rozeti için.
                'has_unpublished_changes' => $this->belgeler->yayinlanmamisDegisiklikVar($tur),
            ];
        }

        return response()->json(['documents' => $cevap]);
    }

    /**
     * Taslağa yazar.
     *
     * ⚠️ Denetim YOK ve mağaza açıkken de serbest. Taslak yarım kalabilsin
     * diye var; dışarıya çıkmıyor.
     */
    public function update(Request $istek, LegalDocumentType $tur): JsonResponse
    {
        $veri = $istek->validate([
            'content' => ['present', 'nullable', 'string', 'max:100000'],
        ]);

        $this->belgeler->taslagaYaz($tur, $veri['content']);

        return $this->index();
    }

    /**
     * Taslağı yayınlar — yeni sürüm doğurur.
     *
     * ⚠️ Mağaza AÇIKKEN yasak. Kontrol burada, serviste değil: servise
     * konsaydı LegalDocumentService → StorePublication → StoreReadiness →
     * LegalDocumentService döngüsü oluşurdu (StoreReadiness yayınlanmış
     * sürüm var mı diye bu servise soruyor).
     */
    public function publish(Request $istek, LegalDocumentType $tur): JsonResponse
    {
        if ($this->yayin->yayindaMi()) {
            throw new SettingLockedException("legal.{$tur->value}");
        }

        $kullanici = $istek->user();

        $surum = $this->belgeler->yayinla($tur, $kullanici instanceof User ? $kullanici : null);

        return response()->json([
            'message' => "{$tur->etiket()} v{$surum->version_no} yayınlandı.",
            'version_no' => $surum->version_no,
        ], 201);
    }
}
