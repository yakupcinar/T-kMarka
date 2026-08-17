<?php

use App\Http\Platform\AuthController as PlatformAuth;
use App\Http\Platform\SignupController as PlatformSignup;
use App\Http\Platform\SubscriptionController as PlatformSubscription;
use App\Http\Platform\SubscriptionWebhookController as SubscriptionWebhook;
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
        | ★ SELF-SERVİS MARKA KAYDI (3D) — KİMLİKSİZ.
        |
        | ⚠️ Kimlik doğrulaması yok ve olmamalı: henüz hesabı olmayan biri
        | kaydoluyor. Korumalar başka katmanlarda —
        |   · hız sınırı (burada)
        |   · haftalık tavan: sertifika kotası (TenantProvisioning, 3-K5)
        |   · ayrılmış alt alan adları (ReservedSubdomains)
        |
        | ⚠️ `throttle:kayit` — vitrindeki müşteri kaydıyla aynı sınıf
        | (1A.2'de tanımlandı, saatte 10/IP). Marka açmak müşteri kaydından
        | çok daha pahalı bir işlem: şema + 28 migration.
        */
        Route::post('/platform/signup', [PlatformSignup::class, 'store'])
            ->middleware('throttle:kayit');

        Route::get('/platform/signup/check', [PlatformSignup::class, 'checkSubdomain'])
            ->middleware('throttle:kayit');

        /*
        | ⚠️ `auth:platform` — ÜÇÜNCÜ kimlik alanı. Marka personeli token'ı
        | buraya giremez; girebilseydi bir markanın sahibi bütün markaları
        | görürdü.
        */
        /*
        | ★ ABONELİK BİLDİRİMİ (3E) — sağlayıcının SUNUCUSU çağırıyor.
        |
        | ⚠️ `auth:platform` DIŞINDA ve olmak zorunda: çağıran iyzico'nun
        | sunucusu, bizim token'ımız onda yok. Koruma İMZA (1E.4'ün aynısı).
        */
        Route::post('/platform/subscription/webhook', SubscriptionWebhook::class);

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

            /*
            | ABONELİK (3E).
            |
            | ⚠️ Kart verisi bu uçtan geçiyor ama HİÇBİR YERE yazılmıyor —
            | ne veritabanına ne günlüğe. Saklamak bizi PCI kapsamına
            | sokardı.
            */
            Route::get('/platform/plans', [PlatformSubscription::class, 'plans']);
            Route::post('/platform/tenants/{tenant}/subscription', [PlatformSubscription::class, 'subscribe']);
            Route::delete('/platform/tenants/{tenant}/subscription', [PlatformSubscription::class, 'cancel']);
        });

    });
}
