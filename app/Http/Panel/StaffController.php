<?php

namespace App\Http\Panel;

use App\Domain\Identity\StaffService;
use App\Http\Panel\Requests\CreateStaffRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personel yönetimi — hepsi `izin:staff.manage` arkasında.
 */
class StaffController
{
    public function __construct(private readonly StaffService $servis) {}

    public function index(): JsonResponse
    {
        $personeller = $this->servis->listele()
            ->map(fn (User $p) => $this->ciktisi($p))
            ->all();

        return response()->json(['staff' => $personeller]);
    }

    public function store(CreateStaffRequest $istek): JsonResponse
    {
        /** @var list<string> $roller */
        $roller = $istek->input('roles', []);

        $personel = $this->servis->olustur(
            $istek->safe()->only(['name', 'email', 'password']),
            $roller,
        );

        return response()->json(['user' => $this->ciktisi($personel)], 201);
    }

    /**
     * Rota bağlaması `uuid` üzerinden yapılıyor (User::getRouteKeyName).
     */
    public function destroy(Request $istek, User $user): JsonResponse
    {
        /** @var User $isteyen */
        $isteyen = $istek->user();

        $this->servis->cikar($user, $isteyen);

        return response()->json(['message' => 'Personel çıkarıldı.']);
    }

    /** @return array<string, mixed> */
    private function ciktisi(User $personel): array
    {
        return [
            ...$personel->only(['uuid', 'name', 'email', 'is_owner']),
            'roles' => $personel->roles->pluck('name')->all(),
        ];
    }
}
