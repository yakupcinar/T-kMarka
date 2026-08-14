<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Askıya alınmış markanın PANELİNİ kapatır. (3C)
 *
 * Kullanımı:  Route::...->middleware('marka-aktif')
 *
 * ★ 4 numaralı kararın uygulaması: askıda PANEL kapanıyor, VİTRİN AÇIK
 * KALIYOR.
 *
 * ⚠️ Vitrini de kapatmak markayı değil markanın MÜŞTERİLERİNİ vururdu:
 * siparişini takip edemeyen, iade açamayan, parasını ödemiş insanlar.
 * Onların bizimle hiçbir sözleşmesi yok — faturayı ödemeyen marka.
 * (Shopify donmuş mağazada ikisini de kapatıyor; biz bilerek ayrıldık.)
 *
 * ⚠️ Yalnızca PANEL rotalarına takılıyor. Vitrinde `magaza-acik` var, o
 * ayrı bir soru soruyor: "marka mağazasını yayınladı mı".
 */
class RequireActiveTenant
{
    public function handle(Request $istek, Closure $next): Response
    {
        $marka = tenant();

        /*
        | ⚠️ Kiracı yoksa KARIŞILMIYOR. Bu middleware yalnızca marka
        | rotalarında; kapı görevlisi zaten kiracıyı çözmüş olmalı.
        | Burada 403 dönseydi merkez rotalara kazara takıldığında
        | anlaşılmaz bir hata verirdi.
        */
        if ($marka === null) {
            return $next($istek);
        }

        $durum = $marka->status ?? null;

        if ($durum instanceof TenantStatus && $durum->panelAcikMi()) {
            return $next($istek);
        }

        /*
        | 403 — 503 DEĞİL.
        |
        | ⚠️ Fark niyette: 503 "geçici bir arıza, sonra dene" demek ve
        | arama motoru buna göre davranıyor. Askı ise bir KARAR — arıza
        | değil. Marka ne yapması gerektiğini bilmeli.
        |
        | ⚠️ Sebep AÇIKÇA söyleniyor: markanın kendi hesabı hakkında bilgi
        | saklamanın bir gerekçesi yok, aksine ödemesini yapması için
        | bilmesi gerekiyor.
        */
        return response()->json([
            'message' => 'Marka hesabı şu anda askıda. Panele erişim için aboneliğinizi güncelleyin.',
            'status' => $durum?->value,
        ], Response::HTTP_FORBIDDEN);
    }
}
