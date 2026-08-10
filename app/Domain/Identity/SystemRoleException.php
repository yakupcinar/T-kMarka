<?php

namespace App\Domain\Identity;

use RuntimeException;

/**
 * Sistem rolü silinmeye çalışıldı.
 *
 * Silinebilseydi marka bütün rollerini silip personelini panelin dışında
 * bırakabilirdi. Rolün izinleri düzenlenebiliyor — yasak olan yalnızca
 * silmek.
 */
class SystemRoleException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Sistem rolleri silinemez.');
    }
}
