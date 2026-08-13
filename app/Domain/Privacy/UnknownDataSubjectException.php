<?php

namespace App\Domain\Privacy;

use RuntimeException;

/**
 * Talep sahibi doğrulanamadı: ne kayıtlı hesap var, ne de e-posta ile
 * eşleşen sipariş.
 *
 * ⚠️ Cevap 404 — "böyle bir müşteri yok" demiyoruz. Deseydik, adres
 * deneyerek hangi e-postanın kayıtlı olduğu öğrenilebilirdi.
 */
class UnknownDataSubjectException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Talep oluşturulamadı.');
    }
}
