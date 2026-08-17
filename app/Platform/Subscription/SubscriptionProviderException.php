<?php

namespace App\Platform\Subscription;

use RuntimeException;

/**
 * Abonelik sağlayıcısı iş düzeyinde hata döndürdü. (3E)
 *
 * ⚠️ 1E'nin dersi: "çağrı başarısız" ile "işlem başarısız" AYRI ŞEYLER.
 * Bu istisna yalnızca ÇAĞRI düzeyindeki hatalar için; ödemenin reddi bir
 * sonuçtur, hata değil ve `SubscriptionOutcome` ile taşınır.
 */
class SubscriptionProviderException extends RuntimeException {}
