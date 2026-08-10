<?php

namespace App\Domain\Identity;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rol yönetimi — kurma, düzenleme, silme.
 *
 * ⚠️ Kurallar BURADA, controller'da değil. Controller'da dururken şu risk
 * vardı: bir artisan komutu, bir kuyruk işi ya da bir tohumlayıcı rol
 * silerse "sistem rolü silinemez" ve "üzerinde personel olan rol silinemez"
 * kuralları hiç çalışmazdı — hata da vermezdi. 1A.5'te adres için
 * uyguladığımız ilkenin aynısı: kontrolü unutmak mümkün olmamalı.
 *
 * ⚠️ Uçları `sahip` middleware'i arkasında — izin DEĞİL. `role.manage`
 * diye bir izin olsaydı ona sahip kişi kendine `settings.write` içeren bir
 * rol kurup atardı. "Yetki dağıtan işlem, yetkiyle dağıtılmaz."
 */
class RoleService
{
    /**
     * Roller, personel sayılarıyla birlikte.
     *
     * @return Collection<int, Role>
     */
    public function listele(): Collection
    {
        return Role::withCount('users')->orderBy('name')->get();
    }

    /** @param  list<string>  $izinler */
    public function olustur(string $ad, array $izinler): Role
    {
        // `is_system` $fillable dışında — istekle "silinemez rol" üretilemesin.
        $rol = Role::create(['name' => $ad]);

        /*
        | ⚠️ `refresh()` şart: `is_system` değeri VERİTABANI VARSAYILANINDAN
        | geliyor (`default(false)`), gönderilmediği için bellekteki nesnede
        | hiç yok — cevapta `null` dönerdi.
        |
        | 1A.2'de `accepts_marketing` ile aynı tuzağa düşmüştük: "kolonun
        | varsayılanı var" ile "modelin değeri var" ayrı şeyler.
        */
        $rol->refresh();

        $rol->syncPermissions($izinler);

        return $rol;
    }

    /**
     * Ad ve izinler güncellenir — SİSTEM ROLLERİ DAHİL.
     *
     * ⚠️ Sistem rolleri dondurulmuyor, yalnızca silinemiyor. "Yönetici'den
     * finans iznini alayım" meşru bir istek; yasaklasaydık marka rolü
     * kopyalayıp aynısını kurar, sonuç değişmez ama iki karışık rol olurdu.
     *
     * @param  list<string>  $izinler
     */
    public function guncelle(Role $rol, string $ad, array $izinler): Role
    {
        $rol->update(['name' => $ad]);
        $rol->syncPermissions($izinler);

        return $rol;
    }

    /**
     * @throws SystemRoleException sistem rolü silinmeye çalışıldı
     * @throws RoleInUseException rol hâlâ personelde
     */
    public function sil(Role $rol): void
    {
        /*
        | ⚠️ Sistem rolü silinemez.
        |
        | Silinebilseydi marka bütün rollerini silip kimsenin hiçbir şey
        | yapamadığı bir panele düşebilirdi. (Sahip `is_owner` muafiyetiyle
        | kapıda kalır ama personelin tamamı dışarıda kalırdı.)
        */
        if ($rol->is_system) {
            throw new SystemRoleException;
        }

        /*
        | ⚠️ Üzerinde personel olan rol silinemez.
        |
        | Sessizce çözülseydi personel bir sabah yetkisiz uyanır ve kimse
        | sebebini bilmezdi. Sahip önce personeli taşımak zorunda — bilinçli
        | bir hamle gerekiyor.
        */
        $personelSayisi = $rol->users()->count();

        if ($personelSayisi > 0) {
            throw new RoleInUseException($personelSayisi);
        }

        // Öksüz izin satırı kalmasın: aynı id yeniden kullanılırsa yeni rol
        // eski izinlerle doğardı.
        $rol->syncPermissions([]);
        $rol->delete();
    }

    /**
     * Panelin izin listesini koda gömmesine gerek kalmasın diye seçenekler.
     *
     * @return list<array{value: string, label: string}>
     */
    public function izinSecenekleri(): array
    {
        return array_map(
            fn (Permission $izin) => ['value' => $izin->value, 'label' => $izin->etiket()],
            Permission::cases(),
        );
    }
}
