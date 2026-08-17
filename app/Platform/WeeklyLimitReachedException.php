<?php

namespace App\Platform;

use DomainException;

/**
 * Haftalık marka açma tavanı doldu. (3D / 3-K5)
 *
 * ⚠️ Bu istisna olmasaydı marka açılır, panel çalışır ama SİTE AÇILMAZDI:
 * Let's Encrypt kotası dolduğu için sertifika alınamaz. Sessiz bir tavan
 * yerine gürültülü bir ret.
 */
class WeeklyLimitReachedException extends DomainException {}
