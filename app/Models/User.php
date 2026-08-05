<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Marka PERSONELİ — panele giren kişi.
 *
 * Müşteriler ayrı model (`Customer`), ayrı tablo, ayrı guard (1A.0).
 * Tablo adı `users` ama içerik Laravel'in varsayılanı değil; onun migration'ı
 * silinip yerine kendi şemamız yazıldı (1A.1).
 */
class User extends Authenticatable
{
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

    /** Token tabanlı kimlik (K-12) — `remember_token` kolonu da yok. */
    protected $rememberTokenName = null;
}
