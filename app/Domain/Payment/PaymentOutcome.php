<?php

namespace App\Domain\Payment;

/**
 * Webhook'un bizim dilimizdeki karşılığı — ödemenin GERÇEK sonucu.
 *
 * ⚠️ Bu nesne yalnızca imza doğrulandıktan sonra üretilir (1E-K1).
 */
final readonly class PaymentOutcome
{
    /**
     * @param  string  $saglayiciReferansi  idempotanslığın dayanağı — payments UNIQUE'i
     * @param  numeric-string  $tutar  sağlayıcının bildirdiği tutar
     * @param  array<string, mixed>  $hamCevap  denetim izi — MASKELENMİŞ
     */
    public function __construct(
        public string $siparisNumarasi,
        public string $saglayiciReferansi,
        public bool $basarili,
        public string $tutar,
        public array $hamCevap = [],
        public ?string $hataKodu = null,
    ) {}
}
