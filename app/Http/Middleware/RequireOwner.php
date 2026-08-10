<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yalnızca marka SAHİBİ geçebilir — TEK KAPI.
 *
 * Kullanımı:  Route::...->middleware('sahip')
 *
 * ⚠️ Neden izin değil bayrak.
 *
 * `role.manage` diye bir izin tanımlansaydı, o izne sahip kişi kendine
 * `settings.write` içeren yeni bir rol kurup atardı — kimse ona o yetkiyi
 * vermeden yetkisi artardı. Yetki yükseltme açığının ders kitabı örneği.
 *
 * Kural: **yetki dağıtan işlem, yetkiyle dağıtılmaz.** `staff.manage`'ı
 * hiçbir varsayılan role koymama kararımız (1A.3) da aynı düşüncenin
 * sonucuydu; burada bir adım ileri gidip izin sistemine hiç sokmuyoruz.
 */
class RequireOwner
{
    public function handle(Request $istek, Closure $next): Response
    {
        $kullanici = $istek->user();

        // Tip kontrolü ikinci savunma hattı: rota zaten `auth:staff`
        // arkasında olmalı, ama düşerse burada duruyoruz. Müşterinin
        // `is_owner` alanı bile yok.
        if (! $kullanici instanceof User || ! $kullanici->is_owner) {
            abort(403, 'Bu işlem yalnızca mağaza sahibine açık.');
        }

        return $next($istek);
    }
}
