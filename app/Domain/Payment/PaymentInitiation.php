<?php

namespace App\Domain\Payment;

/**
 * `baslat()`'ın cevabı: müşteri nereye gidecek ve bu deneme hangi
 * numarayla anılacak.
 *
 * ⚠️ Burada "başarılı mı" alanı YOK — bilerek. Bu noktada ödeme henüz
 * OLMADI; yalnızca başlatıldı. Alan olsaydı bir gün biri ona bakıp
 * siparişi ödenmiş sayardı.
 */
final readonly class PaymentInitiation
{
    /**
     * @param  string  $yonlendirmeAdresi  müşterinin gönderileceği 3D Secure sayfası
     * @param  string  $saglayiciReferansi  payments.provider_ref
     */
    public function __construct(
        public string $yonlendirmeAdresi,
        public string $saglayiciReferansi,
    ) {}
}
