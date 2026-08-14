<?php

namespace App\Http\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Models\PlatformUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Platform yöneticisi girişi. (3C)
 *
 * ⚠️ KAYIT UCU YOK — marka panelindeki gibi (1A.2). Platform yöneticisi
 * ancak komutla açılıyor; uç olsaydı internetteki herkes kendine bütün
 * markalara erişen bir hesap yaratabilirdi.
 */
class AuthController extends Controller
{
    public function login(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $kullanici = PlatformUser::where('email', strtolower($veri['email']))->first();

        /*
        | ⚠️ "Parola yanlış" ile "hesap yok" AYNI cevabı alıyor (1A.2).
        | Ayrılsaydı deneyerek hangi e-postaların kayıtlı olduğu öğrenilirdi.
        |
        | ⚠️ Kapatılmış hesap da aynı cevabı alıyor: "hesabınız kapalı"
        | demek, o e-postanın bir zamanlar yönetici olduğunu doğrulardı.
        */
        if ($kullanici === null || ! $kullanici->is_active || ! Hash::check($veri['password'], $kullanici->password)) {
            throw ValidationException::withMessages([
                'email' => 'Giriş bilgileri doğrulanamadı.',
            ]);
        }

        $kullanici->last_login_at = now();
        $kullanici->save();

        return response()->json([
            'token' => $kullanici->createToken('platform')->plainTextToken,
            'user' => ['uuid' => $kullanici->uuid, 'name' => $kullanici->name],
        ]);
    }

    public function logout(Request $istek): JsonResponse
    {
        $kullanici = $istek->user();

        if ($kullanici instanceof PlatformUser) {
            $kullanici->currentAccessToken()->delete();
        }

        return response()->json(status: 204);
    }

    public function me(Request $istek): JsonResponse
    {
        $kullanici = $istek->user();

        return response()->json(['user' => $kullanici instanceof PlatformUser ? [
            'uuid' => $kullanici->uuid,
            'name' => $kullanici->name,
            'email' => $kullanici->email,
        ] : null]);
    }
}
