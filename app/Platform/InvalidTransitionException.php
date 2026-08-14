<?php

namespace App\Platform;

use DomainException;

/**
 * Geçersiz durum geçişi. (3C)
 *
 * ⚠️ Bu istisna olmasaydı kapatılmış bir marka kazara denemeye
 * döndürülebilir ve sonsuz ücretsiz kullanım açılırdı — hata vermeden.
 */
class InvalidTransitionException extends DomainException {}
