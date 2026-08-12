<?php

declare(strict_types=1);

use App\Http\Panel\AuthController as PanelAuth;
use App\Http\Panel\CategoryController;
use App\Http\Panel\LegalController;
use App\Http\Panel\OptionController;
use App\Http\Panel\OrderController;
use App\Http\Panel\ProductController;
use App\Http\Panel\RoleController;
use App\Http\Panel\SettingsController;
use App\Http\Panel\StaffController;
use App\Http\Panel\StoreController;
use App\Http\Storefront\AddressController;
use App\Http\Storefront\AuthController as VitrinAuth;
use App\Http\Storefront\CartController;
use App\Http\Storefront\CatalogController;
use App\Http\Storefront\CheckoutController as VitrinCheckout;
use App\Http\Storefront\LegalController as VitrinLegal;
use App\Http\Storefront\PaymentController;
use App\Http\Storefront\PaymentReturnController;
use App\Http\Storefront\PaymentWebhookController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| MARKA rotaları — yalnızca markanın kendi alan adında geçerli
|--------------------------------------------------------------------------
|
| Merkez rotaları routes/web.php'de (kontrol düzlemi).
|
| Middleware zinciri:
|   api                             oturumsuz + CSRF yok. 'web' kullanılsaydı
|                                   token istemcisi CSRF üretemediği için her
|                                   POST kırılırdı.
|   InitializeTenancyByDomain       KAPI GÖREVLİSİ: host → domains → search_path
|   PreventAccessFromCentralDomains bu rotalara merkez adresten girilemez
|
| Rota EŞLEŞMESİ ile MIDDLEWARE ayrı iki aşama: burada yalnızca bağlantı
| kuruluyor; kiracı çözümlemesi middleware çalışınca oluyor.
*/

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // Markanın ayakta olduğunu görmek için — vitrin Faz 4'te gelecek (M-3).
    Route::get('/', fn () => response()->json([
        'tenant' => tenant('id'),
        'message' => 'Marka ayakta. Vitrin Faz 4te.',
    ]));

    /*
    | ÖDEME BİLDİRİMİ (1E.4) — sağlayıcının sunucusu çağırıyor.
    |
    | ⚠️ `api` ÖNEKİ YOK, `magaza-acik` KAPISI YOK, kimlik doğrulaması YOK.
    | Üçü de bilinçli:
    |
    |   önek yok      sağlayıcı panelinde yazılı adres; kısa ve sabit kalmalı
    |   kapı yok      marka mağazasını kapatınca çoktan başlamış ödemelerin
    |                 bildirimi 503 alırdı — para çekilmiş, sipariş pending
    |   kimlik yok    sağlayıcı bizim token'ımızı bilmiyor; tek koruma İMZA
    |
    | ⚠️ Kiracı ALAN ADINDAN çözülüyor. Yanlış şemaya yazılan tahsilat
    | hata vermez — A markasının parası B'nin defterinde görünür (0.5).
    */
    Route::post('/webhooks/payment', [PaymentWebhookController::class, 'store']);

    /*
    | ÖDEME DÖNÜŞÜ (1E.5) — müşterinin bankadan geri geldiği ekran.
    |
    | ⚠️ HİÇBİR ŞEY YAZMIYOR (1E-K1). Tarayıcı dönüşü ödeme kanıtı değil;
    | müşteri o ekrana hiç ulaşmayabilir, ya da adres çubuğuna kendisi
    | `?status=success` yazabilir. Gerçek webhook'tan geliyor.
    |
    | ⚠️ GET ve POST birlikte: sağlayıcılar dönüşü ikisinden biriyle
    | yapıyor (iyzico POST eder). Tek yöntem tanımlansaydı gerçek
    | sağlayıcı takıldığı gün müşteri 405 ekranı görürdü.
    |
    | ⚠️ `magaza-acik` DIŞINDA: mağaza kapansa bile bankadan dönen
    | müşteri ne olduğunu görebilmeli.
    */
    Route::match(['get', 'post'], PaymentController::DONUS_YOLU, [PaymentReturnController::class, 'show']);

    /*
    | VİTRİN — markanın müşterisi
    */
    Route::prefix('api')->group(function () {

        /*
        | KATALOG — herkese açık, kimlik doğrulama YOK.
        |
        | ⚠️ `magaza-acik` kapısı İLK KEZ gerçek bir rotada: mağaza
        | kapalıysa 503 + Retry-After (1A.4'te yazıldı, burada bağlandı).
        | Panel bu kapının DIŞINDA — marka mağazasını kapatınca kendini de
        | dışarıda bırakmasın.
        |
        | ⚠️ Sorgular ProductQuery'den geçiyor: maliyet ve taslak sızıntısı
        | ikisi de sessiz olurdu (1B-K10).
        */
        Route::middleware('magaza-acik')->group(function () {
            Route::get('/products', [CatalogController::class, 'index']);
            Route::get('/products/{slug}', [CatalogController::class, 'show']);
            Route::get('/categories', [CatalogController::class, 'categories']);

            /*
            | SEPET — kimlik doğrulama İSTEĞE BAĞLI.
            |
            | ⚠️ `auth:customer` YOK: misafir sepeti var (M-1). Kimin
            | sepeti olduğu controller'da çözülüyor — giriş yapmışsa
            | müşteri sepeti, yapmamışsa X-Cart-Token başlığındaki misafir
            | sepeti (1C-K1).
            |
            | ⚠️ Satır adresi VARYANT uuid'si ile: sepet satırının kendi
            | kimliğini dışarı vermeye gerek yok, müşteri zaten hangi
            | varyantı değiştirdiğini biliyor.
            */
            Route::get('/cart', [CartController::class, 'show']);
            Route::post('/cart/items', [CartController::class, 'addItem']);
            Route::put('/cart/items/{variant}', [CartController::class, 'updateItem']);
            Route::delete('/cart/items/{variant}', [CartController::class, 'removeItem']);

            /*
            | SİPARİŞ OLUŞTURMA — misafir de verebiliyor (M-1).
            |
            | ⚠️ ÖDEME BURADA YOK: sipariş `pending` doğuyor, ödeme 1E'de
            | gelecek. Ödemenin transaction dışında kalması bilinçli —
            | dış servis yavaşlarsa satırlar dakikalarca kilitli kalır.
            */
            /*
            | YASAL METİNLER — ödeme adımının ÖN KOŞULU.
            |
            | ⚠️ `/checkout` müşteriden `legal_version_id` istiyor; sürüm
            | kimliğini veren tek yer burası. Uç 1D.6'da eklendi: yokken
            | sipariş vermek dışarıdan imkânsızdı ve tek bir test bile
            | kırılmıyordu (testler kimliği modelden okuyordu).
            */
            Route::get('/legal', [VitrinLegal::class, 'index']);
            Route::get('/legal/{tur}', [VitrinLegal::class, 'show']);

            Route::post('/checkout', [VitrinCheckout::class, 'store']);

            /*
            | ÖDEME BAŞLATMA (1E.3).
            |
            | ⚠️ Adres SİPARİŞ NUMARASI değil UUID taşıyor. Numara
            | tahmin edilebilir (TM-2026-000123, 1D-K4) ve bu bilinçli
            | bir karardı — ama o karar "görüntülemek kimlik doğrulaması
            | ister" varsayımına dayanıyordu. Misafir siparişinde kimlik
            | doğrulaması yok; numara kullanılsaydı ardışık numara
            | deneyen biri başkasının siparişinin ödemesini başlatabilirdi.
            |
            | ⚠️ UUID müşteriye ZATEN /api/checkout cevabında veriliyor —
            | 1D.6'nın kuralı: isteğe giren her kimlik bir önceki uçtan
            | gelmeli.
            */
            Route::post('/orders/{siparis}/pay', [PaymentController::class, 'store']);
        });

        // Hız sınırları AppServiceProvider'da tanımlı.
        // M-4.1/3: Caddy'de hız sınırlaması yok, koruma bilerek burada.
        Route::post('/register', [VitrinAuth::class, 'register'])->middleware('throttle:kayit');
        Route::post('/login', [VitrinAuth::class, 'login'])->middleware('throttle:giris');

        // auth:customer → yalnızca CUSTOMER token'ı geçer.
        // Personel token'ı buraya giremez (1A.0'da kanıtlandı).
        Route::middleware('auth:customer')->group(function () {
            Route::post('/logout', [VitrinAuth::class, 'logout']);
            Route::get('/me', [VitrinAuth::class, 'me']);

            /*
            | ADRES DEFTERİ.
            |
            | ⚠️ Sahiplik kontrolü burada DEĞİL, controller'da: her sorgu
            | müşterinin ilişkisi üzerinden açılıyor, başkasının adresi
            | sonuç kümesine hiç girmiyor. `{adres}` bir MODEL değil düz
            | uuid — örtük rota bağlaması kullanılsaydı başkasının satırı
            | belleğe gelirdi.
            */
            Route::get('/addresses', [AddressController::class, 'index']);
            Route::post('/addresses', [AddressController::class, 'store']);
            Route::put('/addresses/{adres}', [AddressController::class, 'update']);
            Route::delete('/addresses/{adres}', [AddressController::class, 'destroy']);
        });
    });

    /*
    | PANEL — markanın personeli
    |
    | ⚠️ KAYIT UCU YOK ve olmayacak. Personel davetle gelir (1A.3).
    | Olsaydı markanın alan adını bilen herkes panele hesap açardı.
    */
    Route::prefix('panel')->group(function () {

        Route::post('/login', [PanelAuth::class, 'login'])->middleware('throttle:giris');

        // auth:staff → yalnızca STAFF token'ı geçer.
        // Müşteri token'ı buraya giremez (1A.0'da kanıtlandı).
        Route::middleware('auth:staff')->group(function () {
            Route::post('/logout', [PanelAuth::class, 'logout']);
            Route::get('/me', [PanelAuth::class, 'me']);

            /*
            | PERSONEL YÖNETİMİ — `staff.manage` izni şart.
            | Bu izin varsayılan rollerin hiçbirinde yok; pratikte yalnızca
            | sahip erişebiliyor. Personel davet etmek yetki yükseltmeye en
            | yakın işlem olduğu için bilerek böyle (1A.3).
            */
            Route::middleware('izin:staff.manage')->group(function () {
                Route::get('/staff', [StaffController::class, 'index']);
                Route::post('/staff', [StaffController::class, 'store']);
                Route::delete('/staff/{user}', [StaffController::class, 'destroy']);
            });

            /*
            | SİPARİŞLER — `order.view`.
            |
            | Bu izin de 1A.3'ten beri boştu; ilk kez burada kapı bekliyor.
            */
            Route::middleware('izin:order.view')->group(function () {
                Route::get('/orders', [OrderController::class, 'index']);
                Route::get('/orders/{order}', [OrderController::class, 'show']);
            });

            /*
            | SEVKİYAT — `order.fulfill`. AYRI izin, bilerek.
            |
            | "Sipariş & Destek" rolünde `order.view` ve `order.fulfill` var
            | ama `order.refund` YOK — depocu örneği (1A.3): siparişi görür,
            | kargoya verir, para iadesi yapamaz.
            */
            Route::middleware('izin:order.fulfill')->group(function () {
                Route::post('/orders/{order}/fulfillments', [OrderController::class, 'storeFulfillment']);
                Route::post('/orders/{order}/fulfillments/{fulfillment}/ship', [OrderController::class, 'ship']);
                Route::post('/orders/{order}/fulfillments/{fulfillment}/deliver', [OrderController::class, 'deliver']);
                Route::delete('/orders/{order}/fulfillments/{fulfillment}', [OrderController::class, 'cancelFulfillment']);
            });

            /*
            | KATALOG — `product.write`.
            |
            | Bu izin de 1A.3'ten beri boştu; ilk kez burada kapı bekliyor.
            | Katalog rolünde var, yani ürün ekleyen personel eksen de
            | tanımlayabiliyor — eksen katalogun yapısı.
            |
            | Eksenler MAĞAZA seviyesinde (1B-K3): "Renk" bir kez tanımlanır.
            | Değer uçları eksenin ALTINDA çünkü değer tek başına anlamsız;
            | ayrıca adres, değerin hangi eksene ait olduğunu da doğruluyor.
            */
            Route::middleware('izin:product.write')->group(function () {
                Route::get('/options', [OptionController::class, 'index']);
                Route::post('/options', [OptionController::class, 'store']);
                Route::put('/options/{option}', [OptionController::class, 'update']);
                Route::delete('/options/{option}', [OptionController::class, 'destroy']);

                /*
                | ÜRÜN ve VARYANTLAR.
                |
                | ⚠️ Durum değişikliği ve eksen ayarı AYRI uçlarda: ikisinin
                | de kendi şartı var (satışa almak varyant ister, eksen
                | değiştirmek varyantsızlık ister). Genel `update` içine
                | konsaydı basit bir başlık düzeltmesi bu kuralları
                | tetikleyebilirdi.
                */
                Route::get('/products', [ProductController::class, 'index']);
                Route::post('/products', [ProductController::class, 'store']);
                Route::get('/products/{product}', [ProductController::class, 'show']);
                Route::put('/products/{product}', [ProductController::class, 'update']);
                Route::delete('/products/{product}', [ProductController::class, 'destroy']);

                Route::put('/products/{product}/options', [ProductController::class, 'setOptions']);
                Route::post('/products/{product}/status', [ProductController::class, 'setStatus']);

                Route::post('/products/{product}/images', [ProductController::class, 'storeImage']);
                Route::post('/products/{product}/images/reorder', [ProductController::class, 'reorderImages']);
                Route::delete('/products/{product}/images/{image}', [ProductController::class, 'destroyImage']);

                Route::post('/products/{product}/variants', [ProductController::class, 'storeVariant']);
                Route::post('/products/{product}/variants/generate', [ProductController::class, 'generateVariants']);
                Route::put('/products/{product}/variants/{variant}', [ProductController::class, 'updateVariant']);
                Route::delete('/products/{product}/variants/{variant}', [ProductController::class, 'destroyVariant']);

                /*
                | KATEGORİ AĞACI.
                |
                | ⚠️ Taşıma AYRI uçta: kendi kuralı var (döngü engeli) ve
                | alt ağacın tamamını yeniden yazıyor. Ad değiştirmekle aynı
                | uçta olsaydı, yanlışlıkla gönderilen bir parent_uuid koca
                | bir dalı taşırdı.
                */
                Route::get('/categories', [CategoryController::class, 'index']);
                Route::post('/categories', [CategoryController::class, 'store']);
                Route::put('/categories/{category}', [CategoryController::class, 'update']);
                Route::post('/categories/{category}/move', [CategoryController::class, 'move']);
                Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

                Route::post('/options/{option}/values', [OptionController::class, 'storeValue']);
                Route::put('/options/{option}/values/{deger}', [OptionController::class, 'updateValue']);
                Route::delete('/options/{option}/values/{deger}', [OptionController::class, 'destroyValue']);
            });

            /*
            | MAĞAZA AYARLARI, YASAL METİNLER, YAYIN DURUMU — `settings.write`.
            |
            | Bu izin 1A.3'te tanımlanmıştı ama hiçbir yeri korumuyordu;
            | ilk kez burada gerçek bir kapı bekliyor.
            |
            | Üçü de tek izin altında: "mağazayı kapatma" ile "kargo ücretini
            | değiştirme" ayrı izinler olsun mu diye tartışıldı, şimdilik
            | ayrılmadı (1A.4). Ayrım gerekirse `store.publish` eklenecek.
            */
            /*
            | ROL YÖNETİMİ — `sahip` kapısı, izin DEĞİL.
            |
            | `role.manage` diye bir izin olsaydı ona sahip kişi kendine
            | `settings.write` içeren bir rol kurup atardı — yetki
            | yükseltme. "Yetki dağıtan işlem, yetkiyle dağıtılmaz."
            |
            | Marka kendi rolünü kurabiliyor çünkü katı rol listesi
            | güvenlik değil AŞIRI YETKİ üretir: "sadece finans" rolü
            | yoksa marka muhasebecisine Yönetici verir.
            */
            Route::middleware('sahip')->group(function () {
                Route::get('/roles', [RoleController::class, 'index']);
                Route::post('/roles', [RoleController::class, 'store']);
                Route::put('/roles/{rol}', [RoleController::class, 'update']);
                Route::delete('/roles/{rol}', [RoleController::class, 'destroy']);
            });

            Route::middleware('izin:settings.write')->group(function () {
                Route::get('/settings', [SettingsController::class, 'index']);
                Route::put('/settings', [SettingsController::class, 'update']);

                // {tur} enum'a bağlanıyor: geçersiz tür rotaya HİÇ girmiyor,
                // controller'a gelmeden 404 oluyor.
                Route::get('/legal', [LegalController::class, 'index']);
                Route::put('/legal/{tur}', [LegalController::class, 'update']);
                Route::post('/legal/{tur}/publish', [LegalController::class, 'publish']);

                Route::get('/store/readiness', [StoreController::class, 'readiness']);
                Route::post('/store/publish', [StoreController::class, 'publish']);
                Route::post('/store/close', [StoreController::class, 'close']);
            });
        });
    });
});
