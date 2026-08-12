<?php

namespace App\Http\Storefront;

use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ödeme başlatma — vitrin ucu. (1E.3)
 *
 * ⚠️ Bu uç PARA TAHSİL ETMİYOR. Müşteriyi sağlayıcıya yolluyor; sonuç
 * dakikalar sonra webhook'la gelecek (1E.4). Cevaptaki `redirect_url`
 * bir başarı bildirimi değil, "şuraya git" talimatı.
 */
class PaymentController extends Controller
{
    /** Müşterinin ödeme sonrası geri geleceği ekran (1E.5). */
    public const DONUS_YOLU = '/odeme/donus';

    public function __construct(private readonly PaymentService $odemeler) {}

    public function store(Request $istek, string $siparis): JsonResponse
    {
        $sonuc = $this->odemeler->baslat(
            $this->siparisiCoz($istek, $siparis),

            /*
            | ★ DÖNÜŞ ADRESİ SUNUCUDA ÜRETİLİYOR.
            |
            | ⚠️ İstek gövdesinden alınsaydı saldırgan kendi sitesini
            | yazardı: müşteri ödeme sonrası oraya düşer, sahte bir
            | "başarılı" ekranı görür ve siparişinin durduğunu sanırdı.
            | Açık yönlendirme (open redirect) açığının tam tanımı.
            */
            $istek->getSchemeAndHttpHost().self::DONUS_YOLU,
        );

        return response()->json([
            /*
            | ⚠️ İstemci bunu bir SONUÇ sanmamalı: ödeme henüz olmadı.
            | Alan adı bilerek `redirect_url` — `payment_result` gibi bir
            | ad bir gün birinin ona bakıp siparişi ödenmiş saymasına
            | davetiye olurdu.
            */
            'redirect_url' => $sonuc->yonlendirmeAdresi,
            'reference' => $sonuc->saglayiciReferansi,
        ]);
    }

    /**
     * Siparişi DARALTILMIŞ sorgudan çözer. (1A.5 deseni)
     *
     * ⚠️ Düz `Order::where('uuid', …)` kullanılsaydı, uuid'yi ele geçiren
     * biri başkasının siparişinin tutarını görebilir ve ödemesini
     * başlatabilirdi. Sorgu sahiplik üzerinden açılınca yabancı satır
     * sonuç kümesine hiç girmiyor.
     *
     * ⚠️ 404, 403 DEĞİL: "böyle bir sipariş var ama senin değil" bilgisi
     * de sızıntıdır (1A.5'te karara bağlandı).
     */
    private function siparisiCoz(Request $istek, string $uuid): Order
    {
        $kullanici = $istek->user();

        $sorgu = Order::where('uuid', $uuid);

        if ($kullanici instanceof Customer) {
            $sorgu->where('customer_id', $kullanici->id);
        } else {
            // Misafir yalnızca MİSAFİR siparişini ödeyebilir.
            $sorgu->whereNull('customer_id');
        }

        return $sorgu->firstOrFail();
    }
}
