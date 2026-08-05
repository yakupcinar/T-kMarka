<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * Marka PERSONELİ — panele giren kişi.
 *
 * Müşteriler ayrı model (`Customer`), ayrı tablo, ayrı guard (1A.0).
 * Tablo adı `users` ama içerik Laravel'in varsayılanı değil; onun migration'ı
 * silinip yerine kendi şemamız yazıldı (1A.1).
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * ⚠️ `is_owner` bilerek listede YOK.
     *
     * Olsaydı personel davet/güncelleme isteğine `is_owner=true` eklenerek
     * herkes kendini sahip yapabilirdi. Sahiplik yalnızca kurulumda,
     * `tenant:create` içinde atanır (1A.6).
     *
     * Aynı gerekçe `Address::$fillable`'da `customer_id` için de geçerliydi:
     * $fillable "neyi ekleyeyim" değil, "neyi ASLA dışarıdan almam" listesi.
     */
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * E-posta sınırda küçültülür — `CHECK (email = lower(email))` ile
     * birlikte çalışır (Customer'daki desenin aynısı, 1A.1 bulgusu).
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    /**
     * Personelin rolleri — ÇOKTAN ÇOĞA.
     *
     * Arada `role_user` pivot tablosu var: bir personelin birden çok rolü,
     * bir rolde birden çok personel olabilir.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Bu personelin sahip olduğu izinler — rollerinden toplanmış.
     *
     * İstek başına bir kez sorgulanır. Önbellek olmasaydı her yetki kontrolü
     * ayrı bir sorgu açardı; tek bir panel sayfasında onlarca kontrol olabilir.
     *
     * @return Collection<int, string>
     */
    public function izinler(): Collection
    {
        return $this->izinOnbellegi ??= DB::table('role_permissions')
            ->whereIn('role_id', $this->roles()->pluck('roles.id'))
            ->distinct()
            ->pluck('permission');
    }

    /**
     * Yetki kontrolü — tek kapı.
     *
     * ⚠️ SAHİP her izne otomatik sahiptir. Olmasaydı sahip kendi rolünden
     * `staff.manage` iznini kaldırdığında bir daha personel yönetimine
     * giremez, yani kendi markasına kilitlenirdi. `is_owner` bir rol değil,
     * bu yüzden emniyet kilidi (docs/domain-model.md §3).
     */
    public function hasPermission(Permission|string $izin): bool
    {
        if ($this->is_owner) {
            return true;
        }

        $deger = $izin instanceof Permission ? $izin->value : $izin;

        return $this->izinler()->contains($deger);
    }

    /** @var Collection<int, string>|null */
    private ?Collection $izinOnbellegi = null;

    /** Token tabanlı kimlik (K-12) — `remember_token` kolonu da yok. */
    protected $rememberTokenName = null;
}
