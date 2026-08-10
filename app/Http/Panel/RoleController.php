<?php

namespace App\Http\Panel;

use App\Domain\Identity\RoleService;
use App\Http\Controllers\Controller;
use App\Http\Panel\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * Rol yönetimi — `sahip` middleware'i arkasında (izin DEĞİL, bkz.
 * [App\Http\Middleware\RequireOwner]).
 *
 * ⚠️ İş kuralları burada DEĞİL, [App\Domain\Identity\RoleService]'te.
 * Bu sınıf yalnızca HTTP'ye çeviriyor: isteği al, servisi çağır, cevabı
 * biçimle. Kurallar burada dursaydı bir artisan komutu ya da kuyruk işi
 * onları atlayabilirdi.
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roller) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => $this->roller->listele()->map(fn (Role $rol) => [
                'id' => $rol->id,
                'name' => $rol->name,
                'is_system' => $rol->is_system,
                'permissions' => $rol->permissions(),

                // Sahip, silmeden önce kaç kişiyi taşıması gerektiğini görsün.
                'staff_count' => $rol->users_count,
            ]),

            'available_permissions' => $this->roller->izinSecenekleri(),
        ]);
    }

    public function store(RoleRequest $istek): JsonResponse
    {
        /** @var array{name: string, permissions: list<string>} $veri */
        $veri = $istek->validated();

        $rol = $this->roller->olustur($veri['name'], $veri['permissions']);

        return response()->json(['role' => $this->goster($rol)], 201);
    }

    public function update(RoleRequest $istek, Role $rol): JsonResponse
    {
        /** @var array{name: string, permissions: list<string>} $veri */
        $veri = $istek->validated();

        $rol = $this->roller->guncelle($rol, $veri['name'], $veri['permissions']);

        return response()->json(['role' => $this->goster($rol)]);
    }

    /**
     * Silme kuralları serviste; ihlal edilirse istisna fırlıyor ve
     * `bootstrap/app.php` onu 409'a çeviriyor.
     */
    public function destroy(Role $rol): JsonResponse
    {
        $this->roller->sil($rol);

        return response()->json(['message' => 'Rol silindi.']);
    }

    /** @return array<string, mixed> */
    private function goster(Role $rol): array
    {
        return [
            'id' => $rol->id,
            'name' => $rol->name,
            'is_system' => $rol->is_system,
            'permissions' => $rol->permissions(),
        ];
    }
}
