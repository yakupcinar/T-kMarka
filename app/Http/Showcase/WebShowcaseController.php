<?php

declare(strict_types=1);

namespace App\Http\Showcase;

use App\Domain\Catalog\ProductQuery;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Sunum katmanı — API'den ve iş kurallarından BİLEREK ayrı.
 *
 * Bu controller hiçbir kayıt YAZMAZ. İzleyiciye ürün, ödeme ve olay
 * mekanizmasının canlı veritabanında çalıştığını gösterir; buna karşılık
 * müşteri/teslimat verisi, ham webhook yükü ve ödeme sağlayıcısı sırları
 * hiçbir zaman view'a taşınmaz.
 */
final class WebShowcaseController extends Controller
{
    public function __construct(private readonly ProductQuery $urunler) {}

    public function index(): View
    {
        $products = $this->urunler->forStorefront()
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Product $product): array => $this->productCard($product));

        return view('showcase', [
            'tenantId' => (string) tenant('id'),
            'products' => $products,
            'summary' => $this->summary(),
            'activity' => $this->activityRows(),
        ]);
    }

    /**
     * Tarayıcı 10 saniyede bir yalnızca güvenli olay özetini yeniler.
     * Bu uç da yazmaz; gerçek zamanlı hissi için log veya kişisel veri
     * açmak yerine `events` tablosunun doğrudan okunmasını kullanır.
     */
    public function activity(): JsonResponse
    {
        return response()->json([
            'summary' => $this->summary(),
            'activity' => $this->activityRows(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /** @return array{products: int, payments: int, captured_payments: int, events: int, latest_payment: array{provider: string, status: string, amount: string, completed_at: string|null}|null} */
    private function summary(): array
    {
        $latestPayment = Payment::query()
            ->select(['provider', 'status', 'amount', 'completed_at'])
            ->latest('id')
            ->first();

        return [
            'products' => Product::query()->count(),
            'payments' => Payment::query()->count(),
            'captured_payments' => Payment::query()->where('status', 'captured')->count(),
            'events' => Event::query()->count(),
            'latest_payment' => $latestPayment === null ? null : [
                'provider' => $latestPayment->provider,
                'status' => $latestPayment->status->value,
                'amount' => $latestPayment->amount,
                'completed_at' => $latestPayment->completed_at?->toIso8601String(),
            ],
        ];
    }

    /** @return list<array{type: string, occurred_at: string}> */
    private function activityRows(): array
    {
        return Event::query()
            ->select(['type', 'occurred_at'])
            ->latest('occurred_at')
            ->limit(12)
            ->get()
            ->map(fn (Event $event): array => [
                'type' => $event->type->value,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array{title: string, slug: string, image: string|null, from_price: string|null, variants: int} */
    private function productCard(Product $product): array
    {
        return [
            'title' => $product->title,
            'slug' => $product->slug,
            'image' => $product->images->first()?->url(),
            'from_price' => $product->enDusukFiyat(),
            'variants' => $product->variants->count(),
        ];
    }
}
