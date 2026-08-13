<?php

namespace App\Http\Panel;

use App\Domain\Review\ReviewService;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Yorum moderasyonu — panel ucu. (2E-K2)
 *
 * ⚠️ `izin:product.write` arkasında: yorum ürünün vitrin içeriğidir,
 * katalog rolünün işi. Ayrı bir izin açılsaydı üç sistem rolünün hiçbirinde
 * bulunmaz ve pratikte yalnızca sahip moderasyon yapabilirdi.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $yorumlar) {}

    /** Moderasyon kuyruğu — varsayılan olarak BEKLEYENLER. */
    public function index(Request $istek): JsonResponse
    {
        $ham = $istek->query('status', ReviewStatus::Pending->value);
        $durum = is_string($ham) ? ReviewStatus::tryFrom($ham) : null;

        $sayfa = $this->yorumlar->kuyruk($durum)->paginate(25);

        return response()->json([
            'reviews' => collect($sayfa->items())->map(fn (Review $y) => $this->goster($y)),
            'meta' => ['page' => $sayfa->currentPage(), 'total' => $sayfa->total()],
        ]);
    }

    public function approve(Request $istek, Review $review): JsonResponse
    {
        return response()->json(['review' => $this->goster(
            $this->yorumlar->onayla($review, $this->personel($istek)),
        )]);
    }

    public function reject(Request $istek, Review $review): JsonResponse
    {
        $veri = $istek->validate(['note' => ['nullable', 'string', 'max:500']]);

        return response()->json(['review' => $this->goster(
            $this->yorumlar->reddet($review, $this->personel($istek), $veri['note'] ?? null),
        )]);
    }

    private function personel(Request $istek): User
    {
        $kullanici = $istek->user();

        // `izin:` middleware'i personel olmayanı zaten geçirmiyor.
        assert($kullanici instanceof User);

        return $kullanici;
    }

    /** @return array<string, mixed> */
    private function goster(Review $yorum): array
    {
        return [
            'uuid' => $yorum->uuid,
            'rating' => $yorum->rating,
            'title' => $yorum->title,
            'body' => $yorum->body,
            'status' => $yorum->status->value,

            // ⚠️ Panelde TAM ad var — moderasyon kararı için gerekli.
            'customer' => $yorum->customer?->name,
            'product' => $yorum->product?->title,

            'moderation_note' => $yorum->moderation_note,
            'created_at' => $yorum->created_at?->toIso8601String(),
        ];
    }
}
