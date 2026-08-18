<?php

namespace App\Http\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Models\PlatformUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kontrol düzlemi giriş/çıkış — OTURUM tabanlı. (4F)
 *
 * ⚠️ Marka panelinden AYRI GUARD (`platform-web`). Aynı guard'da
 * olsalardı bir markanın sahibi kontrol düzlemine girebilirdi (3C).
 */
class PlatformAuthPageController extends Controller
{
    public function form(): Response
    {
        return Inertia::render('Giris');
    }

    public function giris(Request $istek): RedirectResponse
    {
        $veri = $istek->validate([
            'email' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $kullanici = PlatformUser::where('email', strtolower((string) $veri['email']))->first();

        /*
        | ⚠️ ÜÇ DURUM TEK CEVAP: hesap yok · parola yanlış · hesap kapalı.
        | Ayrılsaydı deneyerek hangi e-postaların yönetici olduğu
        | öğrenilirdi; "hesabınız kapalı" demek de o adresin bir zamanlar
        | yönetici olduğunu doğrulardı (1A.2).
        |
        | ⚠️ Kural [AuthController]'da da aynı — token ucu ile sayfa ucu
        | aynı cevabı vermeli, yoksa birinden öğrenilemeyen bilgi
        | diğerinden öğrenilir.
        */
        if ($kullanici === null || ! $kullanici->is_active || ! Hash::check((string) $veri['password'], $kullanici->password)) {
            throw ValidationException::withMessages([
                'email' => 'Giriş bilgileri doğrulanamadı.',
            ]);
        }

        Auth::guard('platform-web')->login($kullanici);

        // ⚠️ Oturum sabitleme (session fixation) saldırısına karşı.
        $istek->session()->regenerate();

        /*
        | ⚠️ `route('yonetim.pano')` KULLANILMIYOR — ve bunu test yakaladı.
        |
        | Merkez rotalar BİRDEN ÇOK alan adına kayıtlı (`central_domains`:
        | `localhost`, `127.0.0.1`, ileride `tikmarka.com`). `route()` her
        | zaman LİSTEDEKİ İLKİNİ üretiyor; yani `localhost`'tan giriş yapan
        | yönetici `127.0.0.1`'e savruluyordu — oturum çerezi orada geçerli
        | olmadığı için de giriş ekranına geri düşerdi.
        |
        | Göreli yol isteğin kendi alan adında kalıyor.
        */
        return redirect()->intended('/yonetim');
    }

    public function cikis(Request $istek): RedirectResponse
    {
        Auth::guard('platform-web')->logout();

        $istek->session()->invalidate();
        $istek->session()->regenerateToken();

        // ⚠️ Göreli yol — gerekçesi yukarıda (çok alan adlı merkez).
        return redirect('/yonetim/giris');
    }
}
