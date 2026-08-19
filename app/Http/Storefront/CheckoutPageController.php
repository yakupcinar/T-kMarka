<?php

namespace App\Http\Storefront;

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CartNotOrderableException;
use App\Domain\Order\CheckoutService;
use App\Domain\Order\StaleContractException;
use App\Domain\Payment\PaymentService;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Rules\DeliverableEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

        /*
        | ★ KAYITLI ADRESLER FORMA GİRİYOR (4.5I).
        |
        | ⚠️ Gerçek kullanımda bulundu: müşteri adres defterine adres
        | kaydediyor, ödemeye gelince aynı adresi BAŞTAN yazmak zorunda
        | kalıyordu. Adres defteri (1C) vardı ama ödeme akışı ondan
        | habersizdi — "uç var ≠ kullanılabilir"in bir örneği daha.
        |
        | ⚠️ Guard AÇIKÇA yazılıyor; gerekçesi [CartResolver]'da.
        */
        $musteri = $istek->user('customer-web');

        $adresler = $musteri instanceof Customer
            ? $musteri->addresses()->orderByDesc('id')->get()
            : new EloquentCollection;

        return view('storefront.sade.odeme', [
            'sepet' => $sepet,
            'sozlesme' => $sozlesme,
            'adresler' => $adresler,

            /*
            | ⚠️ E-posta ön dolu ama KİLİTLİ DEĞİL: müşteri siparişi
            | başka bir adresle takip etmek isteyebilir. Kilitlenseydi
            | hesap e-postasını değiştirmeden bunu yapamazdı.
            */
            'eposta' => old('email', $musteri instanceof Customer ? $musteri->email : ''),
        ]);
    }

    public function gonder(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            /*
            | ⚠️ `DeliverableEmail` ŞART (4.5G): Laravel'in `email` kuralı
            | `a@a` kabul ediyor, iyzico reddediyor. Kural olmadan sipariş
            | OLUŞUYOR, stok bağlanıyor ve ödeme sonradan patlıyordu —
            | stok 60 dakika kimseye satılamıyordu.
            */
            'email' => ['required', 'email', 'max:190', new DeliverableEmail],
            'legal_version_id' => ['required', 'integer'],

            /*
            | ⚠️ Onay kutusu SUNUCUDA da zorunlu. Yalnızca `required`
            | HTML özniteliğine bırakılsaydı formu elle gönderen biri
            | sözleşmeyi onaylamadan sipariş verebilirdi — mesafeli satışta
            | onay yasal bir şart.
            */
            'sozlesme_onay' => ['accepted'],

            /*
            | ★ KAYITLI ADRES SEÇİLDİYSE FORM ALANLARI ZORUNLU DEĞİL (4.5I).
            |
            | ⚠️ `required_without` kullanılıyor: adres seçilmediyse
            | alanlar yine zorunlu. Koşulsuz `nullable` yazılsaydı hiçbir
            | adres seçmeyen müşteri BOŞ adresle sipariş verebilirdi —
            | kargo çıkamayan bir sipariş, hata vermeden.
            */
            'adres_uuid' => ['nullable', 'uuid'],
            'adresi_kaydet' => ['nullable', 'boolean'],

            'shipping.full_name' => ['required_without:adres_uuid', 'string', 'max:120'],
            'shipping.phone' => ['required_without:adres_uuid', 'string', 'max:20'],
            'shipping.city' => ['required_without:adres_uuid', 'string', 'max:60'],
            'shipping.district' => ['required_without:adres_uuid', 'string', 'max:60'],
            'shipping.neighborhood' => ['nullable', 'string', 'max:100'],
            'shipping.line1' => ['required_without:adres_uuid', 'string', 'max:255'],
            'shipping.line2' => ['nullable', 'string', 'max:255'],
            'shipping.postal_code' => ['nullable', 'string', 'max:10'],

            'billing_tax_number' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'billing_tax_office' => ['nullable', 'string', 'max:100'],
        ]);

        $sepet = $this->coz->bul($istek);

        if ($sepet === null) {
            return redirect()->route('vitrin.sepet')->with('hata', 'Sepetiniz bulunamadı.');
        }

        $musteri = $istek->user('customer-web');

        /*
        | ★ SEÇİLEN ADRES SUNUCUDA ÇÖZÜLÜYOR — istekten gelen alanlar
        | kullanılmıyor.
        |
        | ⚠️ Ekranda gizlemek doğrulama değildir: `adres_uuid` gönderip
        | yanına başka bir `shipping` gövdesi eklemek serbest. Adres
        | seçildiyse alanlar DEFTERDEN okunuyor, istekten değil.
        |
        | ⚠️ Sahiplik kontrolü şart: başkasının adres uuid'si gönderilirse
        | o adrese sipariş çıkardı. `addresses()` ilişkisi üzerinden
        | arandığı için yabancı adres zaten bulunamıyor.
        */
        if (is_string($veri['adres_uuid'] ?? null) && $veri['adres_uuid'] !== '') {
            if (! $musteri instanceof Customer) {
                return back()->withInput()->with('hata', 'Kayıtlı adres kullanmak için giriş yapmalısınız.');
            }

            $adres = $musteri->addresses()->where('uuid', $veri['adres_uuid'])->first();

            if ($adres === null) {
                return back()->withInput()->with('hata', 'Seçilen adres bulunamadı.');
            }

            $veri['shipping'] = [
                'full_name' => $adres->full_name,
                'phone' => $adres->phone,
                'city' => $adres->city,
                'district' => $adres->district,
                'neighborhood' => $adres->neighborhood,
                'line1' => $adres->line1,
                'line2' => $adres->line2,
                'postal_code' => $adres->postal_code,
            ];
        } elseif ($musteri instanceof Customer && ($veri['adresi_kaydet'] ?? false)) {
            /*
            | ⚠️ Adres defterine kayıt YALNIZCA istenirse. Sessizce
            | kaydedilseydi müşteri bir kerelik gönderdiği adresi (hediye,
            | iş yeri) defterinde bulur ve kim eklediğini anlamazdı.
            |
            | ⚠️ `title` zorunlu (4.5G) — burada ekranda sorulmuyor, bu
            | yüzden ilden türetiliyor. Boş bırakılsaydı kayıt sessizce
            | düşerdi.
            */
            $musteri->addresses()->create($veri['shipping'] + [
                'title' => $veri['shipping']['city'] ?? 'Adres',
            ]);
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

        /*
        | ★ 4.5-K1: SİTEDE KALIYORUZ. Sipariş oluştu, ödeme sayfasına
        | yönlendirmek yerine gömülü ödeme adımına gidiliyor.
        |
        | ⚠️ Sipariş kimliği adrese `uuid` ile giriyor, `id` ile değil:
        | sıralı kimlik adres çubuğunda görünseydi başkasının siparişinin
        | ödeme ekranı tahmin edilebilirdi.
        */
        return redirect()->route('vitrin.ode', $siparis->uuid);
    }

    /**
     * Gömülü ödeme adımı — kart formu IFRAME içinde. (4.5-K1)
     *
     * ⚠️ Sipariş SEPETTEN DEĞİL adresten geliyor ve sahipliği burada
     * doğrulanıyor: adresi bilen herkes başkasının ödeme ekranını
     * açamamalı.
     */
    public function ode(Request $istek, Order $siparis): View|RedirectResponse
    {
        $this->siparisiDogrula($istek, $siparis);

        /*
        | ⚠️ ÖDENMİŞ siparişe ödeme ekranı açılmıyor: müşteri geri
        | düğmesine bastığında ikinci kez ödemeye çalışabilirdi.
        */
        if ($siparis->payment_status !== PaymentStatus::Pending) {
            return redirect()->route('vitrin.anasayfa');
        }

        $sonuc = $this->odemeler->baslat(
            $siparis,
            $istek->getSchemeAndHttpHost().PaymentController::DONUS_YOLU,
        );

        /*
        | ⚠️ Sağlayıcı gömmeyi desteklemiyorsa YÖNLENDİRİLİYOR. Tek yol
        | dayatılsaydı, iframe vermeyen bir sağlayıcıya geçildiği gün
        | ödeme tamamen kırılırdı.
        */
        if (! $sonuc->gomulebilirMi()) {
            return redirect()->away($sonuc->yonlendirmeAdresi);
        }

        return view('storefront.odeme-form', [
            'siparis' => $siparis,
            'gomuluAdres' => (string) $sonuc->gomuluAdres,
        ]);
    }

    /**
     * Siparişin bu ziyaretçiye ait olduğunu doğrular.
     *
     * ★ Kural 1E'de kuruldu ([PaymentController::siparisiCoz]) ve BURADA
     * TEKRARLANMIYOR, aynısı uygulanıyor: giriş yapmışsa yalnızca kendi
     * siparişi, misafirse yalnızca MİSAFİR siparişi.
     *
     * ⚠️ 404, 403 DEĞİL: "böyle bir sipariş var ama senin değil" bilgisi
     * de sızıntıdır (1A.5).
     *
     * ⚠️ Sipariş ile sepet arasında kolon bağı YOK, bu yüzden misafir
     * tarafında token eşleştirmesi yapılamıyor. Bugünkü koruma "misafir
     * siparişi" olmasıyla sınırlı — uuid v7 tahmin edilebilir değil ama
     * bu, adresi ELE GEÇİREN birine karşı koruma sağlamıyor. Aynı sınır
     * 1E'deki ödeme ucunda da var; daraltılacaksa iki yerde birden
     * daraltılmalı.
     */
    private function siparisiDogrula(Request $istek, Order $siparis): void
    {
        // ⚠️ SAYFA katmanı: kimlik oturumda. Bkz. [CartResolver].
        $kullanici = $istek->user('customer-web');

        if ($kullanici instanceof Customer) {
            abort_unless($siparis->customer_id === $kullanici->id, 404);

            return;
        }

        abort_unless($siparis->customer_id === null, 404);
    }
}
