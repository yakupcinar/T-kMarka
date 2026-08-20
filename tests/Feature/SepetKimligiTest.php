<?php

use App\Http\Storefront\CartToken;

/*
| Misafir sepetinin kimliği TEK KAPIDAN okunur. (4B)
|
| ★ NEDEN YAPISAL TEST: 4A'da çerez desteği eklendi ama YALNIZCA
| CartController'a. Üç yer başlığı doğrudan okumaya devam etti ve sonuçları
| SESSİZDİ:
|
|   CouponController   tarayıcıdan kupon → "sepet bulunamadı"
|   CheckoutController tarayıcıdan ödeme → "sepet bulunamadı"
|   AuthController     giriş yapınca misafir sepeti BİRLEŞMİYOR → SEPET GİDER
|
| Hiçbiri hata vermiyordu; hepsi "sepetin yok" diyordu.
|
| ⚠️ Yorum yetmiyor — 3C'de aynı ders çıkmıştı: karar 1A.2'de yazılıydı ve
| yine de unutuldu. Kuralı ÖLÇEN bir test gerekiyor.
*/

it('★ sepet kimligini SADECE CartToken okuyor', function () {
    $kok = app_path('Http');

    $yakalananlar = [];

    $dosyalar = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok));

    foreach ($dosyalar as $dosya) {
        if (! $dosya->isFile() || $dosya->getExtension() !== 'php') {
            continue;
        }

        $yol = $dosya->getPathname();

        // Kapının kendisi hariç.
        if (str_ends_with($yol, 'CartToken.php')) {
            continue;
        }

        $satirlar = file($yol);

        if ($satirlar === false) {
            continue;
        }

        foreach ($satirlar as $no => $satir) {
            if (! str_contains($satir, CartToken::BASLIK)) {
                continue;
            }

            /*
            | ⚠️ YORUM SATIRLARI SAYILMIYOR. Sayılsaydı test, başlığı
            | AÇIKLAYAN her yorumda düşerdi ve doğru davranışı belgelemek
            | testi kırmak anlamına gelirdi — insanlar da yorumu silerdi.
            */
            $kirpik = ltrim($satir);

            if (str_starts_with($kirpik, '*') || str_starts_with($kirpik, '//') || str_starts_with($kirpik, '|')) {
                continue;
            }

            $yakalananlar[] = basename($yol).':'.($no + 1).' → '.trim($satir);
        }
    }

    expect($yakalananlar)->toBe([], "Başlık doğrudan okunuyor:\n".implode("\n", $yakalananlar));
});

/*
| SEPETİ ÇÖZEN TEK YOL [CartResolver]. (4.5J)
|
| ★ Yukarıdaki testin ikinci yarısı: kimliği okumak bir şey, o kimlikten
| SEPETİ ÇÖZMEK başka bir şey. `StorefrontViewData` (üst bardaki rozet)
| kendi yolunu açmıştı — `misafirSepetiBul()` çağırıyordu.
|
| ⚠️ Bedeli iki yönlüydü ve ikisi de SESSİZ:
|
|   giriş yapmış müşterinin dolu sepeti → rozet HİÇ ÇIKMIYOR
|   bayat misafir çerezi duruyorsa      → rozet DOLU, sepet sayfası BOŞ
|
| İkincisi gerçek kullanımda bildirildi: "sayaç 2 gösteriyor ama içine
| girince boş… sayı 2'de sabit kaldı."
*/
it('★★★ SEPETI GOSTEREN sayfalar CartResolver kullaniyor', function () {
    /*
    | ⚠️ KAPSAM DAR ve bilerek: kural "sepeti EKRANDA GÖSTEREN sayfa
    | kendi çözüm yolunu açmasın". Geniş tarama meşru kullanımı da
    | yakalıyordu:
    |
    |   AccountPageController → GİRİŞTE misafir sepetini birleştiriyor;
    |     oturum daha açılmadan misafir token'ını okumak ZORUNDA (1C-K5)
    |   api/* controller'ları → kimlik sanctum token'ında, `CartResolver`
    |     sayfa katmanı için yazıldı (4.5I'deki guard ayrımının aynısı)
    |
    | ⚠️ Yorumlar SAYILMIYOR: eşleşme çağrının kendisinde (`->metot(`).
    | İlk yazılışında yorum metni de yakalanıyordu — test kendi
    | belgelemesini "ihlal" sayıyordu.
    */
    $sayfalar = [
        'CartPageController.php',
        'CheckoutPageController.php',
        'StorefrontViewData.php',
    ];

    foreach ($sayfalar as $dosya) {
        $icerik = (string) file_get_contents(app_path('Http/Storefront/'.$dosya));

        expect($icerik)->not->toMatch('/->\s*misafirSepetiBul\s*\(/', $dosya.' sepeti CartResolver dışında çözüyor')
            ->and($icerik)->not->toMatch('/->\s*musteriSepeti\s*\(/', $dosya.' sepeti CartResolver dışında çözüyor');
    }
});
