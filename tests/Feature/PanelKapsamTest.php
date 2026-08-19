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
