<?php

namespace App\Http\Panel;

use App\Domain\Identity\StaffAuthService;
use App\Http\Panel\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Panel tarafı kimlik uçları — markanın PERSONELİ.
 *
 * ⚠️ `register` metodu YOK. Personel davetle gelir (1A.3).
 */
class AuthController
{
    public function __construct(private readonly StaffAuthService $servis) {}

    public function login(LoginRequest $istek): JsonResponse
    {
        $sonuc = $this->servis->girisYap(
            (string) $istek->input('email'),
            (string) $istek->input('password'),
        );

        return response()->json([
            'user' => $this->personelCiktisi($sonuc['user']),
            'token' => $sonuc['token'],
        ]);
    }

    public function logout(Request $istek): JsonResponse
    {
        /** @var User $personel */
        $personel = $istek->user();

        $this->servis->cikisYap($personel);

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    public function me(Request $istek): JsonResponse
    {
        /** @var User $personel */
        $personel = $istek->user();

        return response()->json(['user' => $this->personelCiktisi($personel)]);
    }

    /**
     * ⚠️ `roles` burada DÖNMÜYOR — izin sistemi 1A.3'te yazılacak.
     * Şimdi eklenseydi henüz kuralı olmayan bir alanı API sözleşmesine
     * sokmuş olurduk.
     *
     * @return array<string, mixed>
     */
    private function personelCiktisi(User $personel): array
    {
        return $personel->only(['uuid', 'name', 'email', 'is_owner']);
    }
}
