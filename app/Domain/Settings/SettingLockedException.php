<?php

namespace App\Domain\Settings;

use RuntimeException;

/**
 * Mağaza yayındayken kilitli bir ayar değiştirilmeye çalışıldı.
 *
 * ⚠️ Bu bir YETKİ hatası değil (403) — personelin yetkisi var. Verinin
 * geçersizliği de değil (422) — veri geçerli. Yanlış olan ZAMAN: istek
 * sistemin şu anki durumuyla çelişiyor. HTTP karşılığı 409 Conflict.
 */
class SettingLockedException extends RuntimeException
{
    public function __construct(public readonly string $alan)
    {
        parent::__construct(
            "'{$alan}' mağaza yayındayken değiştirilemez — bu alan müşterinin ".
            'onayladığı sözleşmenin içine giriyor. Önce mağazayı kapatın.'
        );
    }
}
