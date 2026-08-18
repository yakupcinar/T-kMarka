<?php

namespace App\Http\Middleware;

use App\Http\Storefront\PaymentController;
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
    /**
     * `api` grubunda olduğu hâlde İNSANIN GÖRDÜĞÜ ekranlar.
     *
     * ★ ÖDEME DÖNÜŞÜ (4B'de gerçek koşuda bulundu).
     *
     * Bu uç `api` grubunda çünkü sağlayıcı POST ediyor ve CSRF üretemez —
     * yani `web` grubuna taşınamıyor. Ama müşterinin bankadan döndüğü
     * EKRAN da burası.
     *
     * ⚠️ ÖLÇÜLDÜ: [PaymentReturnController]'a HTML dalı yazıldı ve hiç
     * çalışmadı; bu middleware `Accept`'i ezdiği için `expectsJson()`
     * orada HER ZAMAN doğruydu. Ödemesini yeni bitirmiş müşteri ekranında
     * süslü parantezli bir metin görüyordu — testler yeşildi, gerçek
     * `curl` koşusu gösterdi.
     *
     * ⚠️ Liste DAR tutulmalı: her uç buraya eklenirse 2E'de ölçülen 500
     * hatası geri gelir.
     *
     * @var list<string>
     */
    private const HTML_UCLARI = [
        PaymentController::DONUS_YOLU,
    ];

    public function handle(Request $istek, Closure $sonraki): Response
    {
        if (! $this->insanEkraniMi($istek)) {
            $istek->headers->set('Accept', 'application/json');
        }

        return $sonraki($istek);
    }

    private function insanEkraniMi(Request $istek): bool
    {
        foreach (self::HTML_UCLARI as $yol) {
            if ($istek->is(ltrim($yol, '/'))) {
                return true;
            }
        }

        return false;
    }
}
