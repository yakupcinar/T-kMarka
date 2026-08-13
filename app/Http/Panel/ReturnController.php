<?php

namespace App\Http\Panel;

use App\Domain\Returns\RefundService;
use App\Domain\Returns\RefundTotals;
use App\Domain\Returns\ReturnService;
use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * İade yönetimi — panel ucu. (2B)
 *
 * `izin:order.refund` arkasında: para geri gönderen işlem, siparişi
 * görebilen herkese açık olamaz.
 */
class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $iadeler,
        private readonly RefundService $paraIadesi,
        private readonly RefundTotals $hesap,
    ) {}

    public function index(): JsonResponse
    {
        $talepler = OrderReturn::with('order', 'items')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'returns' => collect($talepler->items())->map(fn (OrderReturn $t) => $this->ozet($t)),
            'meta' => ['total' => $talepler->total(), 'page' => $talepler->currentPage()],
        ]);
    }

    public function show(OrderReturn $return): JsonResponse
    {
        return response()->json(['return' => $this->ayrinti($return)]);
    }

    public function approve(OrderReturn $return): JsonResponse
    {
        return response()->json(['return' => $this->ozet($this->iadeler->onayla($return))]);
    }

    public function reject(Request $istek, OrderReturn $return): JsonResponse
    {
        $veri = $istek->validate(['note' => ['nullable', 'string', 'max:255']]);

        return response()->json([
            'return' => $this->ozet($this->iadeler->reddet($return, isset($veri['note']) ? (string) $veri['note'] : null)),
        ]);
    }

    /**
     * Ürün elde. ⚠️ Stok geri koyma AYRI KARAR (2B-K6).
     */
    public function receive(Request $istek, OrderReturn $return): JsonResponse
    {
        $veri = $istek->validate(['restock' => ['nullable', 'boolean']]);

        return response()->json([
            'return' => $this->ozet($this->iadeler->teslimAlindi($return, (bool) ($veri['restock'] ?? false))),
        ]);
    }

    /** Para iadesi — ancak ürün teslim alınmışsa. */
    public function refund(Request $istek, OrderReturn $return): JsonResponse
    {
        $veri = $istek->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $iade = $this->paraIadesi->iadeEt($return, isset($veri['reason']) ? (string) $veri['reason'] : null);

        return response()->json([
            'refund' => [
                'uuid' => $iade->uuid,
                'status' => $iade->status->value,
                'items_amount' => $iade->items_amount,
                'shipping_amount' => $iade->shipping_amount,

                // ⚠️ Bilgi amaçlı: `items_amount`'ın İÇİNDE (§8.2).
                'tax_amount' => $iade->tax_amount,

                'amount' => $iade->amount,
            ],
            'payment_status' => $return->order?->refresh()->payment_status->value,
        ], 201);
    }

    /** @return array<string, mixed> */
    private function ozet(OrderReturn $talep): array
    {
        return [
            'uuid' => $talep->uuid,
            'order_number' => $talep->order?->order_number,
            'status' => $talep->status->value,
            'is_withdrawal' => $talep->is_withdrawal,
            'reason' => $talep->reason,
            'created_at' => $talep->created_at,
        ];
    }

    /** @return array<string, mixed> */
    private function ayrinti(OrderReturn $talep): array
    {
        $talep->load('items.orderItem');

        return $this->ozet($talep) + [
            'items' => $talep->items->map(fn ($s) => [
                'title' => $s->orderItem?->product_title,
                'sku' => $s->orderItem?->sku,
                'quantity' => $s->quantity,
            ]),

            // Marka ne kadar geri vereceğini ÖNCEDEN görüyor.
            'estimate' => $this->hesap->hesapla($talep),
        ];
    }
}
