<?php

namespace App\Http\Storefront;

use App\Domain\Payment\PaymentProviderFactory;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ödeme dönüşü — müşterinin bankadan geri geldiği ekran. (1E.5)
 *
 * ★ BU UÇ HİÇBİR ŞEY YAZMIYOR. Tek işi ekran çevirmek.
 *
 * ⚠️ 1E-K1'in uygulaması. Tarayıcı dönüşü ödeme kanıtı DEĞİLDİR:
 *
 *   · müşteri o ekrana hiç ulaşmayabilir (sekmeyi kapatır, ağı kopar)
 *   · adres çubuğuna `?status=success` yazan herkes üretebilir
 *
 * iyzico kendi belgesinde bunu açıkça söylüyor: geri dönüş yönlendirmesi
 * ödemenin tamamlandığının güvenilir göstergesi değildir, callback
 * KULLANICIYI BİLGİLENDİRMEK içindir. Gerçek webhook'tan geliyor (1E.4).
 *
 * ⚠️ Sağlayıcıya da SORMUYOR. Sorsaydı ödemeyi öğrenmenin ikinci bir yolu
 * olurdu ve "hangisi doğru" sorusu doğardı; iki kaynağın çeliştiği an
 * kimse fark etmezdi.
 *
 * ⚠️ `magaza-acik` kapısının DIŞINDA: marka mağazayı kapatmış olsa bile
 * bankadan dönen müşteri ne olduğunu görebilmeli.
 */
class PaymentReturnController extends Controller
{
    public function __construct(private readonly PaymentProviderFactory $saglayicilar) {}

    /**
     * ⚠️ GET ve POST birlikte: sağlayıcılar dönüşü ikisinden biriyle
     * yapıyor (iyzico POST eder). Tek yöntem tanımlansaydı gerçek
     * sağlayıcı takıldığı gün müşteri 405 ekranıyla karşılaşırdı.
     */
    public function show(Request $istek): JsonResponse|RedirectResponse
    {
        $saglayici = $this->saglayicilar->coz();

        /*
        | ★ REFERANSI SAĞLAYICI ÇIKARIYOR — uç bilmiyor. (1E.7.3)
        |
        | ⚠️ Burada `?ref=` sabit yazılıydı ve iyzico'nun üç callback
        | denemesi de 404 aldı: iyzico `token`'ı POST GÖVDESİNDE yolluyor.
        | Müşteri ödemeyi bitirdikten sonra "sayfa bulunamadı" gördü.
        |
        | Sahte sağlayıcı bunu gizlemişti — yönlendirme adresini kendisi
        | üretiyordu, yani test kendi koyduğu değeri geri okuyordu.
        */
        $referans = $saglayici->donusReferansi($istek->all());

        $deneme = $referans === null
            ? null
            : Payment::where('provider', $saglayici->ad())
                ->where('provider_ref', $referans)
                ->first();

        abort_if($deneme === null, 404);

        $siparis = $deneme->order;

        abort_if($siparis === null, 404);

        /*
        | ⚠️ SİPARİŞTEN OKUNUYOR, istekten değil.
        |
        | İstekteki `status` alanına bakılsaydı müşteri adres çubuğunda
        | `?status=success` yazarak kendine "ödendi" ekranı gösterebilirdi.
        | Sipariş hiç ödenmemiş olurdu ama o beklemeye başlardı.
        */
        $durum = match ($siparis->payment_status) {
            PaymentStatus::Paid => 'success',
            PaymentStatus::Failed, PaymentStatus::Cancelled => 'failed',
            default => 'processing',
        };

        /*
        | ★ TARAYICIYA HTML, SAĞLAYICIYA/API'YE JSON. (4B)
        |
        | ⚠️ Bu uç müşterinin bankadan döndüğü EKRAN. Ham JSON göstermek,
        | ödemesini yeni yapmış birine süslü parantezli bir metin göstermek
        | demekti — siparişinin ne olduğunu anlayamazdı.
        |
        | ⚠️ Ayrım `expectsJson()` ile ve bu ancak 4A'dan sonra güvenilir:
        | `ForceJson` global olduğu sürece her istek "JSON istiyorum" derdi.
        */
        /*
        | ★ TARAYICI: POST → 303 → GET'lenebilir SONUÇ SAYFASI. (4.5R)
        |
        | ⚠️ GERÇEK KUSUR BURADAYDI ve ölçüldü. Sağlayıcı bu uca
        | ÇERÇEVENİN İÇİNDE ve POST ile geliyor; referans GÖVDEDE.
        | Sayfa doğrudan burada render edilince çerçeveden çıkış betiği
        | `window.top.location.href = window.location.href` yazıyordu —
        | yani ÜST PENCERE aynı adrese **GET** ile gidiyor ve gövdedeki
        | referans kayboluyordu:
        |
        |     sağlayıcı POST (token gövdede) → 200  ✅
        |     betiğin gittiği GET (token yok)  → 404 ❌
        |
        | Müşterinin ödemesi başarılı olmasına rağmen gördüğü son ekran
        | 404'tü. Bildirilen "açılamayan sayfa" buydu.
        |
        | ⚠️ SAHTE SAĞLAYICI BUNU GİZLEMİŞTİ — İKİNCİ KEZ. 1E.7.3'te
        | referansı adres çubuğuna koyduğu için testler `?ref=` ile
        | koşuyordu ve betik çalışıyordu. Gerçek sağlayıcının şekli
        | (POST + gövde) hiç sınanmamıştı.
        |
        | ⚠️ Adres İMZALI: sonuç sayfası artık GET'lenebilir olduğu için
        | uuid'i bilen herkes başkasının sipariş durumunu okuyabilirdi.
        | İmzayı biz üretiyoruz, tahmin edilemiyor ve süresi dolunca
        | müşteri siparişini "Siparişlerim"den görüyor.
        */
        if (! $istek->expectsJson()) {
            return redirect()->signedRoute(
                'vitrin.odeme.sonuc',
                ['siparis' => $siparis->uuid],
                now()->addHour(),
            )->setStatusCode(303);
        }

        return response()->json([
            'order_number' => $siparis->order_number,
            'payment_status' => $siparis->payment_status->value,

            /*
            | ★ `pending` = "bildirim HENÜZ GELMEDİ", "başarısız" değil.
            |
            | ⚠️ Bu ayrım kritik. iyzico ilk bildirimi 10-15 saniye sonra
            | atıyor; müşteri o ekrana 3 saniyede varabilir. Ara durum
            | "başarısız" gösterilseydi müşteri paniğe kapılır, ikinci kez
            | ödemeye çalışır ya da bankasını arardı — oysa ödemesi yolda.
            */
            'state' => $durum,
        ]);
    }

    /**
     * Ödeme sonucu — müşterinin gördüğü ekran. (4.5R)
     *
     * ⚠️ Bu uç GET ve İMZALI. `donus` ucundan 303 ile buraya geliniyor;
     * böylece çerçeveden çıkış betiği ÜST PENCEREYİ bu adrese
     * götürebiliyor — gövdede taşınan bir şey kalmadı.
     *
     * ⚠️ Durum yine SİPARİŞTEN okunuyor, istekten değil: imzalı adres
     * "bu siparişi görebilirsin" der, "ödendi" demez.
     */
    public function sonuc(Order $siparis): View
    {
        $durum = match ($siparis->payment_status) {
            PaymentStatus::Paid => 'success',
            PaymentStatus::Failed, PaymentStatus::Cancelled => 'failed',
            default => 'processing',
        };

        return view('storefront.sade.odeme-donus', [
            'siparis' => $siparis,
            'durum' => $durum,
        ]);
    }
}
