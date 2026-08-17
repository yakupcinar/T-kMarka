<?php

/*
|--------------------------------------------------------------------------
| ABONELİK — BİZİM markadan tahsilatımız. (3E)
|--------------------------------------------------------------------------
|
| ⚠️ 1E'deki ödeme ayarlarıyla KARIŞTIRILMAMALI. Orada her markanın KENDİ
| iyzico anahtarları vardı ve marka `settings` tablosunda tutuluyordu.
| Burası TEK ve MERKEZ: TıkMarka'nın kendi hesabı.
|
| ⚠️ Anahtarlar `settings`'e KONMUYOR: o tablo marka şemasında ve marka
| personeli okuyabiliyor. Bizim tahsilat anahtarımızı marka görmemeli.
*/

return [
    /*
    | Hangi sağlayıcı kullanılacak.
    |
    | ⚠️ Varsayılan `fake`: gerçek sağlayıcı yapılandırılmadan çalışan bir
    | ortamda kazara canlı tahsilat denenmesin.
    */
    'provider' => env('SUBSCRIPTION_PROVIDER', 'fake'),

    /*
    | Bildirim imza anahtarı.
    |
    | ⚠️ BOŞ BIRAKILIRSA imzalama YASAK (istisna fırlıyor). 1E.7'de
    | ölçüldü: `hash_hmac(..., '')` geçerli GÖRÜNEN bir imza üretiyor ve
    | doğrulama hiçbir şeyi korumuyor.
    */
    'webhook_secret' => env('SUBSCRIPTION_WEBHOOK_SECRET', ''),

    'iyzico' => [
        'base_url' => env('SUBSCRIPTION_IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com'),
        'api_key' => env('SUBSCRIPTION_IYZICO_API_KEY', ''),
        'secret_key' => env('SUBSCRIPTION_IYZICO_SECRET_KEY', ''),
    ],
];
