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
    /**
     * @param  string  $izin  tek izin ya da `|` ile ayrılmış HERHANGİ BİRİ
     *
     * ★ ÇOKLU İZİN "HERHANGİ BİRİ" (4.6S). Görüntüleme sayfaları
     * `izin:product.view|product.write` ile korunuyor: görmek için okuma
     * YA DA yazma yetkisi yeter.
     *
     * ⚠️ Neden böyle, neden sayfaları doğrudan `.view`'a taşımadık:
     * yayındaki markalarda `product.write` verilmiş ama `product.view`
     * verilmemiş roller olabilir. Doğrudan taşımak o personeli bugün
     * kullandığı ekranlardan **sessizce** dışarı atardı — kullanıcı
     * "panelim bozuldu" derdi ve sebebi görünmezdi.
     *
     * ⚠️ "HEPSİ" değil "HERHANGİ BİRİ": yazma yetkisi olan biri sayfayı
     * görebilmeli. `AND` yazılsaydı yalnızca yazma izni olan rol yine
     * dışarıda kalırdı.
     */
    public function handle(Request $istek, Closure $next, string $izin): Response
    {
        $kullanici = $istek->user();

        /*
        | ⚠️ Tip kontrolü ikinci savunma hattı. Rota zaten `auth:staff`
        | arkasında olmalı; ama bir gün biri onu düşürürse burada duruyoruz.
        | Müşterinin `hasPermission` metodu bile yok.
        */
        $kabul = array_filter(explode('|', $izin));

        $yetkili = $kullanici instanceof User
            && array_filter($kabul, fn (string $tek) => $kullanici->hasPermission($tek)) !== [];

        if (! $yetkili) {
            abort(403, 'Bu işlem için yetkiniz yok.');
        }

        return $next($istek);
    }
}
