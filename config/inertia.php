<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Inertia — panel arayüzünün sunucu tarafı (4C)
    |--------------------------------------------------------------------------
    |
    | ⚠️ SSR AÇILMIYOR (4-K2). Paketin varsayılanı zaten kapalı; burada
    | AÇIKÇA yazıyoruz ki bir gün "performans için açalım" denince
    | gerekçesi karşımıza çıksın:
    |
    | SSR ayrı bir Node süreci çalıştırıyor; süreç uzun ömürlü ve BÜTÜN
    | MARKALAR için ortak. Modül seviyesindeki durum istekler arasında
    | paylaşılıyor (cross-request state pollution) — yani MARKA SIZMASI.
    | M-2.4'te pgBouncer'ı reddetme gerekçesinin aynısı.
    */
    'ssr' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | GELİŞTİRİCİ ARAÇLARI — KAPALI
    |--------------------------------------------------------------------------
    |
    | ★ 4D'de GERÇEK KOŞUDA kapatıldı.
    |
    | Açıkken her istek `storage/inertia-devtools/` altına bir JSON kaydı
    | yazıyor ve periyodik olarak "son temizlik" zaman damgasını
    | güncelliyor. Bu yazma, geliştirme ortamındaki bağlı klasörde
    | `errno=35 Resource deadlock avoided` ile düştü ve panelin BÜTÜN
    | sayfaları 500 vermeye başladı.
    |
    | ⚠️ Belirti yanıltıcıydı: hata mesajı Inertia'dan değil
    | `file_put_contents`'ten geliyordu ve yığın izinde sayfayı yazan kod
    | hiç görünmüyordu.
    |
    | ⚠️ Kaybımız yok: bu araç Inertia'nın tarayıcı eklentisi içindir,
    | onu kullanmıyoruz. Testler ve panelin kendisi etkilenmiyor.
    | İhtiyaç olursa `INERTIA_DEVTOOLS_ENABLED=true` ile açılabilir.
    */
    'devtools' => [
        'enabled' => env('INERTIA_DEVTOOLS_ENABLED', false),
    ],

];
