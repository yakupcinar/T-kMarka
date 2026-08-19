<?php

use Illuminate\Support\Facades\Route;

/*
| FAZ 4.5 BİTİŞ ÖLÇÜTÜ (4.5F) — "panelde ekranı olmayan uç kalmaz".
|
| ★ NEDEN YAPISAL TEST: Faz 4.5 bir ÖLÇÜMLE açıldı (73 uç, 34 sayfa) ve
| aynı ölçüm kapanışta da yapılmalı. Elle sayılsaydı bir sonraki blokta
| yeni bir uç eklenip ekranı unutulduğunda kimse fark etmezdi.
|
| ⚠️ Test uç SAYISINI değil ALAN KAPSAMINI ölçüyor: her API alanının bir
| ekran karşılığı var mı. Sayı karşılaştırması yanıltıcı olurdu — bir
| ekran birden çok ucu karşılayabiliyor (ürün ekranı 14 ucu karşılıyor).
*/

it('★★★ PANELDE EKRANI OLMAYAN API ALANI KALMADI', function () {
    $ucAlanlari = [];
    $sayfaAlanlari = [];

    foreach (Route::getRoutes()->getRoutes() as $rota) {
        $yol = $rota->uri();

        if (str_starts_with($yol, 'panel/')) {
            $ucAlanlari[] = explode('/', substr($yol, strlen('panel/')))[0];
        }

        if (str_starts_with($yol, 'yonetim/')) {
            $sayfaAlanlari[] = explode('/', substr($yol, strlen('yonetim/')))[0];
        }
    }

    $ucAlanlari = array_values(array_unique($ucAlanlari));
    $sayfaAlanlari = array_values(array_unique($sayfaAlanlari));

    /*
    | API alanı → onu karşılayan ekran(lar).
    |
    | ⚠️ Eşleme ELLE yazılıyor ve bu bilinçli: adları otomatik eşleştirmek
    | (`products` → `urunler`) mümkün değil, çünkü Türkçe. Elle yazılan
    | eşleme aynı zamanda BELGE: hangi ucun nerede karşılandığı okunuyor.
    */
    $esleme = [
        'products' => 'urunler',
        'categories' => 'katalog',
        'options' => 'katalog',
        'collections' => 'koleksiyonlar',
        'orders' => 'siparisler',
        'returns' => 'iadeler',
        'reviews' => 'yorumlar',
        'staff' => 'personel',
        'roles' => 'personel',
        'domains' => 'alan-adlari',
        'legal' => 'yasal',
        'payment' => 'odeme-ayarlari',
        'settings' => 'magaza',
        'store' => 'magaza',
        'login' => 'giris',
        'logout' => 'cikis',

        /*
        | ⚠️ BİLEREK EKRANI YOK: `/panel/me` istemcinin "ben kimim" sorusu.
        | Panelde bu bilgi zaten her sayfada paylaşılıyor (4C); ayrı bir
        | ekran anlamsız olurdu.
        */
        'me' => null,
    ];

    $eksikler = [];

    foreach ($ucAlanlari as $alan) {
        if (! array_key_exists($alan, $esleme)) {
            $eksikler[] = "{$alan} → eşlemede YOK (yeni uç mu eklendi?)";

            continue;
        }

        $ekran = $esleme[$alan];

        if ($ekran !== null && ! in_array($ekran, $sayfaAlanlari, true)) {
            $eksikler[] = "{$alan} → '{$ekran}' ekranı bulunamadı";
        }
    }

    expect($eksikler)->toBe([], "Ekranı olmayan uç alanları:\n".implode("\n", $eksikler));
});

