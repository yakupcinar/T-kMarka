<?php

namespace App\Domain\Notification;

use App\Mail\AbandonedOrderMail;
use App\Mail\OrderPaidMail;
use App\Mail\PaymentFailedMail;
use App\Mail\ShipmentMail;
use App\Models\Fulfillment;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Müşteriye giden postaların tek kapısı. (2H)
 *
 * ★ `EventRecorder` ile aynı desen ve aynı sebeple: iş kuralı burada
 * yaşamıyor, yalnızca "şu oldu, haber ver" deniyor.
 *
 * ⚠️ Bu sınıf kiracıdan HABERSİZ (M-2.7). Hangi markada olduğunu sormuyor;
 * kimliği kuyruk altyapısı taşıyor.
 */
class Notifier
{
    /**
     * ⚠️ Sipariş onayı ÖDEME BAŞARILI OLUNCA gider, sipariş oluşunca DEĞİL.
     * Sipariş `pending` doğuyor ve ödemesi hiç tamamlanmayabiliyor (1D).
     */
    public function siparisOnayi(Order $siparis): void
    {
        $this->gonder($siparis->email, new OrderPaidMail($siparis));
    }

    public function odemeBasarisiz(Order $siparis): void
    {
        $this->gonder($siparis->email, new PaymentFailedMail($siparis));
    }

    /**
     * Ödemesi yarım kalmış sipariş hatırlatması. (2F)
     *
     * ⚠️ `odemeBasarisiz` ile AYRI: orada müşteri denedi ve reddedildi,
     * burada hiç denemedi. Aynı mail kullanılsaydı vazgeçen müşteri
     * kartında sorun olduğunu sanırdı.
     */
    public function odemeHatirlatmasi(Order $siparis): void
    {
        $this->gonder($siparis->email, new AbandonedOrderMail($siparis));
    }

    public function kargoBildirimi(Fulfillment $paket): void
    {
        $this->gonder($paket->order?->email, new ShipmentMail($paket));
    }

    /**
     * ★ 2H-K2 — POSTA DÜŞERSE İŞ BOZULMAZ.
     *
     * ⚠️ 1F-K3'ün tekrarı. Mailin gitmemesi kötü; siparişin oluşamaması
     * felaket. Kuyruk sürücüsü erişilemezse istisna yükselip ödemeyi
     * düşürmesin diye yutuluyor.
     *
     * Yutulan tek şey KUYRUĞA ATAMAMA. İşin kendisi düşerse kuyruk zaten
     * tekrar deniyor.
     *
     * ⚠️ `afterCommit` YOK — bilerek. Bu çağrılar transaction DIŞINDA
     * yapılıyor (ödeme sonucu ve sevkiyat, kendi transaction'ları
     * kapandıktan sonra). 1F-K5'teki durum farklıydı: orada sipariş
     * oluşturma transaction'ının İÇİNDEYDİK.
     */
    private function gonder(?string $alici, Mailable $posta): void
    {
        /*
        | ⚠️ Alıcı yoksa sessizce çıkılıyor. `orders.email` her zaman dolu
        | (1D: misafir siparişinin tek iletişim kanalı) ama silinmiş
        | siparişin paketi gibi uç durumlarda ilişki boş dönebiliyor.
        */
        if ($alici === null || $alici === '') {
            return;
        }

        try {
            Mail::to($alici)->queue($posta);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
