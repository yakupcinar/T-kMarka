<?php

namespace App\Domain\Review;

use DomainException;

/**
 * Müşteri bu ürüne zaten yorum yazmış.
 *
 * ⚠️ Veritabanı kısıtı da var; bu istisna kısıtın YERİNE değil, ÖNÜNDE:
 * kısıt patlasaydı müşteri 500 görürdü.
 */
class DuplicateReviewException extends DomainException {}
