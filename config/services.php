<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
    | iyzico — YALNIZCA ORTAM AYARI.
    |
    | ⚠️ Burada YALNIZCA adres var. Marka anahtarları (API + gizli anahtar)
    | BURAYA GİRMEZ: her markanın kendi ödeme hesabı var (M-1), `.env`'e
    | yazılsaydı bütün markalar aynı hesaba tahsilat yapardı ve bu hata
    | vermezdi — para yanlış yere giderdi.
    |
    | Anahtarların yeri: `settings` tablosu, `payment` grubu, ŞİFRELİ (§4).
    |
    | Sandbox mı canlı mı sorusu ise markaya göre değil ORTAMA göre
    | değişiyor; o yüzden adres burada.
    */
    'iyzico' => [
        'base_uri' => env('IYZICO_BASE_URI', 'https://sandbox-api.iyzipay.com'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
