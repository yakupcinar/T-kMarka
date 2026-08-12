<?php

namespace App\Http\Storefront;

use App\Domain\Payment\PaymentProviderFactory;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
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
    public function show(Request $istek): JsonResponse
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
            'state' => match ($siparis->payment_status) {
                PaymentStatus::Paid => 'success',
                PaymentStatus::Failed, PaymentStatus::Cancelled => 'failed',
                default => 'processing',
            },
        ]);
    }
}
