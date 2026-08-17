<?php

namespace App\Platform\Subscription;

use RuntimeException;

/** Yapılandırmada tanınmayan abonelik sağlayıcısı. (3E) */
class UnknownSubscriptionProviderException extends RuntimeException {}
