<?php

use App\Models\Customer;
use App\Models\User;
use App\Platform\Models\PlatformUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'customer'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        /*
        | İKİ AYRI KİMLİK ALANI (1A.0).
        |
        | Müşteri ve personel farklı tablolarda, farklı yüzeylerden giriyor.
        | Tek guard olsaydı "bu token hangi tabloya ait" sorusu her istekte
        | tekrar sorulur ve bir gün biri unuturdu. Ayrı guard bu soruyu
        | rotanın kendisinde cevaplıyor: `auth:staff` yazan bir uca müşteri
        | token'ı giremez.
        |
        | İkisi de `sanctum` sürücüsü: kimlik doğrulama token tabanlı (K-12).
        | Oturum çerezi kullanılmıyor — panel ileride ayrı alt alan adına
        | taşınırsa çerez kapsamı sorun çıkarırdı.
        */

        // Vitrin tarafı — markanın müşterisi
        'customer' => [
            'driver' => 'sanctum',
            'provider' => 'customers',
        ],

        // Panel tarafı — markanın personeli
        'staff' => [
            'driver' => 'sanctum',
            'provider' => 'staff',
        ],

        /*
        | ★ ÜÇÜNCÜ KİMLİK ALANI (3C) — kontrol düzlemi.
        |
        | ⚠️ TıkMarka'yı işleten kişi; yetkisi BÜTÜN MARKALARA uzanıyor,
        | sistemdeki en tehlikeli yetki. Marka personeliyle aynı guard'da
        | olsaydı bir markanın sahibi merkez uçlara girebilirdi.
        |
        | ⚠️ Kullanıcıları MERKEZ şemada (`platform_users`); marka
        | şemasındaki `users` tablosuyla hiçbir ilişkisi yok.
        */
        'platform' => [
            'driver' => 'sanctum',
            'provider' => 'platform_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        /*
        | Provider = "kullanıcıyı nereden bulacağız".
        | İkisi de marka şemasındaki tablolara bakıyor; hangi markanınki
        | olduğu sorusu burada sorulmuyor — `search_path` zaten belirlemiş
        | oluyor (M-2.1).
        */

        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],

        'platform_users' => [
            'driver' => 'eloquent',
            'model' => PlatformUser::class,
        ],

        'staff' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
