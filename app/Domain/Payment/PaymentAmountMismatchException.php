<?php

namespace App\Domain\Payment;

use RuntimeException;

/**
 * Bildirilen tutar, denemenin tutarıyla uyuşmuyor.
 *
 * ⚠️ Sipariş ÖDENMİŞ SAYILMIYOR. Uyuşmazlık ya sağlayıcı tarafında bir
 * karışıklık ya da kasıtlı bir müdahale demek; ikisinde de doğru davranış
 * durmak. "Yakın tutar kabul edilsin" gibi bir esneklik, 549,70'lik
 * siparişin 1,00'e kapanmasının kapısını açardı.
 */
class PaymentAmountMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $referans,
        public readonly string $beklenen,
        public readonly string $gelen,
    ) {
        parent::__construct('Bildirilen tutar sipariş tutarıyla uyuşmuyor.');
    }
}
