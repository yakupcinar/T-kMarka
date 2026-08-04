<?php

namespace App\Platform\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Bir marka. Merkez şemadaki `tenants` tablosunu temsil eder.
 *
 * app/Platform/ altında duruyor çünkü bu bir MERKEZ kaydı — hiçbir
 * markanın kendi şemasında değil, hepsinin ortak lobisinde (M-2.7).
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /**
     * HasDomains  → $tenant->domains ilişkisi. Alan adından marka bulmak
     *               bu ilişki olmadan çalışmıyor.
     * HasDatabase → markanın şemasını açma/silme yeteneği.
     */
    use HasDatabase, HasDomains;
}