it('★★★ VİTRİN API ALANLARININ DA EKRAN KARŞILIĞI VAR', function () {
    /*
    | ★ BU TESTİ GERÇEK KULLANIM DOĞURDU. 4.5F'nin kapsam testi yalnızca
    | PANEL uçlarına bakıyordu; vitrin API'sinin ekran karşılığı hiç
    | ölçülmüyordu.
    |
    | Sonuç: marka koleksiyon kuruyordu ama müşteri onu HİÇBİR YERDEN
    | göremiyordu — uçlar (`/api/collections`) vardı, sayfa yoktu ve
    | hiçbir test bunu göremezdi.
    */
    $ucAlanlari = [];
    $sayfaAlanlari = [];

    foreach (Route::getRoutes()->getRoutes() as $rota) {
        $yol = $rota->uri();

        if (str_starts_with($yol, 'api/')) {
            $ucAlanlari[] = explode('/', substr($yol, strlen('api/')))[0];
        }

        /*
        | ⚠️ Vitrin sayfaları KÖKTE duruyor (`/sepet`, `/urun/{slug}`),
        | önek yok — panel gibi ayırt edilemiyor. Bu yüzden `yonetim/`
        | ve `api/` dışındaki HER rota vitrin sayfası sayılıyor.
        */
        if (! str_starts_with($yol, 'api/') && ! str_starts_with($yol, 'yonetim/') && ! str_starts_with($yol, 'panel/')) {
            $sayfaAlanlari[] = explode('/', $yol)[0];
        }
    }

    $ucAlanlari = array_values(array_unique($ucAlanlari));
    $sayfaAlanlari = array_values(array_unique($sayfaAlanlari));

    /*
    | Vitrin API alanı → onu karşılayan sayfa.
    |
    | ⚠️ `null` = BİLEREK ekranı yok, gerekçesiyle.
    */
    $esleme = [
        'products' => 'urun',
        'categories' => null,     // ⚠️ kategori gezinme sayfası YOK — bilinen borç
        'collections' => 'koleksiyonlar',
        'cart' => 'sepet',
        'checkout' => 'odeme',
        'orders' => 'hesabim',
        'addresses' => 'hesabim',
        'legal' => 'yasal',
        'login' => 'giris',
        'register' => 'kayit',
        'logout' => 'cikis',

        // ⚠️ "ben kimim" — vitrinde her sayfada zaten paylaşılıyor.
        'me' => null,

        // ⚠️ KVKK veri talebi: bağlantı e-postadan geliyor, sayfası yok (2G).
        'privacy' => null,
    ];

    $eksikler = [];

    foreach ($ucAlanlari as $alan) {
        if (! array_key_exists($alan, $esleme)) {
            $eksikler[] = "{$alan} → eşlemede YOK (yeni uç mu eklendi?)";

            continue;
        }

        $ekran = $esleme[$alan];

        if ($ekran !== null && ! in_array($ekran, $sayfaAlanlari, true)) {
            $eksikler[] = "{$alan} → '{$ekran}' sayfası bulunamadı";
        }
    }

    expect($eksikler)->toBe([], "Ekranı olmayan vitrin uç alanları:\n".implode("\n", $eksikler));
});

/*
| SAYFA KATMANINDA GUARD YAZILMIŞ OLMALI (4.5I).
|
| ⚠️ Varsayılan guard `customer` (sanctum, TOKEN). Sayfa katmanında kimlik
| OTURUMDA; guard yazılmadığı sürece sanctum sorulur, `null` döner ve giriş
| yapmış müşteri MİSAFİR sayılır. Bedeli sessizdi: sepet ve sipariş
| müşteriye hiç bağlanmadı, "Siparişlerim" hiçbir zaman dolamadı.
|
| ⚠️ API katmanı BUNUN TERSİ — orada varsayılan guard DOĞRU. Bu yüzden test
| tüm vitrini değil yalnızca sayfa dosyalarını tarıyor.
*/
it('★★★ SAYFA katmani guardi ACIKCA yaziyor', function () {
    $sayfaDosyalari = [
        'CartResolver.php',
        'CartPageController.php',
        'CheckoutPageController.php',
        'AccountPageController.php',
        'ProductPageController.php',
        'CollectionPageController.php',
    ];

    foreach ($sayfaDosyalari as $dosya) {
        $yol = app_path('Http/Storefront/'.$dosya);

        if (! file_exists($yol)) {
            continue;
        }

        $icerik = (string) file_get_contents($yol);

        /*
        | `user()` — parantezin içi BOŞ. `user('customer-web')` eşleşmiyor.
        */
        expect($icerik)->not->toMatch('/->user\(\s*\)/', $dosya.' guard yazmadan user() çağırıyor');
    }
});
