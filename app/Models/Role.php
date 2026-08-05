<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Marka personelinin rolü. (docs/domain-model.md §3)
 *
 * Sabit enum yerine tablo seçilmişti: "depocu siparişi görsün ama iade
 * yapamasın" isteği enum'da kod değişikliği, tabloda bir satır.
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * ⚠️ `is_system` bilerek listede YOK.
     *
     * Kurulumda gelen dört rol (Sahip · Yönetici · Katalog · Sipariş & Destek)
     * silinemesin diye işaretli. Dışarıdan yazılabilseydi marka kendi rolünü
     * "sistem rolü" ilan edip silinemez hale getirebilir ya da tersine sistem
     * rolünün korumasını kaldırabilirdi.
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Bu role sahip personel — ÇOKTAN ÇOĞA, `role_user` üzerinden.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Rolün izinleri — metin listesi.
     *
     * `role_permissions` için ayrı bir Eloquent modeli YOK: tablo bir varlık
     * değil, role bağlı bir değer listesi (id'si, zaman damgası, kendi başına
     * anlamı yok). Model açmak gereksiz katman olurdu.
     *
     * İzin adları kodda tanımlı sabit listeden gelir; panelden yeni izin TÜRÜ
     * üretilemez (domain-model §3 kapsam sınırı).
     *
     * ⚠️ Atama/senkronizasyon mantığı 1A.3'te yazılacak — burada yalnızca
     * okuma var.
     *
     * @return Collection<int, string>
     */
    public function permissions(): Collection
    {
        return DB::table('role_permissions')
            ->where('role_id', $this->id)
            ->orderBy('permission')
            ->pluck('permission');
    }

    /**
     * Rolün izinlerini verilen listeyle DEĞİŞTİRİR (ekleme değil, eşitleme).
     *
     * Sil-ve-ekle yapılıyor: `role_permissions` bir varlık tablosu değil,
     * role bağlı değer listesi. Fark hesaplamak yerine listeyi yenilemek
     * hem daha kısa hem de "panelde ne seçiliyse o geçerli" davranışını
     * doğrudan veriyor.
     *
     * @param  list<Permission|string>  $izinler
     */
    public function syncPermissions(array $izinler): void
    {
        DB::table('role_permissions')->where('role_id', $this->id)->delete();

        $satirlar = collect($izinler)
            ->map(fn (Permission|string $izin) => $izin instanceof Permission ? $izin->value : $izin)
            ->unique()
            ->map(fn (string $izin) => ['role_id' => $this->id, 'permission' => $izin])
            ->all();

        if ($satirlar !== []) {
            DB::table('role_permissions')->insert($satirlar);
        }
    }
}
