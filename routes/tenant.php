<?php

declare(strict_types=1);

use App\Http\Panel\AuthController as PanelAuth;
use App\Http\Storefront\AuthController as VitrinAuth;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| MARKA rotaları — yalnızca markanın kendi alan adında geçerli
|--------------------------------------------------------------------------
|
| Merkez rotaları routes/web.php'de (kontrol düzlemi).
|
| Middleware zinciri:
|   api                             oturumsuz + CSRF yok. 'web' kullanılsaydı
|                                   token istemcisi CSRF üretemediği için her
|                                   POST kırılırdı.
|   InitializeTenancyByDomain       KAPI GÖREVLİSİ: host → domains → search_path
|   PreventAccessFromCentralDomains bu rotalara merkez adresten girilemez
|
| Rota EŞLEŞMESİ ile MIDDLEWARE ayrı iki aşama: burada yalnızca bağlantı
| kuruluyor; kiracı çözümlemesi middleware çalışınca oluyor.
*/

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // Markanın ayakta olduğunu görmek için — vitrin Faz 4'te gelecek (M-3).
    Route::get('/', fn () => response()->json([
        'tenant' => tenant('id'),
        'message' => 'Marka ayakta. Vitrin Faz 4te.',
    ]));

    /*
    | VİTRİN — markanın müşterisi
    */
    Route::prefix('api')->group(function () {

        // Hız sınırları AppServiceProvider'da tanımlı.
        // M-4.1/3: Caddy'de hız sınırlaması yok, koruma bilerek burada.
        Route::post('/register', [VitrinAuth::class, 'register'])->middleware('throttle:kayit');
        Route::post('/login', [VitrinAuth::class, 'login'])->middleware('throttle:giris');

        // auth:customer → yalnızca CUSTOMER token'ı geçer.
        // Personel token'ı buraya giremez (1A.0'da kanıtlandı).
        Route::middleware('auth:customer')->group(function () {
            Route::post('/logout', [VitrinAuth::class, 'logout']);
            Route::get('/me', [VitrinAuth::class, 'me']);
        });
    });

    /*
    | PANEL — markanın personeli
    |
    | ⚠️ KAYIT UCU YOK ve olmayacak. Personel davetle gelir (1A.3).
    | Olsaydı markanın alan adını bilen herkes panele hesap açardı.
    */
    Route::prefix('panel')->group(function () {

        Route::post('/login', [PanelAuth::class, 'login'])->middleware('throttle:giris');

        // auth:staff → yalnızca STAFF token'ı geçer.
        // Müşteri token'ı buraya giremez (1A.0'da kanıtlandı).
        Route::middleware('auth:staff')->group(function () {
            Route::post('/logout', [PanelAuth::class, 'logout']);
            Route::get('/me', [PanelAuth::class, 'me']);
        });
    });
});
