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
     * @param  string  $yonlendirmeAdresi  müşterinin gönderileceği ödeme sayfası
     * @param  string  $saglayiciReferansi  payments.provider_ref
     * @param  string|null  $gomuluAdres  aynı sayfanın IFRAME'e gömülebilen hâli
     */
    public function __construct(
        public string $yonlendirmeAdresi,
        public string $saglayiciReferansi,
        public ?string $gomuluAdres = null,
    ) {}

    /**
     * Ödeme formu vitrine GÖMÜLEBİLİYOR mu?
     *
     * ★ 4.5-K1: kart formu iframe içinde, iyzico'nun kendi kökeninde
     * gösteriliyor. Müşteri siteden ayrılmıyor ama kart verisi bize hiç
     * uğramıyor.
     *
     * ⚠️ Sağlayıcının verdiği HAZIR BETİK (`checkoutFormContent`) DEĞİL,
     * ADRES gömülüyor. Betik seçilseydi iyzico'nun JavaScript'i BİZİM
     * sayfamızın kökeninde çalışırdı; adres gömülünce kart alanları
     * tamamen onların kökeninde kalıyor — PCI kapsamını daraltma amacının
     * gereği de bu.
     */
    public function gomulebilirMi(): bool
    {
        return $this->gomuluAdres !== null && $this->gomuluAdres !== '';
    }
}
