<?php

use Illuminate\Support\Facades\DB;

/*
| Zaman diliminin veritabanı oturumunda SABİT olduğunu koruyan test.
|
| ★ Neden var: Laravel `now()`'ı sorguya OFİSSİZ metin olarak bağlıyor
| ('2026-08-11 14:01:38'). PostgreSQL ofissiz metni oturumun TimeZone'una
| göre yorumluyor — yani "süresi doldu mu" karşılaştırmasının cevabı
| oturum ayarına bağlı. Ayar UTC olmaktan çıkarsa süresi DOLMAMIŞ
| rezervasyonlar ölmüş sayılır ve müşteri ödeme sayfasındayken stoğu
| kapılır. Hiçbir yerde hata görünmez.
|
| WooCommerce'te bu gerçekten yaşandı (#43593): tutma süresi GMT, kontrol
| yerel saat üzerinden hesaplanıyordu; Brisbane'de siparişler 60 dakika
| dolmadan iptal ediliyordu.
*/

it('★ veritabanı oturumu UTC — ofissiz damga yanlış yorumlanmıyor', function () {
    expect(DB::selectOne('SHOW TimeZone')->TimeZone)->toBe('UTC')
        ->and(config('app.timezone'))->toBe('UTC');
});

it('★ süresi DOLMAMIŞ rezervasyon hiçbir oturumda ölmüş sayılmıyor', function () {
    /*
    | KIRMIZI KONTROL — kırılganlığın kendisini gösteriyor.
    |
    | Aynı satır, aynı karşılaştırma, iki farklı oturum saat diliminde iki
    | farklı cevap veriyor. Testin koruduğu şey birinci cevap; ikincisi
    | ayar kayarsa ne olacağının kanıtı.
    */
    $gelecek = now()->addMinutes(15);
    $simdi = now()->format('Y-m-d H:i:s'); // Laravel'in bağladığı biçim — OFİSSİZ

    $soru = 'SELECT (?::timestamptz < ?) AS olmus';

    $utc = DB::selectOne($soru, [$gelecek->toIso8601String(), $simdi]);
    expect($utc->olmus)->toBeFalse();

    DB::statement("SET TimeZone = 'America/New_York'");
    $kaymis = DB::selectOne($soru, [$gelecek->toIso8601String(), $simdi]);

    // ⚠️ AYNI satır, AYNI an — yalnızca oturum ayarı değişti.
    expect($kaymis->olmus)->toBeTrue();

    DB::statement("SET TimeZone = 'UTC'");
});
