<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * İzin kontrolü — TEK KAPI.
 *
 * Kullanımı:  Route::...->middleware('izin:staff.manage')
 *
 * Neden middleware, neden controller içinde `if` değil:
 * kontrol controller'a yazılsaydı her yeni uçta tekrar hatırlanması
 * gerekirdi ve bir gün biri unuturdu. Rotanın yanında duruyor, gözden
 * kaçması zor.
 *
 * Laravel'in `can:` middleware'i (Gate) KULLANILMIYOR: Gate varsayılan
 * guard'ın kullanıcısına bakıyor, bizde varsayılan `customer`. Panel
 * uçlarında kimlik `staff` guard'ından geliyor, dolayısıyla Gate yanlış
 * kullanıcıyı sorgulardı.
 */
class RequirePermission
{
    public function handle(Request $istek, Closure $next, string $izin): Response
    {
        $kullanici = $istek->user();

        /*
        | ⚠️ Tip kontrolü ikinci savunma hattı. Rota zaten `auth:staff`
        | arkasında olmalı; ama bir gün biri onu düşürürse burada duruyoruz.
        | Müşterinin `hasPermission` metodu bile yok.
        */
        if (! $kullanici instanceof User || ! $kullanici->hasPermission($izin)) {
            abort(403, 'Bu işlem için yetkiniz yok.');
        }

        return $next($istek);
    }
}
