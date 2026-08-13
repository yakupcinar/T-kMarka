<?php

namespace App\Http\Storefront;

use App\Domain\Returns\ReturnService;
use App\Domain\Returns\WithdrawalWindow;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * İade talebi — vitrin ucu. (2B)
 *
 * ⚠️ Müşteri yalnızca TALEP açıyor. Onay, teslim alma ve para iadesi
 * markanın işi (2B-K1).
 */
class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $iadeler,
        private readonly WithdrawalWindow $pencere,
    ) {}

    /**
     * Hangi satırlar iade edilebilir — ve ne zamana kadar.
     *
     * ⚠️ Müşteri "neden iade edemiyorum" sorusunu buradan cevaplasın diye
     * ayrı bir uç: talep reddedilince şaşırmasın.
     */
    public function show(Request $istek, string $siparis): JsonResponse
    {
        $kayit = $this->siparisiCoz($istek, $siparis);
        $kayit->load('items.fulfillmentItems.fulfillment');

        return response()->json([
            'order_number' => $kayit->order_number,
            'items' => $kayit->items->map(function (OrderItem $satir) {
                $teslim = $this->pencere->teslimTarihi($satir);

                return [
                    'id' => $satir->id,
                    'title' => $satir->product_title,
                    'sku' => $satir->sku,
                    'quantity' => $satir->quantity,

                    // ⚠️ Süre TESLİM tarihinden işliyor (2B-K2).
                    'delivered_at' => $teslim?->toIso8601String(),
                    'withdrawal_open' => $this->pencere->acikMi($satir),
                    'withdrawal_until' => $teslim?->copy()->addDays(WithdrawalWindow::GUN)->toIso8601String(),
                ];
            }),
        ]);
    }

    public function store(Request $istek, string $siparis): JsonResponse
    {
        $veri = $istek->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            /*
            | ⚠️ CAYMA mı KUSURLU ÜRÜN mü? Cayma 14 günle sınırlı,
            | kusurlu ürün değil. Ayrılmasaydı ya kusurlu ürün 15. günde
            | reddedilirdi ya cayma süresiz açık kalırdı.
            */
            'is_withdrawal' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var array<int, int> $satirlar */
        $satirlar = [];

        foreach ($veri['items'] as $satir) {
            $satirlar[(int) $satir['order_item_id']] = (int) $satir['quantity'];
        }

        $talep = $this->iadeler->talepAc(
            $this->siparisiCoz($istek, $siparis),
            $satirlar,
            (bool) ($veri['is_withdrawal'] ?? true),
            isset($veri['reason']) ? (string) $veri['reason'] : null,
        );

        return response()->json([
            'return' => [
                'uuid' => $talep->uuid,
                'status' => $talep->status->value,
                'items' => $talep->items->map(fn ($s) => [
                    'order_item_id' => $s->order_item_id,
                    'quantity' => $s->quantity,
                ]),
            ],
        ], 201);
    }

    /**
     * ⚠️ Sorgu SAHİPLİK üzerinden açılıyor (1A.5): misafir yalnızca
     * misafir siparişine, müşteri yalnızca kendi siparişine ulaşıyor.
     * 404, 403 DEĞİL — "var ama senin değil" bilgisi de sızıntıdır.
     */
    private function siparisiCoz(Request $istek, string $uuid): Order
    {
        $kullanici = $istek->user();

        $sorgu = Order::where('uuid', $uuid);

        $kullanici instanceof Customer
            ? $sorgu->where('customer_id', $kullanici->id)
            : $sorgu->whereNull('customer_id');

        return $sorgu->firstOrFail();
    }
}
