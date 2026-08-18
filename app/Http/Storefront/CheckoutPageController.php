<?php

namespace App\Http\Storefront;

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CartNotOrderableException;
use App\Domain\Order\CheckoutService;
use App\Domain\Order\StaleContractException;
use App\Domain\Payment\PaymentService;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ödeme sayfası — siparişin oluştuğu yer. (4B)
 *
 * ★ 4-K3: `CheckoutController` (API) çağrılmıyor; aynı `app/Domain/`
 * servisleri doğrudan kullanılıyor.
 */
class CheckoutPageController extends Controller
{
    public function __construct(
        private readonly CheckoutService $siparisler,
        private readonly PaymentService $odemeler,
        private readonly LegalDocumentService $belgeler,
        private readonly CartResolver $coz,
    ) {}

    public function form(Request $istek): View|RedirectResponse
    {
        $sepet = $this->coz->bul($istek);

        if ($sepet === null || $sepet->items()->count() === 0) {
            return redirect()->route('vitrin.sepet');
        }

        $sepet->load('items.variant.product');

        /*
        | ★ SÖZLEŞMENİN SÜRÜMÜ FORMA GÖMÜLÜYOR (1A.4 · 1D-K2).
        |
        | ⚠️ Sunucu sipariş anında kendi bildiği güncel sürümü yazsaydı,
        | formu açtıktan sonra yeni sürüm yayınlanan müşteri GÖRMEDİĞİ
        | metne imza atmış olurdu. Burada gösterilen sürüm neyse, gönderilen
        | de o; sürüm değişmişse `StaleContractException` fırlıyor.
        */
        $sozlesme = $this->belgeler->guncelSurum(LegalDocumentType::DistanceSales);

        return view('storefront.sade.odeme', [
            'sepet' => $sepet,
            'sozlesme' => $sozlesme,
        ]);
    }

    public function gonder(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'email' => ['required', 'email', 'max:190'],
            'legal_version_id' => ['required', 'integer'],

            /*
            | ⚠️ Onay kutusu SUNUCUDA da zorunlu. Yalnızca `required`
            | HTML özniteliğine bırakılsaydı formu elle gönderen biri
            | sözleşmeyi onaylamadan sipariş verebilirdi — mesafeli satışta
            | onay yasal bir şart.
            */
            'sozlesme_onay' => ['accepted'],

            'shipping.full_name' => ['required', 'string', 'max:120'],
            'shipping.phone' => ['required', 'string', 'max:20'],
            'shipping.city' => ['required', 'string', 'max:60'],
            'shipping.district' => ['required', 'string', 'max:60'],
            'shipping.neighborhood' => ['nullable', 'string', 'max:100'],
            'shipping.line1' => ['required', 'string', 'max:255'],
            'shipping.line2' => ['nullable', 'string', 'max:255'],
            'shipping.postal_code' => ['nullable', 'string', 'max:10'],

            'billing_tax_number' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'billing_tax_office' => ['nullable', 'string', 'max:100'],
        ]);

        $sepet = $this->coz->bul($istek);

        if ($sepet === null) {
            return redirect()->route('vitrin.sepet')->with('hata', 'Sepetiniz bulunamadı.');
        }

        try {
            /** @var array{email: string, shipping: array<string, string|null>, legal_version_id: int} $veri */
            $siparis = $this->siparisler->baslat($sepet, $veri);
        } catch (CartNotOrderableException) {
            /*
            | ⚠️ Sepetteki sorun ÖDEME sayfasında değil SEPET sayfasında
            | gösteriliyor: müşterinin düzeltebileceği tek yer orası.
            */
            return redirect()->route('vitrin.sepet')
                ->with('hata', 'Sepetinizde düzeltilmesi gereken satırlar var.');
        } catch (StaleContractException) {
            /*
            | ⚠️ Sözleşme sürümü değişmiş. Sessizce yenisiyle devam etmek,
            | müşteriye görmediği metni imzalatmak olurdu.
            */
            return redirect()->route('vitrin.odeme')
                ->with('hata', 'Satış sözleşmesi güncellendi, lütfen yeniden okuyup onaylayın.');
        }

        return $this->odemeyeYonlendir($istek, $siparis);
    }

    /**
     * Sağlayıcının ödeme sayfasına yönlendirir.
     *
     * ⚠️ DÖNÜŞ ADRESİ SUNUCUDA ÜRETİLİYOR. İstek gövdesinden alınsaydı
     * saldırgan kendi sitesini yazardı: müşteri ödeme sonrası oraya düşer,
     * sahte bir "başarılı" ekranı görürdü (açık yönlendirme açığı).
     */
    private function odemeyeYonlendir(Request $istek, Order $siparis): RedirectResponse
    {
        $sonuc = $this->odemeler->baslat(
            $siparis,
            $istek->getSchemeAndHttpHost().PaymentController::DONUS_YOLU,
        );

        /*
        | ⚠️ `away()` — dış adrese yönlendirme. `to()` kullanılsaydı Laravel
        | adresi kendi alan adına göre yeniden yazardı ve müşteri hiçbir
        | yere gidemezdi.
        */
        return redirect()->away($sonuc->yonlendirmeAdresi);
    }
}
