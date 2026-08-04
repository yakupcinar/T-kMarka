<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        //
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
