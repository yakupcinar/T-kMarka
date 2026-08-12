<?php

namespace App\Domain\Payment;

/**
 * Sağlayıcıya GİDEN istek — değişmez.
 *
 * ⚠️ Sipariş nesnesi (`Order`) gönderilmiyor, yalnızca sağlayıcının
 * ihtiyacı olan alanlar gönderiliyor. Model verilseydi bir sağlayıcı
 * uyarlaması `$siparis->grand_total`'ı okumak yerine `$siparis->items`
 * üzerinden kendi toplamını hesaplamaya kalkabilirdi — para hesabının
 * `OrderTotals` dışında ikinci bir yeri olurdu (§0).
 */
final readonly class PaymentRequest
{
    /**
     * @param  numeric-string  $tutar  ⚠️ orders.grand_total'dan geliyor; istemciden ASLA
     * @param  string  $idempotanslikAnahtari  sipariş numarası (1E-K4)
     * @param  string  $donusAdresi  müşterinin geri geleceği ekran — KANIT DEĞİL
     */
    public function __construct(
        public string $siparisNumarasi,
        public string $tutar,
        public string $eposta,
        public string $idempotanslikAnahtari,
        public string $donusAdresi,
    ) {}
}
