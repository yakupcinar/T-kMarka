<?php

namespace App\Domain\Payment;

use App\Domain\Order\CheckoutService;
use App\Enums\PaymentAttemptStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Webhook işleme — ödemenin GERÇEK sonucunun yazıldığı TEK yer. (1E.4)
 *
 * ★ `odemeBasarili()` ve `odemeBasarisiz()` yalnızca buradan çağrılıyor.
 * Callback (tarayıcı dönüşü) hiçbir şey yazmıyor (1E-K1).
 *
 * ⚠️ ÜÇ KAPI, sırayla — her biri ayrı bir sessiz arızayı kapatıyor:
 *
 *   1  İMZA        controller'da · sahte bildirim buraya HİÇ ulaşmıyor
 *   2  EŞLEŞME     bilinmeyen referans işlenmiyor
 *   3  TEKRAR      aynı bildirim ikinci kez stok düşürmüyor
 */
class PaymentWebhookService
{
    public function __construct(
        private readonly CheckoutService $siparisler,
        private readonly PaymentProviderFactory $saglayicilar,
    ) {}

    /**
     * Doğrulanmış bildirimi işler.
     *
     * ⚠️ Yalnızca imza doğrulandıktan SONRA çağrılıyor.
     *
     * @throws UnknownPaymentReferenceException
     * @throws PaymentAmountMismatchException
     */
    public function isle(PaymentOutcome $sonuc, string $saglayici): WebhookResult
    {
        $deneme = Payment::where('provider', $saglayici)
            ->where('provider_ref', $sonuc->saglayiciReferansi)
            ->first();

        /*
        | ⚠️ Bilinmeyen referans → GÜRÜLTÜLÜ.
        |
        | Sessizce 200 dönseydi sağlayıcı "işlendi" sanıp bir daha
        | denemezdi; gerçekte hiçbir şey olmamış olurdu. Hata dönersek
        | sağlayıcı 15 dakika sonra tekrar deniyor — bizim tarafta geçici
        | bir sorun varsa ikinci deneme kurtarıyor.
        */
        if ($deneme === null) {
            throw new UnknownPaymentReferenceException($sonuc->saglayiciReferansi);
        }

        /*
        | ★ TEKRAR TESLİM — 1E-K3'ün çalışma anındaki karşılığı.
        |
        | iyzico aynı bildirimi 15 dakika arayla 3 kez daha yolluyor.
        | Bu kapı olmasaydı stok her bildirimde bir kez daha düşerdi:
        | üç bildirim = üç kat stok düşümü, hiçbir hata olmadan.
        |
        | ⚠️ 200 dönüyoruz — hata DEĞİL. Sağlayıcının açısından bildirim
        | başarıyla teslim edildi; tekrar denemesine gerek yok.
        */
        if ($deneme->status !== PaymentAttemptStatus::Pending) {
            return WebhookResult::ZatenIslendi;
        }

        /*
        | ⚠️ TUTAR KARŞILAŞTIRMASI — imzaya rağmen (1E-K9).
        |
        | Tutar SAĞLAYICIYA SORULARAK alınıyor: iyzico'nun bildiriminde
        | tutar yok. Ağ çağrısı düşerse istisna yükseliyor, 2xx dönmüyoruz
        | ve sağlayıcı tekrar deniyor — doğru davranış.
        |
        | İmza yükü koruyor ama sağlayıcı tarafındaki bir karışıklık ya da
        | yanlış eşleşen referans, 549,70'lik siparişe 1,00'lik ödemeyi
        | bağlayabilir. Karşılaştırma metin değil SAYISAL: '549.7' ile
        | '549.70' aynı tutardır, düz `!==` bunları farklı görürdü.
        */
        $gercekTutar = $this->saglayicilar->coz()->tutariDogrula($sonuc);

        if (bccomp($this->sayisal($deneme->amount), $this->sayisal($gercekTutar), 2) !== 0) {
            throw new PaymentAmountMismatchException(
                $sonuc->saglayiciReferansi,
                (string) $deneme->amount,
                $gercekTutar,
            );
        }

        return DB::transaction(function () use ($deneme, $sonuc) {
            $siparis = $deneme->order;

            if ($siparis === null) {
                throw new UnknownPaymentReferenceException($sonuc->saglayiciReferansi);
            }

            $deneme->status = $sonuc->basarili
                ? PaymentAttemptStatus::Captured
                : PaymentAttemptStatus::Failed;

            $deneme->completed_at = now();

            /*
            | Denetim izi. `redirect_url` korunuyor — üzerine yazılsaydı
            | "müşteri nereye yönlendirilmişti" bilgisi kaybolurdu.
            */
            $deneme->raw_response = array_merge(
                $deneme->raw_response ?? [],
                ['webhook' => $sonuc->hamCevap],
            );

            $deneme->save();

            /*
            | ★ SİPARİŞİN DURUMUNU DEĞİŞTİREN TEK SATIRLAR.
            |
            | Stok hareketi de burada: `odemeBasarili` rezervasyonları
            | kesinleştiriyor (stok gerçekten düşüyor), `odemeBasarisiz`
            | serbest bırakıyor.
            */
            if ($sonuc->basarili) {
                $this->siparisler->odemeBasarili($siparis);

                return WebhookResult::Odendi;
            }

            $this->siparisler->odemeBasarisiz($siparis);

            return WebhookResult::Basarisiz;
        });
    }

    /**
     * ⚠️ `decimal:2` cast'i metin döndürüyor ama statik analiz onu sayısal
     * bilmiyor. Bozuk veri 0 kabul ediliyor — 0 ile karşılaştırma zaten
     * eşleşmeyeceği için ödeme işlenmiyor, sessizce geçmiyor.
     *
     * @return numeric-string
     */
    private function sayisal(mixed $deger): string
    {
        return is_numeric($deger) ? (string) $deger : '0';
    }
}
