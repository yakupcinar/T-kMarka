<?php

namespace App\Domain\Privacy;

use RuntimeException;

/**
 * Doğrulama bağlantısı geçersiz: yok, süresi dolmuş ya da kullanılmış.
 *
 * ⚠️ Üç durum TEK mesajla dönüyor. Ayrılsaydı "bu jeton vardı ama süresi
 * doldu" bilgisi, jeton tahmin eden birine geri bildirim olurdu.
 */
class InvalidDataRequestException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Bağlantı geçersiz veya süresi dolmuş.');
    }
}
