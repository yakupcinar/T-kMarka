<?php

namespace App\Http\Storefront;

use App\Domain\Cart\CartService;
use App\Domain\Order\CheckoutService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sipariş oluşturma — vitrin ucu. `magaza-acik` arkasında.
 *
 * ⚠️ ÖDEME BURADA YOK. Sipariş `pending` doğuyor; ödeme 1E'de gelecek ve
 * `CheckoutService::odemeBasarili/odemeBasarisiz` dikiş yerine bağlanacak.
 * Ödemenin transaction dışında kalması bilinçli: dış servis yavaşlarsa
 * satırlar dakikalarca kilitli kalır ve tüm mağaza donar.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $odeme,
        private readonly CartService $sepetler,
    ) {}

    public function store(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'email' => ['required', 'email', 'max:190'],

            /*
            | ⚠️ Müşterinin GÖRDÜĞÜ sözleşme sürümü (1A.4 · 1D-K2).
            | Sunucu kendi bildiği güncel sürümü yazsaydı, okurken yeni
            | sürüm yayınlanan müşteri görmediği metne imza atmış olurdu.
            */
            'legal_version_id' => ['required', 'integer'],

            'shipping.full_name' => ['required', 'string', 'max:120'],
            'shipping.phone' => ['required', 'string', 'max:20'],
            'shipping.city' => ['required', 'string', 'max:60'],
            'shipping.district' => ['required', 'string', 'max:60'],
            'shipping.neighborhood' => ['nullable', 'string', 'max:100'],
            'shipping.line1' => ['required', 'string', 'max:255'],
            'shipping.line2' => ['nullable', 'string', 'max:255'],
            'shipping.postal_code' => ['nullable', 'string', 'max:10'],

            'billing' => ['nullable', 'array'],

            // Kurumsal fatura (§8.3): VKN 10 hane / TCKN 11 hane.
            // Faz 1'de yalnızca biçimsel doğrulama; e-fatura Faz 5.
            'billing_tax_number' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'billing_tax_office' => ['nullable', 'string', 'max:100'],
        ]);

        $siparis = $this->odeme->baslat($this->sepetiCoz($istek), $veri);

        return response()->json(['order' => $this->goster($siparis)], 201);
    }

    private function sepetiCoz(Request $istek): Cart
    {
        $kullanici = $istek->user();

        if ($kullanici instanceof Customer) {
            return $this->sepetler->musteriSepeti($kullanici);
        }

        $sepet = $this->sepetler->misafirSepetiBul($istek->header('X-Cart-Token'));

        abort_if($sepet === null, 404, 'Sepet bulunamadı.');

        return $sepet;
    }

    /** @return array<string, mixed> */
    private function goster(Order $siparis): array
    {
        return [
            'order_number' => $siparis->order_number,
            'uuid' => $siparis->uuid,
            'payment_status' => $siparis->payment_status->value,
            'items_total' => $siparis->items_total,
            'shipping_total' => $siparis->shipping_total,

            // ⚠️ Bilgi amaçlı — tahsil edilen tutara EKLENMİYOR (§8.2).
            'tax_total' => $siparis->tax_total,

            'grand_total' => $siparis->grand_total,
            'items' => $siparis->items->map(fn ($satir) => [
                'title' => $satir->product_title,
                'sku' => $satir->sku,
                'options' => $satir->variant_options,
                'unit_price' => $satir->unit_price,
                'quantity' => $satir->quantity,
                'line_total' => $satir->line_total,
            ]),
        ];
    }
}
