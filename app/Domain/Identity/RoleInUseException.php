<?php

namespace App\Domain\Identity;

use RuntimeException;

/**
 * Üzerinde personel olan rol silinmeye çalışıldı.
 *
 * Sessizce çözülseydi personel bir sabah yetkisiz uyanır ve kimse sebebini
 * bilmezdi. Önce taşınmaları gerekiyor — bilinçli bir hamle.
 */
class RoleInUseException extends RuntimeException
{
    public function __construct(public readonly int $personelSayisi)
    {
        parent::__construct("Bu rol {$personelSayisi} personelde kullanılıyor.");
    }
}
