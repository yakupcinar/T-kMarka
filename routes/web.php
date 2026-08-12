<?php

use App\Http\Platform\DomainCheckController;
use App\Http\Showcase\WebShowcaseController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
| MERKEZ rotaları — yalnızca kontrol düzleminin adreslerinde geçerli.
|
| Marka rotaları routes/tenant.php'de. İki dosya da aynı adresi (örn "/")
| tanımlayabildiği için burayı alan adına KİLİTLİYORUZ; yoksa sonra
| yüklenen dosya diğerini gölgeler ve merkez adres erişilemez olur.
|
| Adres listesi config/tenancy.php → central_domains
*/

/*
|--------------------------------------------------------------------------
| DEMO / SUNUM VİTRİNİ — markanın kendi alan adında
|--------------------------------------------------------------------------
|
| Bu rotalar API DEĞİL: yalnızca Blade ile sunum yapar. Yine de tenant
| middleware'i şarttır; `Product::query()` gibi sorguların doğru marka
| şemasında çalışmasının tek yolu budur. `routes/tenant.php` API yüzeyi
| olarak saf kalır, hiçbir API controller'ı burada kullanılmaz.
|
| ⚠️ Ekran sadece güvenli özetleri gösterir: müşteri bilgisi, adres, ham
| webhook gövdesi veya ödeme anahtarı burada ASLA açılmaz. Ngrok adresi
| izleyicilere verilebildiği için bu sınır özellikle önemlidir.
*/
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('showcase')->group(function (): void {
    Route::get('/', [WebShowcaseController::class, 'index'])->name('showcase.index');
    Route::get('/activity', [WebShowcaseController::class, 'activity'])->name('showcase.activity');
});

foreach (config('tenancy.central_domains') as $centralDomain) {
    Route::domain($centralDomain)->group(function () {

        Route::get('/', function () {
            return 'TıkMarka kontrol düzlemi';
        });

        // Caddy on-demand TLS için sorar: "bu alan adı sizin mi?" (M-4.1/1)
        // 200 = kayıtlı, sertifika alınabilir · 404 = kayıtlı değil
        Route::get('/tenancy/domain-check', DomainCheckController::class)
            ->name('tenancy.domain-check');

    });
}
