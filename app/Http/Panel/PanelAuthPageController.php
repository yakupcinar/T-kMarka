<?php

namespace App\Http\Panel;

use App\Domain\Identity\StaffAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel giriş/çıkış — OTURUM tabanlı. (4C-K3)
 *
 * ★ 4-K3: API controller'ı değil `app/Domain/` servisi çağrılıyor.
 * Kimlik kuralları (hangi mesaj verilir, silinmiş personel girebilir mi)
 * tek yerde: [StaffAuthService].
 */
class PanelAuthPageController extends Controller
{
    public function __construct(private readonly StaffAuthService $kimlik) {}

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

        // Hatalı bilgide `ValidationException` fırlıyor ve Inertia onu
        // forma geri taşıyor — mesaj servisin kendisinden geliyor.
        $personel = $this->kimlik->dogrula((string) $veri['email'], (string) $veri['password']);

        Auth::guard('staff-web')->login($personel);

        /*
        | ★ OTURUM KİMLİĞİ YENİLENİYOR — oturum sabitleme (session fixation)
        | saldırısına karşı.
        |
        | ⚠️ Yenilenmeseydi, giriş öncesi kurbanın tarayıcısına kendi oturum
        | kimliğini yazdırabilen biri, kurban giriş yaptıktan sonra AYNI
        | oturumla panele girerdi.
        */
        $istek->session()->regenerate();

        return redirect()->intended(route('panel.pano'));
    }

    public function cikis(Request $istek): RedirectResponse
    {
        Auth::guard('staff-web')->logout();

        /*
        | ⚠️ ÜÇÜ DE GEREKLİ. Yalnızca `logout()` çağrılsaydı oturum verisi
        | (ve CSRF token'ı) tarayıcıda kalırdı.
        */
        $istek->session()->invalidate();
        $istek->session()->regenerateToken();

        return redirect()->route('panel.giris');
    }
}
