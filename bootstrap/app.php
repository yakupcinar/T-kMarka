<?php

use App\Http\Middleware\RequirePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    /*
    | Laravel artisan komutlarını yalnızca app/Console/Commands klasöründe
    | kendiliğinden bulur. Bizim kiracılık komutlarımız M-2.7 gereği
    | app/Tenancy/ altında duruyor ("kiracılığın tamamı tek yerde"), bu
    | yüzden klasörü burada tanıtıyoruz.
    */
    ->withCommands([
        __DIR__.'/../app/Tenancy/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        | Rotalarda `izin:staff.manage` şeklinde kullanılabilmesi için takma ad.
        */
        $middleware->alias([
            'izin' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        | Tanımsız bir alan adına istek gelirse paket istisna fırlatıyor ve
        | Laravel bunu 500 (sunucu hatası) sayıyor. Doğru cevap 404:
        | sunucuda bir şey patlamadı, öyle bir marka yok.
        |
        | Ayrıca 500, saldırgana "burada bir şey var ama bozuldu" bilgisi
        | verir; 404 hiçbir şey söylemez.
        */
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $e) {
            abort(404);
        });

    })->create();
