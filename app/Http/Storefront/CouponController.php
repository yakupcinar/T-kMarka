<?php

namespace App\Http\Storefront;

use App\Domain\Cart\CartService;
use App\Domain\Promotion\CouponService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sepete kupon uygulama — vitrin ucu. (2A)
 *
 * ⚠️ Uygulamak KOTA HARCAMIYOR (2A). Kota sipariş oluşurken harcanıyor;
 * yoksa kuponu deneyip vazgeçen her müşteri kampanyadan bir kullanım
 * yerdi.
 */
class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $kuponlar,
        private readonly CartService $sepetler,
    ) {}

    public function store(Request $istek): JsonResponse
    {
        $veri = $istek->validate(['code' => ['required', 'string', 'max:40']]);

        $sepet = $this->sepetiCoz($istek);
        $kullanici = $istek->user();

        $kupon = $this->kuponlar->uygula(
            $sepet,
            (string) $veri['code'],
            $this->araToplam($sepet),
            $kullanici instanceof Customer ? $kullanici : null,
        );

        $etki = $this->kuponlar->etki($kupon->code, $this->araToplam($sepet));

        return response()->json([
            'coupon' => [
                // ⚠️ NORMALLEŞTİRİLMİŞ kod dönüyor: müşteri ne yazarsa
                // yazsın sistemin tanıdığı biçim bu.
                'code' => $kupon->code,
                'type' => $kupon->type->value,
            ],
            'discount' => $etki['discount'],
            'free_shipping' => $etki['free_shipping'],
        ]);
    }

    public function destroy(Request $istek): JsonResponse
    {
        $this->kuponlar->kaldir($this->sepetiCoz($istek));

        return response()->json(['message' => 'Kupon kaldırıldı.']);
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

    /** @return numeric-string */
    private function araToplam(Cart $sepet): string
    {
        $sepet->load('items.variant');
        $toplam = '0.00';

        foreach ($sepet->items as $satir) {
            $fiyat = $satir->variant?->price;

            if (is_numeric($fiyat)) {
                $toplam = bcadd($toplam, bcmul((string) $fiyat, (string) $satir->quantity, 2), 2);
            }
        }

        /** @var numeric-string $toplam */
        return $toplam;
    }
}
