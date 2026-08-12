<?php

namespace App\Http\Panel;

use App\Domain\Order\FulfillmentService;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sipariş ve sevkiyat — panel ucu.
 *
 * ⚠️ İki AYRI izin: `order.view` görüntüleme, `order.fulfill` kargoya
 * verme. İkisi de 1A.3'ten beri tanımlıydı ama hiçbir kapıyı beklemiyordu;
 * ilk kez burada gerçek oluyorlar.
 *
 * "Sipariş & Destek" rolünde ikisi de var ama `order.refund` YOK —
 * depocu örneği (1A.3): siparişi görür, kargoya verir, para iadesi yapamaz.
 */
class OrderController extends Controller
{
    public function __construct(private readonly FulfillmentService $sevkiyat) {}

    public function index(): JsonResponse
    {
        /*
        | ⚠️ SORUNLU SİPARİŞLER ÖNCE. Stok açığı olan sipariş listenin
        | başında duruyor — marka onu aramak zorunda kalmasın diye.
        | Tarihe göre sıralansaydı, yoğun bir günde uyarı üçüncü sayfaya
        | düşer ve pratikte görünmez olurdu.
        */
        $siparisler = Order::with('items')
            ->orderByDesc('stock_shortfall')
            ->orderByDesc('placed_at')
            ->paginate(50);

        return response()->json([
            'orders' => collect($siparisler->items())->map(fn (Order $s) => $this->ozet($s)),
            'meta' => ['total' => $siparisler->total(), 'page' => $siparisler->currentPage()],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['order' => $this->ayrinti($order)]);
    }

    /** Paket oluşturur — kısmi olabilir. */
    public function storeFulfillment(Request $istek, Order $order): JsonResponse
    {
        $veri = $istek->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'carrier' => ['nullable', 'string', 'max:60'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var array<int, int> $satirlar */
        $satirlar = [];

        foreach ($veri['items'] as $satir) {
            $satirlar[(int) $satir['order_item_id']] = (int) $satir['quantity'];
        }

        $paket = $this->sevkiyat->olustur($order, $satirlar, $veri['carrier'] ?? null, $veri['tracking_number'] ?? null);

        return response()->json([
            'fulfillment' => $this->paketGoster($paket),
            'fulfillment_status' => $order->refresh()->fulfillment_status->value,
        ], 201);
    }

    public function ship(Request $istek, Order $order, string $fulfillment): JsonResponse
    {
        $veri = $istek->validate([
            'carrier' => ['nullable', 'string', 'max:60'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        $paket = $this->sevkiyat->kargoyaVer(
            $this->paketiBul($order, $fulfillment),
            $veri['carrier'] ?? null,
            $veri['tracking_number'] ?? null,
        );

        return response()->json(['fulfillment' => $this->paketGoster($paket)]);
    }

    public function deliver(Order $order, string $fulfillment): JsonResponse
    {
        $paket = $this->sevkiyat->teslimEdildi($this->paketiBul($order, $fulfillment));

        return response()->json(['fulfillment' => $this->paketGoster($paket)]);
    }

    public function cancelFulfillment(Order $order, string $fulfillment): JsonResponse
    {
        $paket = $this->sevkiyat->iptal($this->paketiBul($order, $fulfillment));

        return response()->json([
            'fulfillment' => $this->paketGoster($paket),
            'fulfillment_status' => $order->refresh()->fulfillment_status->value,
        ]);
    }

    /**
     * ⚠️ Paket SİPARİŞE DARALTILMIŞ sorgudan çözülüyor (1A.5 deseni):
     * başka siparişin paketi sonuç kümesine hiç girmiyor → 404.
     */
    private function paketiBul(Order $siparis, string $uuid): Fulfillment
    {
        /** @var Fulfillment $paket */
        $paket = $siparis->fulfillments()->where('uuid', $uuid)->firstOrFail();

        return $paket;
    }

    /** @return array<string, mixed> */
    private function ozet(Order $siparis): array
    {
        return [
            'uuid' => $siparis->uuid,
            'order_number' => $siparis->order_number,
            'email' => $siparis->email,
            'payment_status' => $siparis->payment_status->value,
            'fulfillment_status' => $siparis->fulfillment_status->value,
            'grand_total' => $siparis->grand_total,
            'placed_at' => $siparis->placed_at,

            /*
            | ★ 1E-K5 — "para geldi, mal yok" uyarısı LİSTEDE.
            |
            | ⚠️ Yalnızca ayrıntıda gösterilseydi marka satırı açmadan
            | göremezdi ve işaret sessiz kalırdı. Shopify'ın uyarısı tam
            | buydu: sorun eksi stoğa izin vermek değil, HABER VERMEDEN
            | izin vermek. Karar markanın (tedarik et ya da iade et) ama
            | kararı verebilmesi için önce GÖRMESİ gerekiyor.
            */
            'stock_shortfall' => $siparis->stock_shortfall,
        ];
    }

    /** @return array<string, mixed> */
    private function ayrinti(Order $siparis): array
    {
        $siparis->load(['items', 'fulfillments.items', 'legalVersion']);

        return $this->ozet($siparis) + [
            'items_total' => $siparis->items_total,
            'shipping_total' => $siparis->shipping_total,
            'tax_total' => $siparis->tax_total,

            'shipping_address' => [
                'full_name' => $siparis->shipping_full_name,
                'phone' => $siparis->shipping_phone,
                'city' => $siparis->shipping_city,
                'district' => $siparis->shipping_district,
                'line1' => $siparis->shipping_line1,
            ],

            /*
            | Onaylanan sözleşmenin KENDİSİ — sürüm numarası değil.
            | Marka "müşteri neyi onayladı" sorusunu buradan cevaplıyor.
            */
            'contract_version' => $siparis->legalVersion?->version_no,

            'items' => $siparis->items->map(fn (OrderItem $satir) => [
                'id' => $satir->id,
                'title' => $satir->product_title,
                'sku' => $satir->sku,
                'quantity' => $satir->quantity,
                'shipped' => $this->sevkiyat->sevkEdilenAdet($satir),
                'unit_price' => $satir->unit_price,
                'line_total' => $satir->line_total,
                'tax_amount' => $satir->tax_amount,
            ]),

            'fulfillments' => $siparis->fulfillments->map(fn (Fulfillment $p) => $this->paketGoster($p)),
        ];
    }

    /** @return array<string, mixed> */
    private function paketGoster(Fulfillment $paket): array
    {
        return [
            'uuid' => $paket->uuid,
            'status' => $paket->status->value,
            'carrier' => $paket->carrier,
            'tracking_number' => $paket->tracking_number,
            'shipped_at' => $paket->shipped_at,
            'delivered_at' => $paket->delivered_at,
            'items' => $paket->items->map(fn ($k) => [
                'order_item_id' => $k->order_item_id,
                'quantity' => $k->quantity,
            ]),
        ];
    }
}
