<?php

namespace App\Domain\Catalog;

use DomainException;

/**
 * Geçersiz koleksiyon kuralı. (2D)
 *
 * ⚠️ Bu istisna olmasaydı hata SESSİZ olurdu: bilinmeyen alan atlanır,
 * koleksiyon açılır ve YANLIŞ ürünleri gösterirdi.
 */
class CollectionRuleException extends DomainException {}
