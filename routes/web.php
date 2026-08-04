<?php

use Illuminate\Support\Facades\Route;

/*
| MERKEZ rotaları — yalnızca kontrol düzleminin adreslerinde geçerli.
|
| Marka rotaları routes/tenant.php'de. İki dosya da aynı adresi (örn "/")
| tanımlayabildiği için burayı alan adına KİLİTLİYORUZ; yoksa sonra
| yüklenen dosya diğerini gölgeler ve merkez adres erişilemez olur.
|
| Adres listesi config/tenancy.php → central_domains
*/

foreach (config('tenancy.central_domains') as $centralDomain) {
    Route::domain($centralDomain)->group(function () {

        Route::get('/', function () {
            return 'TıkMarka kontrol düzlemi';
        });

    });
}
