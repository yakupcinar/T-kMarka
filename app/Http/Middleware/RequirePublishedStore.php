<?php

namespace App\Http\Middleware;

use App\Domain\Settings\StorePublication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mağaza kapalıysa vitrini kapatır — TEK KAPI.
 *
 * Kullanımı:  Route::...->middleware('magaza-acik')
 *
 * Yalnızca VİTRİN rotalarına takılıyor. Panel her zaman açık kalmalı;
 * mağazayı tekrar açmanın tek yolu panel ve o da kapansaydı marka
 * kendini dışarıda bırakırdı — 1A.3'teki "sahip kendi rolünden
 * staff.manage'i kaldıramaz" kilidiyle aynı düşünce.
 */
class RequirePublishedStore
{
    public function __construct(private readonly StorePublication $yayin) {}

    public function handle(Request $istek, Closure $next): Response
    {
        if ($this->yayin->yayindaMi()) {
            return $next($istek);
        }

        /*
        | 503 + Retry-After.
        |
        | Neden 404 değil: mağaza var, sadece şu anda hizmet vermiyor.
        | Neden çıplak 503 değil: `Retry-After` "bu geçici" demenin
        | standart yolu. Arama motorları bu başlığı görünce sayfayı
        | dizinden düşürmüyor, sonra tekrar geliyor. Başlıksız 503'ü
        | kalıcı bir bozukluk sayabilirler.
        |
        | 3600 sn = 1 saat: marka düzenlemesini bitirene kadar makul bir
        | süre; çok kısa olsaydı gereksiz yere tekrar tekrar yoklanırdık.
        */
        /*
        | ★ TARAYICIYA HTML, API'YE JSON. (4A'da eklendi)
        |
        | ⚠️ Önceden HER İKİSİNE de JSON dönüyordu ve API için doğruydu —
        | arayüz yoktu (M-3). Faz 4'te vitrin var: kapalı mağazayı ziyaret
        | eden müşteri ekranında süslü parantezli bir metin görürdü.
        |
        | ⚠️ Ayrımı `expectsJson()` yapıyor ve bu ANCAK 4A'dan sonra güvenilir:
        | `ForceJson` global olduğu sürece HER istek "JSON istiyorum" derdi
        | ve bu dal hiç çalışmazdı. Middleware `api` grubuna daraltıldı.
        */
        if (! $istek->expectsJson()) {
            return response()->view('storefront.kapali', [], Response::HTTP_SERVICE_UNAVAILABLE)
                ->header('Retry-After', '3600');
        }

        return response()->json(
            ['message' => 'Mağaza şu anda hizmet vermiyor, kısa süre içinde tekrar açılacak.'],
            Response::HTTP_SERVICE_UNAVAILABLE,
        )->header('Retry-After', '3600');
    }
}
