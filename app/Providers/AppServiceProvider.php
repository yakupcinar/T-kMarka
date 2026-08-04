<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        | MERKEZ migration klasörünü kaydediyoruz.
        |
        | database/migrations kökü bilerek boş (bkz. PLAN.md 0.5/2) — bu
        | yüzden Laravel varsayılan olarak hiçbir tarif bulamıyor. Burada
        | landlord/ klasörünü tanıtınca hem `php artisan migrate` hem de
        | testlerdeki `migrate:fresh` onu görüyor.
        |
        | Marka tarifleri (tenant/) BİLEREK kaydedilmiyor: onlar merkez
        | veritabanına değil, her markanın kendi şemasına uygulanacak
        | (`php artisan tenants:migrate`).
        */
        $this->loadMigrationsFrom(database_path('migrations/landlord'));
    }
}
