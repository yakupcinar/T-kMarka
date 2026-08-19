<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSessionTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Panel\AuthController as PanelAuth;
use App\Http\Panel\CatalogSettingsPageController;
use App\Http\Panel\CategoryController;
use App\Http\Panel\CollectionController;
use App\Http\Panel\CollectionPageController;
use App\Http\Panel\DashboardController;
use App\Http\Panel\DomainController;
use App\Http\Panel\DomainPageController;
use App\Http\Panel\LegalController;
use App\Http\Panel\LegalPageController as PanelLegalSayfa;
use App\Http\Panel\OptionController;
use App\Http\Panel\OrderController;
use App\Http\Panel\OrderPageController;
use App\Http\Panel\PanelAuthPageController;
use App\Http\Panel\PaymentSettingsController;
use App\Http\Panel\PaymentSettingsPageController;
use App\Http\Panel\ProductController;
use App\Http\Panel\ProductPageController as PanelUrunSayfasi;
use App\Http\Panel\ReturnController as PanelIade;
use App\Http\Panel\ReturnPageController;
use App\Http\Panel\ReviewController;
use App\Http\Panel\RoleController;
use App\Http\Panel\SettingsController;
use App\Http\Panel\StaffController;
use App\Http\Panel\StaffPageController;
use App\Http\Panel\StoreController;
use App\Http\Panel\StorePageController;
use App\Http\Panel\ThemePageController;
use App\Http\Storefront\AccountPageController;
use App\Http\Storefront\AddressController;
use App\Http\Storefront\AuthController as VitrinAuth;
use App\Http\Storefront\CartController;
use App\Http\Storefront\CartPageController;
use App\Http\Storefront\CatalogController;
use App\Http\Storefront\CheckoutController as VitrinCheckout;
use App\Http\Storefront\CheckoutPageController;
use App\Http\Storefront\CollectionController as StorefrontCollectionController;
use App\Http\Storefront\CouponController;
use App\Http\Storefront\HomeController;
use App\Http\Storefront\LegalController as VitrinLegal;
use App\Http\Storefront\LegalPageController;
use App\Http\Storefront\PaymentController;
use App\Http\Storefront\PaymentReturnController;
use App\Http\Storefront\PaymentWebhookController;
use App\Http\Storefront\PrivacyController;
use App\Http\Storefront\ProductPageController;
use App\Http\Storefront\ReturnController as VitrinIade;
use App\Http\Storefront\ReviewController as StorefrontReviewController;
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
    | KVKK DOĞRULAMA BAĞLANTISI (2G-K3).
    |
    | ⚠️ `magaza-acik` DIŞINDA ve `api` önekinden AYRI: bağlantı postadan
    | tıklanıyor, mağaza kapalıyken de çalışmalı. Yasal bir hak, mağazanın
    | açık olmasına bağlanamaz.
    */
    Route::get(PrivacyController::DONUS_YOLU.'/{token}', [PrivacyController::class, 'confirm']);

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
            | KOLEKSİYONLAR (2D). Kurallı olanın üyeleri sorgu anında
            | hesaplanıyor — bu uçtan bakıldığında hiçbir fark yok, fark
            | fiyat değişince ortaya çıkıyor: liste kendiliğinden güncel.
            */
            /*
            | YORUMLAR (2E). Okuma herkese açık, YAZMA `auth:customer`
            | arkasında — aşağıdaki müşteri bloğunda.
            |
            | ⚠️ Misafir yorum yazamıyor: kimlik yok, "bu kişi gerçekten
            | aldı mı" sorusu cevaplanamaz. Bu bir SINIR, gizlenmiyor.
            */
            Route::get('/products/{slug}/reviews', [StorefrontReviewController::class, 'index']);

            Route::get('/collections', [StorefrontCollectionController::class, 'index']);
            Route::get('/collections/{slug}', [StorefrontCollectionController::class, 'show']);

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
            | KUPON (2A) — uygulamak KOTA HARCAMIYOR.
            |
            | ⚠️ Kota sipariş oluşurken harcanıyor; yoksa kuponu deneyip
            | vazgeçen her müşteri kampanyadan bir kullanım yerdi.
            */
            Route::post('/cart/coupon', [CouponController::class, 'store']);
            Route::delete('/cart/coupon', [CouponController::class, 'destroy']);

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

            /*
            | KVKK VERİ TALEPLERİ (2G).
            |
            | ⚠️ Kimlik doğrulaması İSTEĞE BAĞLI: misafir de talep
            | edebilmeli (M-1). Kimlik kanıtı e-posta + sipariş numarası;
            | asıl koruma ise doğrulama postası.
            */
            Route::post('/privacy/requests', [PrivacyController::class, 'store']);

            /*
            | İADE TALEBİ (2B) — müşteri yalnızca TALEP açıyor.
            |
            | ⚠️ Onay, teslim alma ve para iadesi markanın işi (2B-K1).
            | `GET` ucu "hangi satır ne zamana kadar iade edilebilir"
            | sorusunu cevaplıyor: müşteri reddedilince şaşırmasın.
            */
            Route::get('/orders/{siparis}/returns', [VitrinIade::class, 'show']);
            Route::post('/orders/{siparis}/returns', [VitrinIade::class, 'store']);
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
            | YORUM YAZMA (2E-K1). Satın alma kanıtı [PurchaseProof]'ta —
            | burada değil: kontrol HTTP dışından da atlanmamalı.
            */
            Route::post('/products/{slug}/reviews', [StorefrontReviewController::class, 'store']);

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

        /*
        | ★ ÇIKIŞ ve KİMLİK — askıda DA açık.
        |
        | ⚠️ Bilerek `marka-aktif`'in DIŞINDA: askıdaki markanın yöneticisi
        | çıkış yapabilmeli (yoksa token'ı elinde kalırdı) ve hesabının
        | durumunu görebilmeli.
        */
        Route::middleware('auth:staff')->group(function () {
            Route::post('/logout', [PanelAuth::class, 'logout']);
            Route::get('/me', [PanelAuth::class, 'me']);
        });

        /*
        | auth:staff  → yalnızca STAFF token'ı geçer.
        |               Müşteri token'ı buraya giremez (1A.0'da kanıtlandı).
        |
        | marka-aktif → askıya alınmış markanın paneli KAPALI (3C).
        |
        | ⚠️ Vitrin AÇIK kalıyor: müşteri siparişini takip edebilsin, iade
        | açabilsin. Askı markayı vurmalı, markanın müşterilerini değil
        | (4 numaralı karar).
        */
        Route::middleware(['auth:staff', 'marka-aktif'])->group(function () {

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

                /*
                | KOLEKSİYONLAR.
                |
                | ⚠️ Üyelik uçları (`products/…`) AYRI ve yalnızca manuel
                | koleksiyonda çalışıyor; kurallıda `CollectionService`
                | reddediyor. Aynı uçtan yönetilseydi elle eklenen ürün,
                | kural onu dışlasa bile listede kalırdı.
                |
                | ⚠️ `GET /products` kuralın ŞU AN ne getirdiğini gösteriyor
                | — marka kuralını kaydetmeden sonucunu görebilmeli.
                */
                /*
                | YORUM MODERASYONU (2E-K2).
                |
                | ⚠️ `product.write` arkasında: yorum ürünün vitrin
                | içeriğidir. Ayrı izin açılsaydı üç sistem rolünün
                | hiçbirinde bulunmaz, pratikte yalnızca sahip yapabilirdi.
                */
                Route::get('/reviews', [ReviewController::class, 'index']);
                Route::post('/reviews/{review}/approve', [ReviewController::class, 'approve']);
                Route::post('/reviews/{review}/reject', [ReviewController::class, 'reject']);

                Route::get('/collections', [CollectionController::class, 'index']);
                Route::post('/collections', [CollectionController::class, 'store']);
                Route::put('/collections/{collection}', [CollectionController::class, 'update']);
                Route::delete('/collections/{collection}', [CollectionController::class, 'destroy']);

                Route::get('/collections/{collection}/products', [CollectionController::class, 'products']);
                Route::post('/collections/{collection}/products', [CollectionController::class, 'attach']);
                Route::post('/collections/{collection}/products/reorder', [CollectionController::class, 'reorder']);
                Route::delete('/collections/{collection}/products/{urun}', [CollectionController::class, 'detach']);

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

            /*
            | İADE YÖNETİMİ (2B) — `order.refund` izni ilk kez kapı bekliyor.
            |
            | ⚠️ `order.view` YETMİYOR: para geri gönderen işlem, siparişi
            | görebilen herkese açık olamaz.
            */
            Route::middleware('izin:order.refund')->group(function () {
                Route::get('/returns', [PanelIade::class, 'index']);
                Route::get('/returns/{return}', [PanelIade::class, 'show']);
                Route::post('/returns/{return}/approve', [PanelIade::class, 'approve']);
                Route::post('/returns/{return}/reject', [PanelIade::class, 'reject']);
                Route::post('/returns/{return}/receive', [PanelIade::class, 'receive']);
                Route::post('/returns/{return}/refund', [PanelIade::class, 'refund']);
            });

            Route::middleware('izin:settings.write')->group(function () {

                /*
                | ★ ÖZEL ALAN ADI (3H).
                |
                | ⚠️ `settings.write` arkasında: alan adı mağazanın kimliği,
                | katalog değil. Yanlış bağlanan bir alan adı mağazayı
                | erişilemez yapabilir.
                |
                | ⚠️ `{domain}` düz metin, MODEL DEĞİL: örtük rota bağlaması
                | kullanılsaydı başka markanın alan adı belleğe gelirdi
                | (adres defterinde aynı karar var, 1A.5).
                */
                Route::get('/domains', [DomainController::class, 'index']);
                Route::post('/domains', [DomainController::class, 'store']);
                Route::post('/domains/{domain}/verify', [DomainController::class, 'verify']);
                Route::delete('/domains/{domain}', [DomainController::class, 'destroy']);

                /*
                | ÖDEME SAĞLAYICI AYARLARI (1E-K11) — genel ayar ucundan AYRI.
                |
                | ⚠️ Genel uç serbest biçimli: marka istediği anahtarı
                | yazabiliyor. Burada anahtarlar SAĞLAYICININ BİLDİRDİĞİ
                | listeyle sınırlı, çünkü `iyzico_api` gibi bir yazım hatası
                | sessizce kabul edilirse ödeme "ayarlandı" görünür ve ilk
                | gerçek müşteride patlar.
                */
                Route::get('/payment', [PaymentSettingsController::class, 'index']);
                Route::put('/payment', [PaymentSettingsController::class, 'update']);

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

/*
|--------------------------------------------------------------------------
| VİTRİN SAYFALARI — insanın gördüğü yüz (4A)
|--------------------------------------------------------------------------
|
| ⚠️ `web` GRUBU, `api` DEĞİL — ve bu 3C'deki dersin TERSİ tarafı.
| 3C'de merkez API'si yanlışlıkla `web`'e yazılmıştı ve CSRF her POST'u
| kırıyordu. Burada `web` BİLEREK seçiliyor:
|
|   oturum   müşteri girişi ve form akışları buna dayanacak (4B)
|   çerez    misafir sepetinin kimliği (CartToken) — başlık taşınamıyor
|   CSRF     form gönderimi için İSTENİYOR
|
| ⚠️ `ForceJson` bu gruba TAKILMIYOR (4A'da `api`'ye daraltıldı). Takılı
| kalsaydı her sayfa "istemci JSON istiyor" sayılır, form hataları geri
| yönlendirme yerine 422 JSON dönerdi.
|
| ⚠️ `magaza-acik` VAR: kapalı mağazanın vitrini açık kalamaz. Middleware
| tarayıcıya artık HTML dönüyor (503 + Retry-After).
*/
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,

    /*
    | ★ OTURUM AÇILDIĞI MARKAYA BAĞLI (4H · 4.5D'de vitrine de eklendi).
    |
    | ⚠️ 4H'de bu middleware YALNIZCA PANEL grubuna takılmıştı. 4.5D'de
    | müşteri oturumu gelince guard listesi genişletildi ama bu YETMEDİ:
    | middleware vitrin grubunda hiç çalışmadığı için A markasının müşteri
    | oturumu B'nin hesabını AÇMAYA DEVAM EDİYORDU — ve test bunu gösterdi.
    |
    | Ders: korumayı genişletmek, onu DOĞRU YERE TAKMAK demek değil.
    */
    EnsureSessionTenant::class,

    'magaza-acik',
])->group(function () {

    Route::get('/', HomeController::class)->name('vitrin.anasayfa');
    Route::get('/urun/{slug}', ProductPageController::class)->name('vitrin.urun');

    /*
    | SEPET SAYFASI VE İŞLEMLERİ
    |
    | ⚠️ Hepsi POST → Redirect → GET (PRG). Doğrudan HTML dönseydi
    | müşterinin sayfayı yenilemesi aynı ürünü tekrar sepete eklerdi.
    |
    | ⚠️ CSRF bu grupta AÇIK ve İSTENİYOR (4-K4): formlar tarayıcıdan
    | geliyor. 3C'de aynı koruma yanlış yerdeydi ve API'yi kırıyordu;
    | burası doğru yeri.
    */
    Route::get('/sepet', [CartPageController::class, 'show'])->name('vitrin.sepet');
    Route::post('/sepet/ekle', [CartPageController::class, 'ekle'])->name('vitrin.sepet.ekle');
    Route::post('/sepet/guncelle', [CartPageController::class, 'guncelle'])->name('vitrin.sepet.guncelle');
    Route::post('/sepet/sil', [CartPageController::class, 'sil'])->name('vitrin.sepet.sil');
    Route::post('/sepet/kupon', [CartPageController::class, 'kupon'])->name('vitrin.sepet.kupon');

    /*
    | ÖDEME SAYFASI
    |
    | ⚠️ Ödeme DÖNÜŞÜ burada DEĞİL: o `api` grubunda kalıyor çünkü
    | sağlayıcı POST ediyor ve CSRF üretemez (rota zaten oradaydı).
    | Aynı uç artık tarayıcıya HTML, API'ye JSON dönüyor.
    */
    /*
    | MÜŞTERİ HESABI (4.5D)
    |
    | ⚠️ Kimlik OTURUMLA (`customer-web`), token'la değil: vitrin sunucuda
    | render ediliyor ve formlar JavaScript'siz çalışıyor (4B-K1).
    | `customer` (sanctum) guard'ı DURUYOR — mobil ve entegrasyonlar onu
    | kullanacak.
    |
    | ⚠️ Giriş/kayıt sayfaları KİMLİKSİZ erişilebilir olmak zorunda;
    | korumalı olanlar `auth:customer-web` arkasında.
    */
    Route::get('/giris', [AccountPageController::class, 'girisFormu'])->name('vitrin.giris');
    Route::post('/giris', [AccountPageController::class, 'giris']);
    Route::get('/kayit', [AccountPageController::class, 'kayitFormu'])->name('vitrin.kayit');
    Route::post('/kayit', [AccountPageController::class, 'kayit']);

    Route::middleware('auth:customer-web')->group(function () {
        Route::post('/cikis', [AccountPageController::class, 'cikis'])->name('vitrin.cikis');
        Route::get('/hesabim', [AccountPageController::class, 'hesap'])->name('vitrin.hesap');
        Route::get('/hesabim/siparis/{siparis:uuid}', [AccountPageController::class, 'siparis'])->name('vitrin.hesap.siparis');
        Route::get('/hesabim/adresler', [AccountPageController::class, 'adresler'])->name('vitrin.adresler');
        Route::post('/hesabim/adresler', [AccountPageController::class, 'adresEkle'])->name('vitrin.adres.ekle');
        Route::delete('/hesabim/adresler/{adres}', [AccountPageController::class, 'adresSil'])->name('vitrin.adres.sil');
    });

    Route::get('/odeme', [CheckoutPageController::class, 'form'])->name('vitrin.odeme');
    Route::post('/odeme', [CheckoutPageController::class, 'gonder'])->name('vitrin.odeme.gonder');

    /*
    | GÖMÜLÜ ÖDEME ADIMI (4.5-K1) — kart formu iframe içinde.
    |
    | ⚠️ Sipariş adrese  ile giriyor; sahipliği controller'da
    | doğrulanıyor (1A.5 · 1E'deki kuralın aynısı).
    */
    Route::get('/odeme/ode/{siparis:uuid}', [CheckoutPageController::class, 'ode'])->name('vitrin.ode');

});

/*
|--------------------------------------------------------------------------
| YASAL METİNLER (4.5A) — `magaza-acik` KAPISININ DIŞINDA
|--------------------------------------------------------------------------
|
| ★ Emsal 2G'de kuruldu: KVKK doğrulama bağlantısı da bu kapının dışında
| ve gerekçesi aynen şuydu — *"Yasal bir hak, mağazanın açık olmasına
| bağlanamaz."*
|
| ⚠️ Mağaza kapalıyken de okunabilmeli:
|   · KVKK aydınlatma metni bir bilgilendirme yükümlülüğü, satış özelliği
|     değil.
|   · Sipariş vermiş bir müşteri, marka mağazasını kapatsa bile onayladığı
|     sözleşmeyi okuyabilmeli.
|
| ⚠️ İlk hâli `magaza-acik` içindeydi ve testte ortaya çıktı: yasal
| metinlerini henüz tamamlamamış (dolayısıyla mağazası kapalı) bir marka,
| yayınladığı metni bile gösteremiyordu.
*/
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('yasal')->group(function () {

    Route::get('/', [LegalPageController::class, 'index'])->name('vitrin.yasal.liste');
    Route::get('/{tur}', [LegalPageController::class, 'show'])->name('vitrin.yasal');

});

/*
|--------------------------------------------------------------------------
| PANEL SAYFALARI — markanın personelinin çalışma alanı (4C)
|--------------------------------------------------------------------------
|
| ★ 4-K1'İN UYGULAMASI: ayrım ALAN ADI + YOL ile.
|   marka alan adı + `/`         → vitrin   (müşteri)
|   marka alan adı + `/yonetim`  → panel    (personel)
|   merkez alan adı + `/yonetim` → kontrol düzlemi (biz, 4F)
|
| ⚠️ `magaza-acik` YOK: marka mağazasını kapatınca kendini panelin dışında
| bırakmamalı — mağazayı tekrar açmanın tek yolu burası.
|
| ⚠️ `web` grubu: oturum ve CSRF gerekiyor (4C-K3 · 4-K4).
*/
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,

    // ⚠️ Inertia middleware'i BU GRUBA takılı, global değil (4F'de
    // daraltıldı): kontrol düzleminin kendi yüzeyi var ve ikisi global
    // olsaydı kök görünümü sonuncusu belirlerdi.
    HandleInertiaRequests::class,

    /*
    | ★ OTURUM AÇILDIĞI MARKAYA BAĞLI (4H) — gerçek bir açığı kapatıyor:
    | A markasının oturum çerezi B'nin panelini açıyordu. Gerekçenin
    | tamamı [EnsureSessionTenant]'da.
    */
    EnsureSessionTenant::class,
])->prefix('yonetim')->group(function () {

    /*
    | ⚠️ `guest:staff-web` — GİRİŞ YAPMIŞ personel giriş formunu görmemeli.
    | Görseydi, ikinci kez giriş yapması oturumunu yeniler ve o sırada
    | doldurduğu formlar sessizce kaybolurdu.
    */
    Route::middleware('guest:staff-web')->group(function () {
        Route::get('/giris', [PanelAuthPageController::class, 'form'])->name('panel.giris');

        Route::post('/giris', [PanelAuthPageController::class, 'giris'])
            ->middleware('throttle:giris')
            ->name('panel.giris.gonder');
    });

    Route::middleware(['auth:staff-web', 'marka-aktif'])->group(function () {
        Route::get('/', DashboardController::class)->name('panel.pano');
        Route::post('/cikis', [PanelAuthPageController::class, 'cikis'])->name('panel.cikis');

        /*
        | KATALOG (4D) — markanın ürün eklediği ekran.
        |
        | ★ 4C-K4'ÜN İKİNCİ YARISI BURADA ÖLÇÜLEBİLİR HÂLE GELİYOR.
        | Menüde "Ürünler" maddesi izne göre gizleniyordu ama o bir
        | KOLAYLIK; gerçek koruma burası. `product.write` izni olmayan
        | personel adresi elle yazsa da 403 alıyor.
        |
        | ⚠️ Panel API'siyle AYNI izin (`izin:product.write`): iki yüzeyin
        | farklı izin istemesi, birinden kapatılanın diğerinden açık
        | kalması demek olurdu.
        */
        Route::middleware('izin:product.write')->group(function () {
            Route::get('/urunler', [PanelUrunSayfasi::class, 'index'])->name('panel.urunler');
            Route::get('/urunler/yeni', [PanelUrunSayfasi::class, 'create'])->name('panel.urun.yeni');
            Route::post('/urunler', [PanelUrunSayfasi::class, 'store'])->name('panel.urun.olustur');

            /*
            | ⚠️ Bağlama `uuid` ile — otomatik artan `id` ile DEĞİL.
            | Sıralı kimlik adres çubuğunda görünseydi marka personeli
            | (ya da adresi gören herkes) kaç ürün olduğunu sayabilirdi;
            | ayrıca `id` tahmin edilebilir.
            */
            Route::get('/urunler/{urun:uuid}', [PanelUrunSayfasi::class, 'edit'])->name('panel.urun.duzenle');
            Route::put('/urunler/{urun:uuid}', [PanelUrunSayfasi::class, 'update'])->name('panel.urun.guncelle');
            Route::post('/urunler/{urun:uuid}/durum', [PanelUrunSayfasi::class, 'durum'])->name('panel.urun.durum');
            Route::delete('/urunler/{urun:uuid}', [PanelUrunSayfasi::class, 'destroy'])->name('panel.urun.sil');

            /*
            | ÜRÜN GÖRSELLERİ (4.5E) — uçları 1B'de vardı, ekranı yoktu:
            | ürünler görselsiz kalıyordu.
            */
            Route::post('/urunler/{urun:uuid}/gorseller', [PanelUrunSayfasi::class, 'gorselYukle'])->name('panel.gorsel.yukle');
            Route::delete('/urunler/{urun:uuid}/gorseller/{gorsel}', [PanelUrunSayfasi::class, 'gorselSil'])->name('panel.gorsel.sil');

            /*
            | KATALOG ALTYAPISI (4.5E): kategoriler ve varyant eksenleri.
            |
            | ⚠️ Tek ekranda ikisi birden — ikisi de ürün eklemeden ÖNCE
            | yapılan hazırlık işi.
            */
            Route::get('/katalog', [CatalogSettingsPageController::class, 'index'])->name('panel.katalog');
            Route::post('/katalog/kategoriler', [CatalogSettingsPageController::class, 'kategoriEkle'])->name('panel.kategori.ekle');
            Route::delete('/katalog/kategoriler/{kategori}', [CatalogSettingsPageController::class, 'kategoriSil'])->name('panel.kategori.sil');
            Route::post('/katalog/eksenler', [CatalogSettingsPageController::class, 'eksenEkle'])->name('panel.eksen.ekle');
            Route::delete('/katalog/eksenler/{eksen}', [CatalogSettingsPageController::class, 'eksenSil'])->name('panel.eksen.sil');
            Route::post('/katalog/eksenler/{eksen}/degerler', [CatalogSettingsPageController::class, 'degerEkle'])->name('panel.deger.ekle');
            Route::delete('/katalog/eksenler/{eksen}/degerler/{deger}', [CatalogSettingsPageController::class, 'degerSil'])->name('panel.deger.sil');

            /*
            | KOLEKSİYONLAR (4.5E) — 2D'nin ekranı.
            |
            | ⚠️ Kurallı koleksiyona ELLE ürün eklenemiyor: üyelik sorguyla
            | belirleniyor ve elle eklenen ürün bir sonraki sorguda
            | kaybolurdu. Kontrol controller'da, 422.
            */
            Route::get('/koleksiyonlar', [CollectionPageController::class, 'index'])->name('panel.koleksiyonlar');
            Route::post('/koleksiyonlar', [CollectionPageController::class, 'ekle'])->name('panel.koleksiyon.ekle');
            Route::get('/koleksiyonlar/{koleksiyon:uuid}', [CollectionPageController::class, 'goster'])->name('panel.koleksiyon');
            Route::delete('/koleksiyonlar/{koleksiyon:uuid}', [CollectionPageController::class, 'sil'])->name('panel.koleksiyon.sil');
            Route::post('/koleksiyonlar/{koleksiyon:uuid}/urunler', [CollectionPageController::class, 'urunEkle'])->name('panel.koleksiyon.urunekle');
            Route::delete('/koleksiyonlar/{koleksiyon:uuid}/urunler/{urun}', [CollectionPageController::class, 'urunCikar'])->name('panel.koleksiyon.uruncikar');

            Route::post('/urunler/{urun:uuid}/varyantlar', [PanelUrunSayfasi::class, 'varyantEkle'])->name('panel.varyant.ekle');
            /*
            | ⚠️ `withoutScopedBindings()` — ve bu BİLİNÇLİ.
            |
            | Laravel iç içe bağlamada çocuğu ebeveynin İLİŞKİSİNDEN
            | çözmeye çalışıyor: `{varyant:uuid}` için `Product::varyants()`
            | arıyor ve bulamayıp 500 veriyor (ilişkinin adı `variants`).
            |
            | Parametreyi `variant` diye adlandırıp paketin kapsamasına
            | bırakabilirdik. Bırakmadık: o zaman controller'daki açık
            | kontrol ÖLÜ savunma olur ve kimse onu ölçemezdi. Koruma
            | görünür ve test edilebilir olsun diye kapsama kapatıldı,
            | doğrulama [ProductPageController]'da açıkça yapılıyor (1A.5).
            */
            Route::put('/urunler/{urun:uuid}/varyantlar/{varyant:uuid}', [PanelUrunSayfasi::class, 'varyantGuncelle'])
                ->withoutScopedBindings()->name('panel.varyant.guncelle');

            Route::delete('/urunler/{urun:uuid}/varyantlar/{varyant:uuid}', [PanelUrunSayfasi::class, 'varyantSil'])
                ->withoutScopedBindings()->name('panel.varyant.sil');
        });

        /*
        | SİPARİŞ VE İADE (4E) — YETKİ ÜÇ KATMANLI.
        |
        | ★ Panel API'siyle AYNI ayrım (Faz 1-2). Arayüz onu BOZMUYOR:
        |   order.view     görebilir
        |   order.fulfill  kargolayabilir
        |   order.refund   para iadesi yapabilir
        |
        | ⚠️ Tek izne indirgemek en kolay yoldu ve depo personeline para
        | iadesi yetkisi vermek demekti.
        */
        Route::middleware('izin:order.view')->group(function () {
            Route::get('/siparisler', [OrderPageController::class, 'index'])->name('panel.siparisler');
            Route::get('/siparisler/{siparis:uuid}', [OrderPageController::class, 'show'])->name('panel.siparis');

            // ⚠️ İade TALEBİNİ görmek `order.view`; karar vermek `order.refund`.
            Route::get('/iadeler', [ReturnPageController::class, 'index'])->name('panel.iadeler');
            Route::get('/iadeler/{iade:uuid}', [ReturnPageController::class, 'show'])->name('panel.iade');
        });

        Route::middleware('izin:order.fulfill')->group(function () {
            /*
            | ⚠️ İç içe kapsama KAPALI (4D-K3'ün gerekçesi): paketin bu
            | siparişe ait olduğu controller'da AÇIKÇA doğrulanıyor, yani
            | koruma görünür ve ölçülebilir.
            */
            Route::post('/siparisler/{siparis:uuid}/paketler', [OrderPageController::class, 'paketOlustur'])
                ->name('panel.paket.olustur');

            Route::post('/siparisler/{siparis:uuid}/paketler/{paket:uuid}/kargo', [OrderPageController::class, 'kargoyaVer'])
                ->withoutScopedBindings()->name('panel.paket.kargo');

            Route::post('/siparisler/{siparis:uuid}/paketler/{paket:uuid}/teslim', [OrderPageController::class, 'teslimEdildi'])
                ->withoutScopedBindings()->name('panel.paket.teslim');

            Route::delete('/siparisler/{siparis:uuid}/paketler/{paket:uuid}', [OrderPageController::class, 'paketIptal'])
                ->withoutScopedBindings()->name('panel.paket.iptal');
        });

        /*
        | TEMA (4G) — markanın vitrinini biçimlendirdiği ekran.
        |
        | ⚠️ `settings.write` izni: tema mağazanın YÜZÜ ve onu değiştirmek
        | ayarları değiştirmektir. Ayrı bir izin açılsaydı "temayı
        | değiştirebilen ama iletişim bilgisini değiştiremeyen" gibi
        | pratikte hiç kullanılmayan bir rol türü doğardı.
        */
        /*
        | PERSONEL VE ROLLER (4.5C) — `izin:staff.manage` arkasında.
        |
        | ⚠️ Bu izin SİSTEMDEKİ EN TEHLİKELİSİ: yetki dağıtma yetkisi.
        | Roller ekranı da aynı kapının ardında — rol düzenleyebilen zaten
        | herkese her yetkiyi verebilir.
        */
        Route::middleware('izin:staff.manage')->group(function () {
            Route::get('/personel', [StaffPageController::class, 'index'])->name('panel.personel');
            Route::post('/personel', [StaffPageController::class, 'personelEkle'])->name('panel.personel.ekle');
            Route::delete('/personel/{kullanici}', [StaffPageController::class, 'personelCikar'])->name('panel.personel.cikar');

            Route::post('/roller', [StaffPageController::class, 'rolEkle'])->name('panel.rol.ekle');
            Route::put('/roller/{rol}', [StaffPageController::class, 'rolGuncelle'])->name('panel.rol.guncelle');
            Route::delete('/roller/{rol}', [StaffPageController::class, 'rolSil'])->name('panel.rol.sil');
        });

        Route::middleware('izin:settings.write')->group(function () {
            /*
            | MAĞAZA AYARLARI VE YAYINA ALMA (4H).
            |
            | ★ Bitiş ölçütünün eksik halkasıydı: marka `curl` olmadan
            | mağazasını AÇAMIYORDU.
            */
            /*
            | ÖDEME SAĞLAYICISI (4.5B) — Faz 4'ün EN CİDDİ boşluğuydu:
            | marka panelden sağlayıcısını kuramıyordu, yani GERÇEK PARA
            | TAHSİL EDEMİYORDU. Uçları 1E'de vardı, ekranı yoktu.
            */
            Route::get('/odeme-ayarlari', [PaymentSettingsPageController::class, 'index'])->name('panel.odeme');
            Route::post('/odeme-ayarlari', [PaymentSettingsPageController::class, 'kaydet'])->name('panel.odeme.kaydet');

            /*
            | YASAL METİNLER (4.5B) — taslak ve yayın AYRI (1A.4).
            | `legal_document_versions` salt-ekleme: yayınlamak yeni satır.
            */
            /*
            | ÖZEL ALAN ADI (4.5C) — 3H'nin karşılığı.
            |
            | ⚠️ Uçları 3H'de vardı ama ekranı yoktu, yani marka DNS
            | talimatını HİÇ GÖREMİYORDU — o adım insan işi ve destek
            | yükünün tamamı orada.
            */
            Route::get('/alan-adlari', [DomainPageController::class, 'index'])->name('panel.alanadlari');
            Route::post('/alan-adlari', [DomainPageController::class, 'ekle'])->name('panel.alanadi.ekle');
            Route::post('/alan-adlari/{alanAdi}/dogrula', [DomainPageController::class, 'dogrula'])->name('panel.alanadi.dogrula');
            Route::delete('/alan-adlari/{alanAdi}', [DomainPageController::class, 'sil'])->name('panel.alanadi.sil');

            Route::get('/yasal', [PanelLegalSayfa::class, 'index'])->name('panel.yasal');
            Route::post('/yasal/{tur}', [PanelLegalSayfa::class, 'kaydet'])->name('panel.yasal.kaydet');
            Route::post('/yasal/{tur}/yayinla', [PanelLegalSayfa::class, 'yayinla'])->name('panel.yasal.yayinla');

            Route::get('/magaza', [StorePageController::class, 'index'])->name('panel.magaza');
            Route::post('/magaza', [StorePageController::class, 'kaydet'])->name('panel.magaza.kaydet');
            Route::post('/magaza/yayinla', [StorePageController::class, 'yayinla'])->name('panel.magaza.yayinla');
            Route::post('/magaza/kapat', [StorePageController::class, 'kapat'])->name('panel.magaza.kapat');

            Route::get('/tema', [ThemePageController::class, 'index'])->name('panel.tema');
            Route::post('/tema', [ThemePageController::class, 'kaydet'])->name('panel.tema.kaydet');
            Route::post('/tema/logo', [ThemePageController::class, 'logoYukle'])->name('panel.tema.logo');
            Route::delete('/tema/logo', [ThemePageController::class, 'logoKaldir'])->name('panel.tema.logo.sil');
        });

        Route::middleware('izin:order.refund')->group(function () {
            Route::post('/iadeler/{iade:uuid}/onayla', [ReturnPageController::class, 'onayla'])->name('panel.iade.onayla');
            Route::post('/iadeler/{iade:uuid}/reddet', [ReturnPageController::class, 'reddet'])->name('panel.iade.reddet');
            Route::post('/iadeler/{iade:uuid}/teslim-al', [ReturnPageController::class, 'teslimAl'])->name('panel.iade.teslim');
            Route::post('/iadeler/{iade:uuid}/para-iadesi', [ReturnPageController::class, 'paraIadesi'])->name('panel.iade.para');
        });
    });

});
