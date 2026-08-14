<?php

namespace App\Platform\Models;

use App\Enums\TenantStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Bir marka. Merkez şemadaki `tenants` tablosunu temsil eder.
 *
 * app/Platform/ altında duruyor çünkü bu bir MERKEZ kaydı — hiçbir
 * markanın kendi şemasında değil, hepsinin ortak lobisinde (M-2.7).
 *
 * ⚠️ `@property` notu şart: statik analiz `casts()` metodundan enum'u
 * çıkaramıyor (Product ve Setting'de aynı not aynı sebeple var).
 *
 * @property TenantStatus|null $status
 * @property string|null $name
 * @property CarbonInterface|null $trial_ends_at
 * @property CarbonInterface|null $grace_ends_at
 * @property CarbonInterface|null $suspended_at
 * @property CarbonInterface|null $closed_at
 * @property string|null $subscription_ref
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /**
     * HasDomains  → $tenant->domains ilişkisi. Alan adından marka bulmak
     *               bu ilişki olmadan çalışmıyor.
     * HasDatabase → markanın şemasını açma/silme yeteneği.
     */
    use HasDatabase, HasDomains;

    /**
     * ⚠️ Paketin kendi `casts()`'ı var; genişletiyoruz, EZMİYORUZ.
     * Ezilseydi `data` kolonunun dizi dönüşümü kaybolur ve sanal
     * kolonların tamamı bozulurdu.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ]);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Deneme süresi dolmuş mu?
     *
     * ⚠️ `trial_ends_at` null ise deneme YOK demek, "süresi dolmuş" değil.
     * Ayrımı yapmayan bir kontrol, ödeyen markayı denemesi bitmiş sanardı.
     */
    public function denemesiBittiMi(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    /**
     * GERÇEK KOLONA yazılacak alanlar. (3B)
     *
     * ★ ÖLÇÜLMEDEN BİLİNEMEZDİ ve tam bir sessiz hata üretiyordu.
     *
     * Paket varsayılan olarak yalnızca `['id']` döndürüyor; geri kalan HER
     * alan `data` json'ının içine yazılıyor. Yani 3B'de kolonları açmak
     * TEK BAŞINA hiçbir işe yaramıyordu — ölçüldü:
     *
     * ```
     * Tenant::create(['name' => 'X', 'status' => 'trial'])
     *   kolon name=NULL  status=NULL          ← boş
     *   data  {"name":"X","status":"trial"}   ← veri burada
     *   $tenant->name = 'X'                   ← model DOĞRU okuyor (!)
     * ```
     *
     * ⚠️ Sinsi olan son satır: model doğru değeri veriyor, yani kod
     * çalışıyor gibi görünüyor. Kırılan tek şey SORGU — "denemesi bugün
     * biten markalar" ya da "askıya alınacaklar" sorgusu hep BOŞ dönerdi,
     * hata vermeden. Faz 3'ün zamanlanmış görevlerinin tamamı buna bakıyor.
     *
     * ⚠️ Listeye kolon eklemeyi unutmak aynı sessiz hatayı geri getirir:
     * yeni bir kolon açıp buraya yazmazsan değer `data`'ya gider ve kolon
     * boş kalır.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'status',
            'plan_id',
            'trial_ends_at',
            'grace_ends_at',
            'suspended_at',
            'closed_at',
            'subscription_ref',
        ];
    }
}
