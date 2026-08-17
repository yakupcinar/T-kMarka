<?php

namespace App\Providers;

use App\Domain\Identity\EmailNormalizer;
use App\Domain\Quota\QuotaGuard;
use App\Platform\PlanQuotaGuard;
use App\Platform\Subscription\SubscriptionProvider;
use App\Platform\Subscription\SubscriptionProviderFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        /*
        | ★ KOTA KAPISI (3F) — arayüz Domain'de, uygulama Platform'da.
        |
        | ⚠️ Bağımlılık BİLEREK ters çevrildi: kota markanın planına bakıyor
        | ve plan MERKEZ kayıtta, ama `app/Domain/` kiracıdan habersiz olmak
        | zorunda (M-2.7, ölçülüyor). İş mantığı "kotam var mı" diye
        | soruyor, planın nereden geldiğini bilmiyor.
        */
        $this->app->bind(
            QuotaGuard::class,
            PlanQuotaGuard::class,
        );

        /*
        | ★ ABONELİK SAĞLAYICISI (3E) — MERKEZ yapılandırmadan.
        |
        | ⚠️ 1E'deki ödeme sağlayıcısı marka `settings`'inden geliyor ve her
        | markada AYRI. Bu ise TEK: TıkMarka'nın kendi tahsilat hesabı.
        | İkisi karışırsa markanın parası bize, bizim paramız markaya gider.
        */
        $this->app->bind(
            SubscriptionProvider::class,
            fn () => (new SubscriptionProviderFactory)->yap(),
        );

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

        /*
        | HIZ SINIRLAYICILAR — kaba kuvvet saldırısının en ucuz önlemi.
        |
        | M-4.1/3: Caddy'nin hız sınırlaması olgun değil, bu yüzden koruma
        | bilerek UYGULAMA katmanında. Yani bu satırlar "iyi olur" değil,
        | kapatılmış bir açığın tek sahibi.
        */

        // Giriş: e-posta + IP birlikte sayılıyor.
        // Sadece IP olsaydı ortak ağdaki (okul, ofis) kullanıcılar birbirini
        // kilitlerdi. Sadece e-posta olsaydı saldırgan farklı adreslerle
        // sınırsız deneme yapardı.
        RateLimiter::for('giris', fn (Request $istek) => Limit::perMinute(5)
            ->by(EmailNormalizer::normallestir((string) $istek->input('email')).'|'.$istek->ip()));

        // Kayıt: IP başına saatlik. Sahte hesap üretimini yavaşlatır.
        RateLimiter::for('kayit', fn (Request $istek) => Limit::perHour(10)->by($istek->ip()));
    }
}
