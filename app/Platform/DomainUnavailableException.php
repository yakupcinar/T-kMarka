<?php

namespace App\Platform;

use DomainException;

/** Alan adı kullanılamaz — dolu ya da ayrılmış. (3D) */
class DomainUnavailableException extends DomainException {}
