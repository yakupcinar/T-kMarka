<?php

namespace App\Domain\Payment;

/**
 * Para iadesi yapabilen sağlayıcı. (2B-K7)
 *
 * ★ AYRI ARAYÜZ — `QueryablePaymentProvider` ile aynı gerekçe: her
 * sağlayıcı her şeyi yapamıyor ve yapamadığını BEYAN etmeli.
 *
 * ⚠️ Uygulamayan sağlayıcıda iade kaydı `pending` kalıyor ve marka elle
 * kapatıyor. Sessizce "tamamlandı" denseydi para hiç gitmemişken sipariş
 * iade edilmiş görünürdü.
 */
interface RefundablePaymentProvider extends PaymentProvider
{
    /**
     * Parayı geri gönderir.
     *
     * ⚠️ `$idempotanslikAnahtari` sağlayıcıya GİDİYOR: aynı anahtarla
     * ikinci istek gelirse yeni iade yapılmaz. İki kez iade, iki kez
     * tahsilattan beter.
     *
     * @param  numeric-string  $tutar
     */
    public function iadeEt(string $referans, string $tutar, string $idempotanslikAnahtari): PaymentOutcome;
}
