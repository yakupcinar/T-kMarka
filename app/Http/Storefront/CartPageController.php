<?php

namespace App\Http\Storefront;

use App\Domain\Cart\CartService;
use App\Domain\Promotion\CouponService;
use App\Domain\Promotion\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sepet sayfası ve sepet işlemleri. (4B)
 *
 * ★ FORMLAR JAVASCRIPT'SİZ ÇALIŞIYOR: her işlem bir `<form method="post">`,
 * cevabı da yönlendirme. Sunucuda render edilen bir vitrinin JS'e bağımlı
 * olması M-3'ün amacını bozardı — müşteri betik yüklenmeden de alışveriş
 * yapabilmeli.
 *
 * ⚠️ Bu yüzden hepsi PRG deseninde (POST → Redirect → GET): doğrudan HTML
 * dönseydi müşterinin sayfayı yenilemesi aynı ürünü tekrar sepete eklerdi.
 */
class CartPageController extends Controller
{
    public function __construct(
        private readonly CartService $sepetler,
        private readonly CartResolver $coz,
        private readonly CouponService $kuponlar,
    ) {}

    public function show(Request $istek): View
    {
        $sepet = $this->coz->bul($istek);

        $sepet?->load('items.variant.product.images');

        return view('storefront.sade.sepet', [
            'sepet' => $sepet,
            'engeller' => $sepet === null ? [] : $this->sepetler->engeller($sepet),
        ]);
    }

    public function ekle(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'variant_uuid' => ['required', 'uuid', 'exists:product_variants,uuid'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAKS_ADET],
        ]);

        /** @var ProductVariant $varyant */
        $varyant = ProductVariant::where('uuid', $veri['variant_uuid'])->firstOrFail();

        /*
        | ⚠️ EKLEME sepeti AÇAR — görüntüleme açmaz. Ayrım [CartResolver]'da.
        */
        $sepet = $this->coz->bulYaDaAc($istek);

        $this->sepetler->ekle($sepet, $varyant, (int) ($veri['quantity'] ?? 1));

        return $this->coz->cerezle(
            redirect()->route('vitrin.sepet')->with('mesaj', 'Ürün sepete eklendi.'),
            $sepet,
        );
    }

    public function guncelle(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'variant_uuid' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAKS_ADET],
        ]);

        $satir = $this->satiriBul($istek, (string) $veri['variant_uuid']);

        $this->sepetler->adetDegistir($satir, (int) $veri['quantity']);

        return redirect()->route('vitrin.sepet');
    }

    public function sil(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(['variant_uuid' => ['required', 'uuid']]);

        $this->sepetler->satirSil($this->satiriBul($istek, (string) $veri['variant_uuid']));

        return redirect()->route('vitrin.sepet')->with('mesaj', 'Ürün sepetten çıkarıldı.');
    }

    public function kupon(Request $istek): RedirectResponse
    {
        $sepet = $this->coz->bul($istek);

        abort_if($sepet === null, 404, 'Sepet bulunamadı.');

        // Boş kod = kuponu kaldır. Ayrı bir rota açmak gerekmiyor.
        $kod = trim((string) $istek->input('kod', ''));

        if ($kod === '') {
            $this->kuponlar->kaldir($sepet);

            return redirect()->route('vitrin.sepet')->with('mesaj', 'Kupon kaldırıldı.');
        }

        try {
            $this->kuponlar->uygula($sepet, $kod, $this->urunToplami($sepet));
        } catch (InvalidCouponException $hata) {
            /*
            | ⚠️ Kupon hatası SAYFADA gösteriliyor, istisna olarak dışarı
            | sızmıyor: geçersiz kod müşterinin hatası değil, sıradan bir
            | sonuç. 500 ekranı görmesi anlamsız olurdu.
            */
            return redirect()->route('vitrin.sepet')->with('hata', $hata->getMessage());
        }

        return redirect()->route('vitrin.sepet')->with('mesaj', 'Kupon uygulandı.');
    }

    /**
     * ⚠️ Satır SEPETE DARALTILMIŞ sorgudan çözülüyor (1A.5 deseni):
     * başkasının sepetindeki satır sonuç kümesine hiç girmiyor → 404.
     */
    private function satiriBul(Request $istek, string $variantUuid): CartItem
    {
        $sepet = $this->coz->bul($istek);

        abort_if($sepet === null, 404, 'Sepet bulunamadı.');

        /** @var CartItem $satir */
        $satir = $sepet->items()
            ->whereHas('variant', fn ($q) => $q->where('uuid', $variantUuid))
            ->firstOrFail();

        return $satir;
    }

    private function urunToplami(Cart $sepet): string
    {
        $toplam = '0.00';

        foreach ($sepet->items as $satir) {
            if (! $satir->kullanilabilirMi()) {
                continue;
            }

            $fiyat = $satir->variant === null || ! is_numeric($satir->variant->price)
                ? '0'
                : (string) $satir->variant->price;

            /** @var numeric-string $toplam */
            $toplam = bcadd($toplam, bcmul($fiyat, (string) $satir->quantity, 2), 2);
        }

        return $toplam;
    }
}
