<?php

namespace App\Platform\Subscription;

use DomainException;

/**
 * Markanın zaten bir aboneliği var. (3E)
 *
 * ⚠️ İkinci abonelik açılabilseydi marka iki kez ücretlendirilir ve ilk
 * abonelik sağlayıcıda öksüz kalırdı — kimse iptal etmediği için her ay
 * çekmeye devam ederdi.
 */
class AlreadySubscribedException extends DomainException {}
