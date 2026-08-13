<?php

namespace App\Domain\Returns;

use App\Domain\Payment\PaymentProviderFactory;
use App\Domain\Payment\RefundablePaymentProvider;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * PARA İADESİ — paranın geri yolculuğu. (2B-K1)
 *
 * ★ `PaymentService`'in aynası: orada para geliyordu, burada gidiyor.
 * Aynı korumalar geçerli ve aynı sebeple.
 *
 * ⚠️ ÜRÜN ELE GEÇMEDEN PARA GİTMİYOR. Yalnızca `received` durumundaki
 * talebin iadesi açılabiliyor — bloğun en önemli koruması.
 */
class RefundService
{
    public function __construct(
        private readonly RefundTotals $hesap,
        private readonly PaymentProviderFactory $saglayicilar,
    ) {}

    /**
     * İade talebinin parasını geri gönderir.
     *
     * @throws ReturnNotRefundableException
     */
    public function iadeEt(OrderReturn $talep, ?string $sebep = null): Refund
    {
        /*
        | ★ ÜRÜN ELDE Mİ? Bloğun en önemli kontrolü (2B-K1).
        |
        | ⚠️ Olmasaydı: müşteri talep açar, marka onaylar, para gider —
        | ürün hiç gelmez. Ve bu bir hata olarak görünmez.
        */
        if ($talep->status !== ReturnStatus::Received) {
            throw new ReturnNotRefundableException($talep->status);
        }

        $siparis = $talep->order;

        if ($siparis === null || $siparis->payment_status === PaymentStatus::Refunded) {
            throw new ReturnNotRefundableException($talep->status);
        }

        $tutarlar = $this->hesap->hesapla($talep);
        $odeme = $this->tahsilEdilenOdeme($siparis);

        /*
        | ★ 2B-K7 — İDEMPOTANSLIK ANAHTARI = TALEBİN UUID'Sİ.
        |
        | ⚠️ Ödemedeki (1E-K4) desenin aynısı ama para GERİ giderken.
        | İki kez iade, iki kez tahsilattan beter: müşteriye fazladan para
        | gider ve geri istemek gerekir.
        |
        | ⚠️ Anahtar TALEBE bağlı, siparişe değil: bir siparişin birden
        | çok iade talebi olabiliyor (kısmi iadeler).
        */
        $anahtar = (string) $talep->uuid;

        $mevcut = Refund::where('order_id', $siparis->id)->where('idempotency_key', $anahtar)->first();

        /*
        | ⚠️ Yalnızca TAMAMLANMIŞ iade erken dönüyor.
        |
        | Gerçek sandbox koşusunda bulundu: sağlayıcı çağrısı düşünce kayıt
        | `pending` kalıyordu ve ikinci deneme sağlayıcıya HİÇ gitmeden o
        | kaydı geri veriyordu — yani hata düzeltilse bile iade bir daha
        | denenemiyordu. Para hiç gitmemişken sistem "iade var" diyordu.
        */
        if ($mevcut !== null && $mevcut->status === RefundStatus::Completed) {
            return $mevcut;
        }

        $iade = $mevcut ?? $this->kayitAc($siparis, $talep, $odeme, $tutarlar, $anahtar, $sebep);

        $saglayici = $this->saglayicilar->coz();

        /*
        | ⚠️ Sağlayıcı iadeyi desteklemiyorsa kayıt `pending` kalıyor ve
        | marka elle kapatıyor. Sessizce "tamamlandı" denseydi, para hiç
        | gitmemişken sipariş iade edilmiş görünürdü.
        */
        if (! $saglayici instanceof RefundablePaymentProvider) {
            return $iade;
        }

        $sonuc = $saglayici->iadeEt(
            (string) $odeme->provider_ref,
            $tutarlar['total'],
            $anahtar,
        );

        $iade->provider_ref = $sonuc->saglayiciReferansi;
        $iade->raw_response = $sonuc->hamCevap;
        $iade->status = $sonuc->basarili ? RefundStatus::Completed : RefundStatus::Failed;
        $iade->completed_at = $sonuc->basarili ? now() : null;
        $iade->save();

        if ($sonuc->basarili) {
            $this->siparisDurumunuGuncelle($siparis);
            $talep->status = ReturnStatus::Completed;
            $talep->save();
        }

        return $iade;
    }

    /**
     * ★ `orders.payment_status` TÜRETİLİYOR — elle yazılmıyor.
     *
     * ⚠️ 1D.4'teki `fulfillment_status` dersinin aynısı: elle yazılan
     * alan üçüncü kısmi iadeden sonra gerçekle uyuşmazdı.
     */
    private function siparisDurumunuGuncelle(Order $siparis): void
    {
        $iadeToplami = $this->hesap->iadeEdilenToplam($siparis->refresh());
        $siparisToplami = is_numeric($siparis->grand_total) ? (string) $siparis->grand_total : '0';

        $siparis->payment_status = bccomp($iadeToplami, $siparisToplami, 2) >= 0
            ? PaymentStatus::Refunded
            : PaymentStatus::PartiallyRefunded;

        $siparis->save();
    }

    /**
     * Paranın gerçekten çekildiği deneme.
     *
     * ⚠️ Bir siparişin birden çok ödeme denemesi olabiliyor (1E.1): kart
     * reddedildi, müşteri başka kartla denedi. İade, tahsilatın
     * gerçekleştiği denemeye bağlanmak zorunda.
     */
    private function tahsilEdilenOdeme(Order $siparis): Payment
    {
        /** @var Payment $odeme */
        $odeme = Payment::where('order_id', $siparis->id)
            ->where('status', PaymentAttemptStatus::Captured)
            ->latest('id')
            ->firstOrFail();

        return $odeme;
    }

    /**
     * @param  array{items: numeric-string, tax: numeric-string, shipping: numeric-string, total: numeric-string}  $tutarlar
     */
    private function kayitAc(Order $siparis, OrderReturn $talep, Payment $odeme, array $tutarlar, string $anahtar, ?string $sebep): Refund
    {
        try {
            return DB::transaction(function () use ($siparis, $talep, $odeme, $tutarlar, $anahtar, $sebep) {
                $iade = new Refund;
                $iade->order()->associate($siparis);
                $iade->orderReturn()->associate($talep);
                $iade->payment()->associate($odeme);
                $iade->items_amount = $tutarlar['items'];
                $iade->tax_amount = $tutarlar['tax'];
                $iade->shipping_amount = $tutarlar['shipping'];
                $iade->amount = $tutarlar['total'];
                $iade->idempotency_key = $anahtar;
                $iade->reason = $sebep;
                $iade->status = RefundStatus::Pending;
                $iade->save();

                return $iade;
            });
        } catch (QueryException $e) {
            /*
            | ⚠️ Yarışta ikinci istek UNIQUE'e çarpıyor; birincinin
            | satırını okuyup devam ediyoruz (1E.3 deseni).
            */
            $mevcut = Refund::where('order_id', $siparis->id)->where('idempotency_key', $anahtar)->first();

            if ($mevcut === null) {
                throw $e;
            }

            return $mevcut;
        }
    }
}
