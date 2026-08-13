<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bu uygulamanın CEVABI HER ZAMAN JSON. (2E'de ölçülerek bulundu)
 *
 * ★ NEDEN VAR — ölçüldü, tahmin değil:
 *
 * ```
 * Accept: application/json  →  401  ✓
 * başlık YOK                →  500  ✗   "Route [login] not defined."
 * ```
 *
 * Laravel kimliksiz HTML isteğini `login` adlı rotaya yönlendirmeye
 * çalışıyor; arayüz olmadığı için (M-3) öyle bir rota yok ve
 * `RouteNotFoundException` fırlıyor.
 *
 * ⚠️ `shouldRenderJsonWhen` bunu ÇÖZMÜYOR: `Handler::unauthenticated()`
 * doğrudan `$request->expectsJson()` soruyor ve o metoda hiç uğramıyor.
 * `$exceptions->render(AuthenticationException::class)` da çalışmıyor —
 * Laravel bu istisnayı kullanıcı geri çağırmalarından ÖNCE eşliyor.
 * İkisi de denendi, ikisi de 500 bıraktı.
 *
 * Tek sağlam nokta isteğin kendisi: başlığı burada koyuyoruz.
 *
 * ⚠️ 425 TESTİN HİÇBİRİ YAKALAMADI — hepsi `postJson`/`getJson` kullanıyor
 * ve o yardımcılar başlığı otomatik ekliyor. Gerçek `curl` koşusunda
 * ortaya çıktı; 1D.6'daki dersin aynısı.
 */
class ForceJson
{
    public function handle(Request $istek, Closure $sonraki): Response
    {
        $istek->headers->set('Accept', 'application/json');

        return $sonraki($istek);
    }
}
