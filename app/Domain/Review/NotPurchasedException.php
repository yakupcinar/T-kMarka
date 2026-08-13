<?php

namespace App\Domain\Review;

use DomainException;

/** Müşteri bu ürünü teslim almamış — yorum yazamaz. (2E-K1) */
class NotPurchasedException extends DomainException {}
