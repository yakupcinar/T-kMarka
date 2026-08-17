<?php

namespace App\Platform\Models;

use Carbon\CarbonInterface;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Markanın alan adı — MERKEZ kayıt. (3H)
 *
 * ★ NEDEN KENDİ MODELİMİZ: 3H'de `verified_at` ve `verification_token`
 * kolonlarını ekledik ama paketin modeli onları BİLMİYOR.
 *
 * ⚠️ ÖLÇÜLDÜ ve testler yakaladı: cast olmadan `verified_at` bir METİN
 * olarak geliyor ve `$kayit->verified_at?->toIso8601String()` çağrısı
 * "Call to a member function on string" ile patlıyordu. Kolonu eklemek
 * TEK BAŞINA yetmiyor — 3B'de `tenants` tablosunda aynı ders çıkmıştı
 * (`getCustomColumns()`).
 *
 * @property CarbonInterface|null $verified_at
 * @property string|null $verification_token
 */
class Domain extends BaseDomain
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'verified_at' => 'datetime',
        ]);
    }

    /**
     * Bu alan adı doğrulandı mı?
     *
     * ⚠️ Ayrı metot: `verified_at !== null` kontrolü her çağrı yerinde
     * tekrarlansaydı biri unutur ve doğrulanmamış alan adı doğrulanmış
     * sayılırdı.
     */
    public function dogrulandiMi(): bool
    {
        return $this->verified_at !== null;
    }
}
