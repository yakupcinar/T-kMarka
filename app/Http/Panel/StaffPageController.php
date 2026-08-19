<?php

namespace App\Http\Panel;

use App\Domain\Identity\RoleInUseException;
use App\Domain\Identity\RoleService;
use App\Domain\Identity\StaffService;
use App\Domain\Identity\SystemRoleException;
use App\Domain\Quota\QuotaExceededException;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\CreateStaffRequest;
use App\Http\Panel\Requests\RoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personel ve roller. (4.5C)
 *
 * ★ Faz 4'ün boşluklarından biriydi: marka panelden personel EKLEYEMİYORDU.
 * Uçları 1A'da vardı, ekranı yoktu.
 *
 * ⚠️ `izin:staff.manage` arkasında — ve o izin SİSTEMDEKİ EN TEHLİKELİSİ:
 * yetki dağıtma yetkisi. Bu yüzden roller ekranı da aynı kapının ardında.
 */
class StaffPageController extends Controller
{
    public function __construct(
        private readonly StaffService $personel,
        private readonly RoleService $roller,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Personel', [
            'personel' => $this->personel->listele()->map(fn (User $k) => [
                /*
                | ⚠️ `uuid`, `id` DEĞİL: `User::getRouteKeyName()` zaten
                | uuid döndürüyor, yani rota `id` ile 404 verir. Ayrıca
                | sıralı iç kimliği arayüze vermek gereksiz sızıntıdır.
                */
                'uuid' => $k->uuid,
                'name' => $k->name,
                'email' => $k->email,

                /*
                | ⚠️ SAHİP BAYRAĞI gösteriliyor: sahip çıkarılamıyor
                | (1A.3) ve arayüz bunu sebebiyle birlikte göstermeli,
                | yoksa "neden silemiyorum" sorusu doğar.
                */
                'is_owner' => (bool) $k->is_owner,
                'roles' => $k->roles->pluck('name')->values()->all(),
            ])->values()->all(),

            'roller' => $this->roller->listele()->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,

                /*
                | ⚠️ SİSTEM ROLÜ işaretli: silinemiyor ve adı
                | değiştirilemiyor (1A.6). Kullanıcı bunu denemeden önce
                | görmeli.
                */
                'is_system' => (bool) $r->is_system,
                /*
                | ⚠️ `permissions` bir ÖZELLİK DEĞİL METOT: `role_permissions`
                | için ayrı model yok, sorgu doğrudan tablodan geliyor (1A.6).
                | Özellik gibi okunursa Laravel onu ilişki sanıyor ve
                | "must return a relationship instance" ile patlıyor.
                */
                'permissions' => $r->permissions()->values()->all(),
                'staff_count' => $r->users()->count(),
            ])->values()->all(),

            'izinler' => $this->roller->izinSecenekleri(),
        ]);
    }

    public function personelEkle(CreateStaffRequest $istek): RedirectResponse
    {
        /** @var list<string> $roller */
        $roller = $istek->validated('roles');

        try {
            $this->personel->olustur($istek->safe()->except('roles'), $roller);
        } catch (QuotaExceededException $hata) {
            /*
            | ⚠️ Kota aşımı 500 DEĞİL (3F): markanın planı yetmiyor, bu
            | sıradan bir sonuç ve sebebi ekranda yazmalı — yoksa marka
            | neden ekleyemediğini bilemez.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Personel eklendi.');
    }

    public function personelCikar(Request $istek, User $kullanici): RedirectResponse
    {
        $isteyen = $istek->user('staff-web');

        abort_unless($isteyen instanceof User, 403);

        /*
        | ⚠️ "Kendini çıkaramaz" ve "sahip çıkarılamaz" kuralları
        | SERVİSTE (1A.3). Burada tekrarlanmıyor: iki yerde tutulsaydı
        | biri güncellenmeden kalır ve panelden yapılabilen bir şey
        | API'den yapılamaz (ya da tersi) olurdu.
        */
        $this->personel->cikar($kullanici, $isteyen);

        return back()->with('mesaj', 'Personel çıkarıldı.');
    }

    public function rolEkle(RoleRequest $istek): RedirectResponse
    {
        /** @var list<string> $izinler */
        $izinler = $istek->validated('permissions');

        $this->roller->olustur((string) $istek->validated('name'), $izinler);

        return back()->with('mesaj', 'Rol oluşturuldu.');
    }

    public function rolGuncelle(RoleRequest $istek, Role $rol): RedirectResponse
    {
        /** @var list<string> $izinler */
        $izinler = $istek->validated('permissions');

        try {
            $this->roller->guncelle($rol, (string) $istek->validated('name'), $izinler);
        } catch (SystemRoleException $hata) {
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Rol güncellendi.');
    }

    public function rolSil(Role $rol): RedirectResponse
    {
        try {
            $this->roller->sil($rol);
        } catch (RoleInUseException|SystemRoleException $hata) {
            /*
            | ⚠️ Kullanımdaki rol silinemiyor (1A.6). Silinseydi o roldeki
            | personel sessizce yetkisiz kalırdı.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Rol silindi.');
    }
}
