<?php

namespace App\Http\Storefront;

use App\Domain\Legal\LegalDocumentService;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Yasal metinler — VİTRİN ucu. Kimlik doğrulama yok, herkese açık.
 *
 * ★ Bu ucun varlık sebebi: `POST /api/checkout` müşteriden
 * `legal_version_id` istiyor (1D-K2 — müşteri GÖRDÜĞÜ sürüme imza atar).
 * Sürüm kimliğini veren tek yer burası.
 *
 * ⚠️ 1D.6'da iki kiracıda gerçek HTTP doğrulaması yapılırken fark edildi:
 * uç yoktu, yani sipariş vermek dışarıdan İMKÂNSIZDI. Testler kimliği
 * modelden okuduğu için hiçbiri kırılmamıştı — testin uca gitmesi yetmiyor,
 * uca giden değerin de uçtan gelmesi gerekiyor.
 *
 * ⚠️ TASLAK ÇIKMIYOR: yalnızca `guncelSurum()` okunuyor. Taslak, marka
 * üzerinde çalışırken yarım kalabilen metin; müşteriye gitmesi yayınlanmamış
 * bir sözleşmeyi imzalatmak olurdu.
 */
class LegalController extends Controller
{
    public function __construct(private readonly LegalDocumentService $metinler) {}

    /** Yayınlanmış metinlerin listesi — içerik YOK, yalnızca başlıklar. */
    public function index(): JsonResponse
    {
        $metinler = collect(LegalDocumentType::cases())
            ->map(fn (LegalDocumentType $tur) => [
                'type' => $tur->value,
                'version' => $this->metinler->guncelSurum($tur)?->version_no,
            ])
            ->filter(fn (array $satir) => $satir['version'] !== null)
            ->values();

        return response()->json(['documents' => $metinler]);
    }

    public function show(string $tur): JsonResponse
    {
        $tip = LegalDocumentType::tryFrom($tur);

        if ($tip === null) {
            abort(404);
        }

        $surum = $this->metinler->guncelSurum($tip);

        /*
        | ⚠️ Hiç yayınlanmamışsa 404 — boş metin dönmüyoruz. Boş dönseydi
        | vitrin "sözleşmeyi okudum" kutusunu boş metinle gösterir, müşteri
        | hiçbir şeye onay vermemiş olurdu.
        */
        if ($surum === null) {
            abort(404);
        }

        return response()->json([
            'document' => [
                // ★ Ödeme adımı bunu geri gönderiyor.
                'version_id' => $surum->id,
                'version' => $surum->version_no,
                'type' => $surum->type->value,
                'content' => $surum->content,
                // Panel ucuyla aynı biçim; `casts()` zaten tarih nesnesi
                // döndürüyor, JSON'a ISO-8601 olarak çıkıyor.
                'published_at' => $surum->published_at,
            ],
        ]);
    }
}
