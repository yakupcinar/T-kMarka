<?php

namespace App\Domain\Identity;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Personel yönetimi — listeleme, davet, çıkarma.
 *
 * ⚠️ Bu servisin uçları `izin:staff.manage` arkasında. O izin varsayılan
 * rollerin HİÇBİRİNDE yok; yani pratikte yalnızca sahip erişebiliyor.
 * Gerekçe: personel davet etmek yetki YÜKSELTMEYE en yakın işlem — bir
 * yönetici kendine ikinci bir hesap açıp izinlerini genişletebilirdi.
 */
class StaffService
{
    /** @return Collection<int, User> */
    public function listele(): Collection
    {
        return User::with('roles')->orderBy('name')->get();
    }

    /**
     * Yeni personel oluşturur ve rollerini atar.
     *
     * Beklenen anahtarlar: name · email · password
     * (doğrulaması `CreateStaffRequest`'te — buraya yalnızca doğrulanmış veri gelir)
     *
     * @param  array<string, mixed>  $veri
     * @param  list<string>  $rolAdlari
     */
    public function olustur(array $veri, array $rolAdlari): User
    {
        $personel = User::create($veri);

        $this->rolleriAta($personel, $rolAdlari);

        return $personel->load('roles');
    }

    /**
     * Personeli çıkarır (soft delete).
     *
     * @throws ValidationException
     */
    public function cikar(User $silinecek, User $isteyen): void
    {
        /*
        | KİLİT 1 — sahip silinemez.
        | Silinebilseydi marka sahipsiz kalır ve `is_owner` bayrağı hiç
        | kimsede olmadığı için bir daha kimse tam yetkiye ulaşamazdı.
        */
        if ($silinecek->is_owner) {
            throw ValidationException::withMessages([
                'user' => ['Marka sahibi çıkarılamaz.'],
            ]);
        }

        /*
        | KİLİT 2 — kimse kendini çıkaramaz.
        | Yanlışlıkla kendini silen tek yetkili, panele bir daha giremezdi.
        */
        if ($silinecek->is($isteyen)) {
            throw ValidationException::withMessages([
                'user' => ['Kendinizi çıkaramazsınız.'],
            ]);
        }

        /*
        | Soft delete: rol bağları duruyor (users soft delete kullandığı için
        | cascade tetiklenmiyor — 1A.1). Personel geri alınırsa rolleri de
        | geri gelir. Token'ları ise iptal ediliyor: çıkarılan personel
        | elindeki token'la panele girmeye devam etmemeli.
        */
        $silinecek->tokens()->delete();
        $silinecek->delete();
    }

    /** @param  list<string>  $rolAdlari */
    private function rolleriAta(User $personel, array $rolAdlari): void
    {
        $rolIdleri = Role::whereIn('name', $rolAdlari)->pluck('id');

        $personel->roles()->sync($rolIdleri);
    }
}
