<?php

namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Abonelik planı — MERKEZ kayıt. (3B)
 *
 * ⚠️ `app/Platform/` altında çünkü hiçbir markanın şemasında değil,
 * hepsinin ortak lobisinde (M-2.7).
 *
 * ⚠️ SINIRLAR ürün ve personel SAYISINDA; aylık sipariş sınırı YOK.
 * Araştırıldı: İkas ve Shopify'da da yok — sipariş kısıtlamak markanın
 * satışını, yani cirosunu kesmek demek.
 *
 * @property array<string, mixed> $features
 */
class Plan extends Model
{
    /**
     * ⚠️ MERKEZ bağlantı — paketin kendi trait'iyle.
     *
     * Olmasaydı marka bağlamındayken bu model MARKA şemasında aranır ve
     * "tablo yok" hatası verirdi (0.5'in `search_path` tuzağı).
     *
     * ⚠️ `protected $connection = 'central'` YAZILAMAZ: bağlantının gerçek
     * adı `.env`'den geliyor (bizde `pgsql`), sabit yazılsaydı ortam
     * değişince sessizce yanlış bağlantıya giderdi.
     */
    use CentralConnection;

    protected $fillable = [
        'code',
        'name',
        'price',
        'currency',
        'interval',
        'max_products',
        'max_staff',
        'features',
        'is_active',
        'position',
    ];

    /**
     * ⚠️ Kolon varsayılanı modele ULAŞMAZ (CLAUDE.md, beş kez ısırdı).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'TRY',
        'interval' => 'monthly',
        'is_active' => true,
        'position' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // ⚠️ `decimal:2` — float DEĞİL. Para float'ta tutulmaz.
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'max_products' => 'integer',
            'max_staff' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Bu sınır aşıldı mı?
     *
     * ⚠️ `null` SINIRSIZ demek — `0` ile karıştırılmamalı. Ayrı metot
     * olmasının sebebi bu: her çağrı yerinde `$limit === null` kontrolü
     * tekrarlansaydı biri unutur ve sınırsız plan sıfır kotalı olurdu.
     */
    public function asildiMi(?int $limit, int $mevcut): bool
    {
        return $limit !== null && $mevcut >= $limit;
    }
}
