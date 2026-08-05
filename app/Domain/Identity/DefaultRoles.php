<?php

namespace App\Domain\Identity;

use App\Enums\Permission;
use App\Models\Role;

/**
 * Marka açılırken kurulan varsayılan roller. (docs/domain-model.md §3)
 *
 * Bu roller `is_system = true` ile işaretlenir: marka kendi rolünü ekleyebilir
 * ama bunları silemez. Silinebilseydi marka bütün rollerini silip kimsenin
 * hiçbir şey yapamadığı bir panele düşebilirdi.
 *
 * ⚠️ SAHİP rolü listede YOK — çünkü sahiplik bir rol değil, `users.is_owner`
 * bayrağı. Rol olsaydı sahip kendi rolünü kaldırıp markasına kilitlenebilirdi.
 */
class DefaultRoles
{
    /**
     * Rol adı → izinleri.
     *
     * @return array<string, list<Permission>>
     */
    public static function tanimlar(): array
    {
        return [
            // Sahipten sonraki en yetkili rol. Finans ve ayarlar dahil,
            // ama personel yönetimi YOK — o yalnızca sahipte.
            'Yönetici' => [
                Permission::ProductView,
                Permission::ProductWrite,
                Permission::OrderView,
                Permission::OrderFulfill,
                Permission::OrderRefund,
                Permission::CustomerView,
                Permission::SettingsWrite,
                Permission::FinanceView,
            ],

            // Ürün ekleyip düzenler. Siparişe ve müşteri verisine erişmez.
            'Katalog' => [
                Permission::ProductView,
                Permission::ProductWrite,
            ],

            // Depo ve destek ekibi: siparişi görür, kargoya verir,
            // müşteriyi görür.
            // ⚠️ OrderRefund YOK — para iadesi ayrı bir sorumluluk.
            // Domain modelde "depocu siparişi görsün ama iade yapamasın"
            // örneği tam olarak buydu.
            'Sipariş & Destek' => [
                Permission::OrderView,
                Permission::OrderFulfill,
                Permission::CustomerView,
                Permission::ProductView,
            ],
        ];
    }

    /**
     * Rolleri ve izinlerini kurar. Zaten varsa yeniden oluşturmaz.
     *
     * Kiracı bağlamı ÇAĞIRAN tarafından açılmış olmalı — bu sınıf hangi
     * markada olduğunu bilmiyor (M-2.7).
     */
    public function kur(): void
    {
        foreach (self::tanimlar() as $ad => $izinler) {
            $rol = Role::firstOrCreate(['name' => $ad]);

            // `is_system` $fillable dışında (istekle set edilemesin diye),
            // bu yüzden doğrudan atanıyor.
            $rol->is_system = true;
            $rol->save();

            $rol->syncPermissions($izinler);
        }
    }
}
