<?php

use App\Http\Platform\AuthController as PlatformAuth;
use App\Http\Platform\TenantController as PlatformTenants;
use Illuminate\Support\Facades\Route;

/*
| KONTROL DÜZLEMİ — merkez alan adında. (3C)
|
| ★ NEDEN AYRI DOSYA: bu rotalar `api` middleware grubunda olmak zorunda,
| `web` grubunda DEĞİL.
|
| ⚠️ GERÇEK HTTP KOŞUSU YAKALADI. Önce `routes/web.php` içindeydiler ve
| bütün testler yeşildi; ama gerçek `curl` isteği `CSRF token mismatch`
| aldı. Sebep: `web` grubu CSRF koruması uyguluyor ve token'ı çerezden
| bekliyor. Testler `postJson` kullandığı için bu hiç görünmedi —
| 1A.2'de aynı karar verilmişti ("api grubu, web değil; CSRF token
| istemcisini kırardı") ama 3C'de tekrar unutuldu.
|
| ⚠️ Bu rotalar YALNIZCA merkez alan adlarında; marka alan adlarında
| yoklar (`routes/tenant.php` ayrı dosya, ayrı kapı görevlisi).
*/
foreach (config('tenancy.central_domains') as $centralDomain) {
    Route::domain($centralDomain)->group(function () {

        /*
        | ⚠️ KAYIT UCU YOK. Yönetici yalnızca `platform:kullanici`
        | komutuyla açılıyor, yani sunucuya erişebilen kişi tarafından.
        | Uç olsaydı internetteki herkes kendine BÜTÜN markalara erişen
        | bir hesap yaratabilirdi (1A.2'nin panel kararıyla aynı).
        */
        Route::post('/platform/login', [PlatformAuth::class, 'login'])
            ->middleware('throttle:giris');

        /*
        | ⚠️ `auth:platform` — ÜÇÜNCÜ kimlik alanı. Marka personeli token'ı
        | buraya giremez; girebilseydi bir markanın sahibi bütün markaları
        | görürdü.
        */
        Route::middleware('auth:platform')->group(function () {
            Route::post('/platform/logout', [PlatformAuth::class, 'logout']);
            Route::get('/platform/me', [PlatformAuth::class, 'me']);

            Route::get('/platform/tenants', [PlatformTenants::class, 'index']);
            Route::get('/platform/tenants/{tenant}', [PlatformTenants::class, 'show']);

            /*
            | ⚠️ Durum değiştirme AYRI uç: kendi kuralları var (geçiş
            | tablosu) ve yanlışlıkla gönderilen bir alan markayı
            | kapatmamalı.
            */
            Route::post('/platform/tenants/{tenant}/status', [PlatformTenants::class, 'status']);
            Route::post('/platform/tenants/{tenant}/plan', [PlatformTenants::class, 'assignPlan']);
        });

    });
}
