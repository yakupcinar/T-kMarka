<?php

namespace App\Http\Panel;

use App\Domain\Order\FulfillmentService;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\FulfillmentItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sipariş ve kargo ekranları. (4E)
 *
 * ★ 4-K3: API controller'ı değil `app/Domain/` servisi çağrılıyor.
 *
 * ★ YETKİ ÜÇ KATMANLI ve rotalarda ayrı ayrı yazılı:
 *
 * ```
 * order.view     görebilir
 * order.fulfill  kargolayabilir
 * order.refund   para iadesi yapabilir  (4E'de iade ekranı)
 * ```
 *
 * ⚠️ Tek izne indirgemek en kolay yoldu ve depo personeline para iadesi
 * yetkisi vermek demekti. Ayrım Faz 1'de kuruldu, arayüz onu BOZMUYOR.
 */
class OrderPageController extends Controller
{
    public const SAYFA = 25;

    public function __construct(private readonly FulfillmentService $sevkiyat) {}

    public function index(Request $istek): Response
    {
        $durum = (string) $istek->query('durum', '');

        $sorgu = Order::query()->with('items');

        if ($durum !== '') {
            $sorgu->where('fulfillment_status', $durum);
        }

        /*
        | ⚠️ SORUNLU SİPARİŞLER ÖNCE — panel API'siyle aynı sıralama.
        | Stok açığı olan sipariş listenin başında duruyor; tarihe göre
        | sıralansaydı yoğun bir günde uyarı üçüncü sayfaya düşer ve
        | pratikte görünmez olurdu.
        */
        $siparisler = $sorgu
            ->orderByDesc('stock_shortfall')
            ->orderByDesc('placed_at')
            ->paginate(self::SAYFA)
            ->withQueryString();

        return Inertia::render('Siparisler/Liste', [
            'siparisler' => $siparisler->through(fn (Order $s) => $this->satir($s)),
            'durum' => $durum === '' ? null : $durum,
        ]);
    }

    public function show(Order $siparis): Response
    {
        $siparis->load(['items', 'fulfillments.items', 'legalVersion']);

        return Inertia::render('Siparisler/Ayrinti', [
            'siparis' => $this->ayrinti($siparis),
        ]);
    }

    public function paketOlustur(Request $istek, Order $siparis): RedirectResponse
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

        /*
        | ⚠️ AŞIRI SEVKİYAT kontrolü servisin içinde (1D). Burada
        | tekrarlanmıyor: aynı kural iki yerde tutulsaydı biri
        | güncellenmeden kalırdı ve panelden sipariş edilenden fazlası
        | kargolanabilirdi.
        */
        $this->sevkiyat->olustur(
            $siparis,
            $satirlar,
            $veri['carrier'] ?? null,
            $veri['tracking_number'] ?? null,
        );

        return back()->with('mesaj', 'Paket oluşturuldu.');
    }

    public function kargoyaVer(Request $istek, Order $siparis, Fulfillment $paket): RedirectResponse
    {
        $this->paketiDogrula($siparis, $paket);

        $veri = $istek->validate([
            'carrier' => ['nullable', 'string', 'max:60'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        $this->sevkiyat->kargoyaVer($paket, $veri['carrier'] ?? null, $veri['tracking_number'] ?? null);

        return back()->with('mesaj', 'Paket kargoya verildi.');
    }

    public function teslimEdildi(Order $siparis, Fulfillment $paket): RedirectResponse
    {
        $this->paketiDogrula($siparis, $paket);

        $this->sevkiyat->teslimEdildi($paket);

        return back()->with('mesaj', 'Paket teslim edildi olarak işaretlendi.');
    }

    public function paketIptal(Order $siparis, Fulfillment $paket): RedirectResponse
    {
        $this->paketiDogrula($siparis, $paket);

        $this->sevkiyat->iptal($paket);

        return back()->with('mesaj', 'Paket iptal edildi.');
    }

    /**
     * Paket SİPARİŞE DARALTILMIŞ doğrulanıyor (1A.5 deseni).
     *
     * ⚠️ Yalnızca `Fulfillment` bağlansaydı, bir siparişi görme yetkisi
     * olan personel BAŞKA siparişin paketini kargoya verebilirdi. İç içe
     * rota kapsaması bilerek kullanılmıyor (4D-K3'ün gerekçesi): koruma
     * görünür ve ölçülebilir olsun.
     */
    private function paketiDogrula(Order $siparis, Fulfillment $paket): void
    {
        abort_unless($paket->order_id === $siparis->id, 404);
    }

    /** @return array<string, mixed> */
    private function satir(Order $siparis): array
    {
        return [
            'uuid' => $siparis->uuid,
            'order_number' => $siparis->order_number,
            'email' => $siparis->email,
            'placed_at' => $siparis->placed_at?->toIso8601String(),
            'grand_total' => $siparis->grand_total,
            'payment_status' => $siparis->payment_status->value,
            'fulfillment_status' => $siparis->fulfillment_status->value,

            /*
            | ⚠️ STOK AÇIĞI listede GÖRÜNÜYOR. Yalnızca ayrıntı sayfasında
            | olsaydı marka onu ancak siparişi açınca fark ederdi.
            */
            'stock_shortfall' => (bool) $siparis->stock_shortfall,
            'item_count' => $siparis->items->sum('quantity'),
        ];
    }

    /** @return array<string, mixed> */
    private function ayrinti(Order $siparis): array
    {
        return $this->satir($siparis) + [
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
            | Onaylanan sözleşmenin SÜRÜMÜ — marka "müşteri neyi onayladı"
            | sorusunu buradan cevaplıyor (1A.4 · 1D-K2).
            */
            'contract_version' => $siparis->legalVersion?->version_no,

            'items' => $siparis->items->map(fn (OrderItem $satir) => [
                'id' => $satir->id,
                'title' => $satir->product_title,
                'sku' => $satir->sku,
                'options' => $satir->variant_options,
                'quantity' => $satir->quantity,
                'shipped' => $this->sevkiyat->sevkEdilenAdet($satir),
                'unit_price' => $satir->unit_price,
                'line_total' => $satir->line_total,
            ])->values()->all(),

            'fulfillments' => $siparis->fulfillments->map(fn (Fulfillment $paket) => [
                'uuid' => $paket->uuid,
                'status' => $paket->status->value,
                'carrier' => $paket->carrier,
                'tracking_number' => $paket->tracking_number,
                'items' => $paket->items->map(fn (FulfillmentItem $s) => [
                    'order_item_id' => $s->order_item_id,
                    'quantity' => $s->quantity,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
