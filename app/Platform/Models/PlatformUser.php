<?php

namespace App\Platform\Models;

use App\Domain\Identity\EmailNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform yöneticisi — ÜÇÜNCÜ kimlik alanı. (3C)
 *
 * ```
 * customer  markanın müşterisi   marka şeması   auth:customer
 * staff     markanın personeli   marka şeması   auth:staff
 * platform  BİZ                  MERKEZ şema    auth:platform   ← bu
 * ```
 *
 * ⚠️ Yetkisi BÜTÜN MARKALARA uzanıyor. Marka personeliyle aynı tabloda
 * tutulsaydı bir markanın sahibi kendini platform yöneticisi yapabilirdi.
 *
 * @property bool $is_active
 * @property CarbonInterface|null $last_login_at
 */
class PlatformUser extends Authenticatable
{
    /**
     * ⚠️ MERKEZ bağlantı. Olmasaydı marka bağlamındayken bu model MARKA
     * şemasında aranır ve "tablo yok" hatası verirdi (0.5'in tuzağı).
     */
    use CentralConnection;

    use HasApiTokens;
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * ⚠️ `is_active` LİSTEDE YOK — kapatılmış bir yönetici kendi kaydını
     * güncelleyerek yeniden açamasın. $fillable "neyi ASLA dışarıdan
     * almam" listesi (1A.1 deseni).
     */
    protected $hidden = [
        'password',
    ];

    /**
     * ⚠️ Kolon varsayılanı modele ULAŞMAZ (CLAUDE.md, beş kez ısırdı).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * E-posta HER ZAMAN küçük harfe indiriliyor.
     *
     * ⚠️ Türkçe büyük/küçük tuzağı burada da geçerli: `strtolower` yerine
     * yerelleştirilmiş bir dönüşüm kullanılsaydı `I` → `ı` olur ve aynı
     * adres iki farklı kayda düşerdi (1A.2'de ölçüldü).
     *
     * ⚠️ Veritabanında da CHECK kısıtı var — model atlanırsa (tohumlayıcı,
     * artisan) kısıt yakalıyor.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $deger): string => (string) EmailNormalizer::normallestir($deger),
        );
    }
}
