<?php

namespace App\Http\Storefront;

use App\Domain\Cart\CartService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sepet — vitrin ucu. `magaza-acik` arkasında, kimlik doğrulama İSTEĞE BAĞLI.
 *
 * ⚠️ Misafir sepeti var (M-1): zorunlu üyelik dönüşümü düşürür. Bu yüzden
 * uçlar hem giriş yapmış hem yapmamış kullanıcıya açık; kimin sepeti olduğu
 * `sepetiCoz()` içinde belirleniyor.
 *
 * ⚠️ Misafir kimliği `X-Cart-Token` BAŞLIĞINDA (1C-K1). Çerez değil: vitrin
 * Faz 4'te ve teknolojisi seçilmedi (M-3); çerez seçersek API'yi henüz var
 * olmayan bir istemciye bağlarız.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $sepetler) {}

    /** Sepeti getirir; yoksa misafir sepeti açar. */
    public function show(Request $istek): JsonResponse
    {
        $sepet = $this->sepetiCoz($istek, olusturmaIzni: true);

        return $this->cevap($sepet);
    }

    public function addItem(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'variant_uuid' => ['required', 'uuid', 'exists:product_variants,uuid'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAKS_ADET],
        ]);

        $sepet = $this->sepetiCoz($istek, olusturmaIzni: true);

        /** @var ProductVariant $varyant */
        $varyant = ProductVariant::where('uuid', $veri['variant_uuid'])->firstOrFail();

        $this->sepetler->ekle($sepet, $varyant, (int) ($veri['quantity'] ?? 1));

        return $this->cevap($sepet->refresh(), 201);
    }

    public function updateItem(Request $istek, string $variant): JsonResponse
    {
        $veri = $istek->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAKS_ADET],
        ]);

        $sepet = $this->sepetiCoz($istek);

        $this->sepetler->adetDegistir($this->satiriBul($sepet, $variant), (int) $veri['quantity']);

        return $this->cevap($sepet->refresh());
    }

    public function removeItem(Request $istek, string $variant): JsonResponse
    {
        $sepet = $this->sepetiCoz($istek);

        $this->sepetler->satirSil($this->satiriBul($sepet, $variant));

        return $this->cevap($sepet->refresh());
    }

    /**
     * Sepet cevabı — misafir sepetinde ÇEREZ de yazılıyor. (4A)
     *
     * ⚠️ Çerez sunucu render'lı vitrin için şart: tarayıcı düz gezinmede
     * `X-Cart-Token` başlığını gönderemiyor, sunucu da üst bardaki sepet
     * sayısını yazamıyordu.
     *
     * ⚠️ MÜŞTERİ sepetine çerez YAZILMIYOR: onun kimliği zaten oturumu.
     * Yazılsaydı çıkış yapan kullanıcının tarayıcısında, sahibi belli bir
     * sepetin token'ı kalırdı.
     */
    private function cevap(Cart $sepet, int $durum = 200): JsonResponse
    {
        $cevap = response()->json($this->goster($sepet), $durum);

        if ($sepet->customer_id === null && is_string($sepet->session_token)) {
            $cevap->withCookie(CartToken::cerez($sepet->session_token));
        }

        return $cevap;
    }

    /**
     * Sepeti çözer.
     *
     * ⚠️ Sıra önemli: GİRİŞ YAPMIŞSA müşteri sepeti kazanıyor. Misafir
     * token'ı da varsa birleştirme `AuthController` tarafında zaten
     * yapılmış oluyor; burada tekrar denemek çift birleştirmeye yol açardı.
     */
    private function sepetiCoz(Request $istek, bool $olusturmaIzni = false): Cart
    {
        $kullanici = $istek->user();

        if ($kullanici instanceof Customer) {
            return $this->sepetler->musteriSepeti($kullanici);
        }

        $sepet = $this->sepetler->misafirSepetiBul(CartToken::oku($istek));

        if ($sepet !== null) {
            return $sepet;
        }

        abort_unless($olusturmaIzni, 404, 'Sepet bulunamadı.');

        return $this->sepetler->misafirSepetiOlustur();
    }

    /**
     * ⚠️ Satır SEPETE DARALTILMIŞ sorgudan çözülüyor (1A.5 deseni):
     * başkasının sepetindeki satır sonuç kümesine hiç girmiyor → 404.
     */
    private function satiriBul(Cart $sepet, string $variantUuid): CartItem
    {
        /** @var CartItem $satir */
        $satir = $sepet->items()
            ->whereHas('variant', fn ($q) => $q->where('uuid', $variantUuid))
            ->firstOrFail();

        return $satir;
    }

    /** @return array<string, mixed> */
    private function goster(Cart $sepet): array
    {
        $sepet->load('items.variant.product.images');

        $satirlar = $sepet->items->map(function (CartItem $satir) {
            $varyant = $satir->variant;
            $urun = $varyant?->product;

            return [
                'variant_uuid' => $varyant?->uuid,
                'sku' => $varyant?->sku,
                'title' => $urun?->title,
                'product_slug' => $urun?->slug,
                'options' => $varyant?->options,
                'image' => $urun?->images->first()?->url(),

                // ⚠️ Fiyat CANLI — satırda saklanmıyor. Marka fiyatı
                // değiştirirse burada da değişiyor (domain-model §6).
                'unit_price' => $varyant?->price,
                'quantity' => $satir->quantity,
                'line_total' => $this->carp($varyant?->price, $satir->quantity),

                /*
                | ⚠️ Ölü satır SİLİNMİYOR, İŞARETLENİYOR (1C-K2).
                | Sessizce silinseydi kullanıcı ne kaybettiğini bilmezdi.
                */
                'available' => $satir->kullanilabilirMi(),
                'stock_ok' => $satir->stokYetiyorMu(),
            ];
        });

        return [
            // Misafir bunu saklayıp sonraki isteklerde başlıkta gönderiyor.
            'cart_token' => $sepet->session_token,

            'items' => $satirlar,
            'item_count' => $sepet->items->sum('quantity'),
            'subtotal' => $this->toplam($sepet),

            // Boş liste = sepet sipariş verilebilir durumda.
            'blockers' => $this->sepetler->engeller($sepet),
        ];
    }

    /**
     * ⚠️ Para hesabı `bcmath` ile — float YASAK (domain-model §0).
     * `0.1 + 0.2 !== 0.3` hatası para tutarında sessizce kuruş kaydırır.
     */
    /** @return numeric-string */
    private function carp(?string $birimFiyat, int $adet): string
    {
        // `decimal:2` cast'i metin döndürüyor ama statik analiz onun sayısal
        // olduğunu bilmiyor; bozuk veride 0 kabul ediliyor.
        $fiyat = is_numeric($birimFiyat) ? $birimFiyat : '0';

        return bcmul($fiyat, (string) $adet, 2);
    }

    private function toplam(Cart $sepet): string
    {
        $toplam = '0.00';

        foreach ($sepet->items as $satir) {
            if (! $satir->kullanilabilirMi()) {
                continue;   // ölü satır toplama girmiyor
            }

            $satirToplami = $this->carp($satir->variant?->price, $satir->quantity);

            /** @var numeric-string $toplam */
            $toplam = bcadd($toplam, $satirToplami, 2);
        }

        return $toplam;
    }
}
