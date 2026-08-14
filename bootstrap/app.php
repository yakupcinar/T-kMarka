<?php

use App\Domain\Cart\VariantNotPurchasableException;
use App\Domain\Catalog\CatalogConflictException;
use App\Domain\Catalog\CatalogRuleException;
use App\Domain\Catalog\CollectionRuleException;
use App\Domain\Catalog\EmptySlugException;
use App\Domain\Identity\RoleInUseException;
use App\Domain\Identity\SystemRoleException;
use App\Domain\Legal\EmptyLegalDocumentException;
use App\Domain\Legal\UnfilledPlaceholderException;
use App\Domain\Order\CartNotOrderableException;
use App\Domain\Order\OrderNotShippableException;
use App\Domain\Order\OverShipmentException;
use App\Domain\Order\StaleContractException;
use App\Domain\Payment\OrderNotPayableException;
use App\Domain\Payment\PaymentAmountMismatchException;
use App\Domain\Payment\PaymentNotConfiguredException;
use App\Domain\Payment\PaymentProviderException;
use App\Domain\Payment\UnknownPaymentReferenceException;
use App\Domain\Privacy\InvalidDataRequestException;
use App\Domain\Privacy\UnknownDataSubjectException;
use App\Domain\Promotion\InvalidCouponException;
use App\Domain\Returns\OverReturnException;
use App\Domain\Returns\ReturnNotRefundableException;
use App\Domain\Returns\ReturnWindowClosedException;
use App\Domain\Review\DuplicateReviewException;
use App\Domain\Review\NotPurchasedException;
use App\Domain\Settings\SettingLockedException;
use App\Domain\Settings\StoreNotReadyException;
use App\Domain\Stock\InsufficientStockException;
use App\Domain\Stock\StockLockTimeoutException;
use App\Http\Middleware\ForceJson;
use App\Http\Middleware\RequireActiveTenant;
use App\Http\Middleware\RequireOwner;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequirePublishedStore;
use App\Platform\InvalidTransitionException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        /*
        | ⚠️ Kontrol düzlemi `api` grubunda — `web` DEĞİL. Gerçek curl
        | koşusu yakaladı: `web` grubundayken bütün testler yeşildi ama
        | gerçek istemci `CSRF token mismatch` aldı (3C). Testler
        | `postJson` kullandığı için hiç görünmedi.
        */
        then: function (): void {
            Route::middleware('api')->group(base_path('routes/platform.php'));
        },
    )
    /*
    | Laravel artisan komutlarını yalnızca app/Console/Commands klasöründe
    | kendiliğinden bulur. Bizim kiracılık komutlarımız M-2.7 gereği
    | app/Tenancy/ altında duruyor ("kiracılığın tamamı tek yerde"), bu
    | yüzden klasörü burada tanıtıyoruz.
    */
    ->withCommands([
        __DIR__.'/../app/Tenancy/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        | ★ VEKİL SUNUCU GÜVENİ — şema (http/https) doğru okunsun diye.
        |
        | ⚠️ 1E.7.3'te ısırdı. Uygulama Caddy'nin ARKASINDA duruyor ve
        | ngrok tünelinde Caddy'ye trafik `http` olarak geliyor (TLS'i
        | ngrok sonlandırıyor). Laravel isteği `http` sanıyordu ve ürettiği
        | her adres `http://` çıkıyordu.
        |
        | Bunun sessiz bedeli: iyzico callback adresinin SSL olmasını
        | ZORUNLU tutuyor. `http://…/odeme/donus` gönderilseydi ödeme
        | başlatma isteği reddedilir, müşteri hiçbir yere yönlendirilemez
        | ve sebebi "ödeme çalışmıyor"dan ibaret kalırdı.
        |
        | ⚠️ `at: '*'` DEĞİL — yalnızca ÖZEL AĞ aralıkları. `*` denseydi,
        | uygulamaya doğrudan ulaşabilen herkes `X-Forwarded-Proto` ve
        | `X-Forwarded-For` başlıklarını uydurabilirdi. Bizde tek giriş
        | Caddy ve o Docker ağının içinde (172.x).
        */
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        /*
        | ★ HER CEVAP JSON — ölçülerek eklendi (2E).
        |
        | `Accept: application/json` göndermeyen bir istemci korumalı bir
        | uca vurduğunda Laravel `login` rotasına yönlendirmeye çalışıyor;
        | arayüz olmadığı için (M-3) öyle bir rota yok ve 500 dönüyor.
        | Gerekçenin tamamı [ForceJson]'da — `shouldRenderJsonWhen` ve
        | istisna eşlemesinin ikisi de denendi, ikisi de çözmedi.
        */
        $middleware->prepend(ForceJson::class);

        /*
        | Rotalarda `izin:staff.manage` şeklinde kullanılabilmesi için takma ad.
        */
        $middleware->alias([
            'izin' => RequirePermission::class,

            /*
            | Yalnızca marka sahibi. İZİN DEĞİL, BAYRAK — yetki dağıtan
            | işlem yetkiyle dağıtılmaz (RequireOwner'da gerekçesi).
            */
            'sahip' => RequireOwner::class,

            /*
            | Vitrin rotalarına takılır: mağaza kapalıysa 503.
            | Panele TAKILMAZ — mağazayı tekrar açmanın tek yolu panel.
            */
            'magaza-acik' => RequirePublishedStore::class,

            /*
            | ⚠️ Askıya alınmış markanın PANELİNİ kapatıyor; vitrin açık
            | kalıyor (3C, 4 numaralı karar). İkisi AYRI soru soruyor:
            |   `magaza-acik`  → marka mağazasını yayınladı mı  (vitrin)
            |   `marka-aktif`  → markanın aboneliği yürüyor mu  (panel)
            */
            'marka-aktif' => RequireActiveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        | Tanımsız bir alan adına istek gelirse paket istisna fırlatıyor ve
        | Laravel bunu 500 (sunucu hatası) sayıyor. Doğru cevap 404:
        | sunucuda bir şey patlamadı, öyle bir marka yok.
        |
        | Ayrıca 500, saldırgana "burada bir şey var ama bozuldu" bilgisi
        | verir; 404 hiçbir şey söylemez.
        */
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $e) {
            abort(404);
        });

        /*
        | Aşağıdaki üç eşleme controller'lara try/catch yazmamak için burada.
        | Her uçta tekrar yazılsaydı bir gün biri unutur, iş kuralı ihlali
        | 500 olarak dönerdi.
        */

        /*
        | Kilitli ayar / yayındayken yasal metin yayınlama → 409 Conflict.
        |
        | 403 DEĞİL: personelin yetkisi var.
        | 422 DEĞİL: gönderilen veri geçerli.
        | Yanlış olan ZAMAN — istek sistemin şu anki durumuyla çelişiyor.
        */
        $exceptions->render(function (SettingLockedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'field' => $e->alan,
                'resolution' => 'Önce /panel/store/close ile mağazayı kapatın.',
            ], 409);
        });

        /*
        | Eksik bilgiyle yayına alma denemesi → 422 + eksiklerin TAMAMI.
        | Tek tek bildirilseydi marka her seferinde bir eksik görüp
        | defalarca tur atardı.
        */
        $exceptions->render(function (StoreNotReadyException $e) {
            return response()->json([
                'message' => 'Mağaza yayına hazır değil.',
                'missing' => $e->eksikler,
            ], 422);
        });

        /*
        | Doldurulamayan yer tutucu → 422.
        |
        | Ya mağaza bilgisi eksik ya da tanınmayan bir yer tutucu yazılmış.
        | İkisinde de metin yayınlanmıyor: müşteriye `{{unvan}}` gitmesindense
        | hata iyidir.
        */
        $exceptions->render(function (UnfilledPlaceholderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'placeholders' => $e->yerTutucular,
                'resolution' => 'Mağaza bilgilerini tamamlayın veya yer tutucuyu metinden çıkarın.',
            ], 422);
        });

        /*
        | Rol silme kuralları → 409 Conflict.
        |
        | Yine ZAMAN/DURUM sorunu: sahibin yetkisi var, gönderdiği veri de
        | geçerli — rolün şu anki durumu silmeye elverişli değil.
        */
        $exceptions->render(function (SystemRoleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'resolution' => 'İzinlerini düzenleyebilirsiniz.',
            ], 409);
        });

        $exceptions->render(function (RoleInUseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'staff_count' => $e->personelSayisi,
                'resolution' => 'Önce personeli başka bir role taşıyın.',
            ], 409);
        });

        /*
        | SEPET · STOK · SİPARİŞ istisnaları.
        |
        | ⚠️ 409 ile 422 ayrımı yine aynı: 409 ZAMAN/DURUM sorunu (veri
        | geçerli ama şu an olmuyor), 422 verinin kendisi geçersiz.
        */
        $exceptions->render(function (CartNotOrderableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'blockers' => $e->engeller,
                'resolution' => 'Sepetteki sorunlu satırları kaldırın.',
            ], 409);
        });

        $exceptions->render(function (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'sku' => $e->sku,
                'available' => $e->mevcut,
            ], 409);
        });

        $exceptions->render(function (VariantNotPurchasableException $e) {
            return response()->json(['message' => $e->getMessage(), 'sku' => $e->sku], 409);
        });

        /*
        | ⚠️ 503 — geçici. Kilit meşguldü, veri sorunlu değil (1D-K6).
        | Müşteri tekrar denemeli; aşırı satış riski yok çünkü kilit
        | kurulamadan hiçbir şey yazılmadı.
        */
        $exceptions->render(function (StockLockTimeoutException $e) {
            return response()->json(['message' => $e->getMessage()], 503)
                ->header('Retry-After', '2');
        });

        $exceptions->render(function (StaleContractException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'resolution' => 'Sözleşmeyi yeniden okuyup onaylayın.',
            ], 422);
        });

        $exceptions->render(function (OverShipmentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'sku' => $e->sku,
                'ordered' => $e->siparisAdedi,
            ], 422);
        });

        /*
        | Ödemeye uygun olmayan sipariş → 409.
        |
        | "Teşekkürler" sayfasını yenileyen müşteri ikinci kez ödeme
        | başlatamasın diye; veri geçerli, yanlış olan ZAMAN.
        */
        $exceptions->render(function (OrderNotPayableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'payment_status' => $e->odemeDurumu->value,
            ], 409);
        });

        /*
        | Bilinmeyen ödeme referansı → 404.
        |
        | ⚠️ 200 DEĞİL: sağlayıcı 200'ü "işlendi" sayıp bir daha aramaz.
        | 404 alınca 15 dakika sonra tekrar deniyor — bizdeki sorun
        | geçiciyse ikinci deneme kurtarıyor.
        */
        /*
        | Sağlayıcı anahtarları eksik → 503 + Retry-After.
        |
        | ⚠️ Eksik anahtar adları cevaba KONMUYOR: müşteriye markanın
        | altyapısı hakkında bilgi vermenin anlamı yok. Marka eksikleri
        | kendi panelinde görüyor (/panel/payment).
        */
        $exceptions->render(function (PaymentNotConfiguredException $e) {
            return response()->json(['message' => $e->getMessage()], 503)
                ->header('Retry-After', '300');
        });

        /*
        | Sağlayıcı çağrısı başarısız → 502 + Retry-After.
        |
        | ⚠️ 500 DEĞİL: bizde bir şey patlamadı, DIŞ servis reddetti ya da
        | ulaşılamadı. 1E.7.3'te gerçek sandbox `.test` uzantılı e-postayı
        | reddetti ve müşteri ham istisna gövdesi gördü — sınıf adı, dosya
        | yolu ve yığın izi dâhil.
        |
        | ⚠️ Sağlayıcının mesajı MÜŞTERİYE GİTMİYOR. İçinde hesap
        | yapılandırmasına dair ayrıntı olabiliyor; ayrıca müşterinin
        | yapabileceği bir şey yok. Ayrıntı istisnada duruyor ve günlüğe
        | düşüyor.
        */
        $exceptions->render(function (PaymentProviderException $e) {
            return response()->json(['message' => 'Ödeme başlatılamadı, lütfen tekrar deneyin.'], 502)
                ->header('Retry-After', '10');
        });

        /*
        | KVKK talebi: sahibi doğrulanamadı → 404.
        |
        | ⚠️ "Böyle bir müşteri yok" DEMİYORUZ. Deseydik, adres deneyerek
        | hangi e-postanın kayıtlı olduğu öğrenilebilirdi.
        */
        /*
        | Cayma süresi dolmuş → 409.
        |
        | ⚠️ ZAMAN sorunu: yetki var, veri geçerli, geçen şey süre.
        | ⚠️ Kusurlu ürün iadesi bu istisnayı almaz — cayma değil.
        */
        /*
        | Kupon uygulanamıyor → 422.
        |
        | ⚠️ Sebep söyleniyor ("tutar yetersiz") çünkü müşterinin
        | yapabileceği bir şey var. Ama kuponun VARLIĞI hakkında bilgi
        | verilmiyor: "yok" ile "süresi geçmiş" ayrımı, kod deneyerek
        | geçerli kupon aramanın kapısını açardı.
        */
        $exceptions->render(function (InvalidCouponException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        /*
        | Geçersiz koleksiyon kuralı → 422. (2D)
        |
        | ⚠️ Eşlenmeseydi marka yığın izi görürdü ve panel hatayı
        | kullanıcıya anlatamazdı — 1E'de aynısı yaşandı, sağlayıcı
        | hatası 500 dönüyordu.
        |
        | ⚠️ Sebep AÇIKÇA söyleniyor ("bilinmeyen alan: x"): kuralı yazan
        | markanın kendisi ve düzeltebilmesi için neyin yanlış olduğunu
        | bilmesi gerekiyor. Kupon istisnasından farkı bu — orada bilgi
        | saklamanın bir gerekçesi vardı.
        */
        $exceptions->render(function (CollectionRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        /*
        | Satın almamış müşteri yorum yazamaz → 403. (2E-K1)
        |
        | ⚠️ 404 DEĞİL: ürün var ve görünüyor, eksik olan YETKİ.
        | ⚠️ 422 de değil: gönderilen veri geçerli.
        */
        /*
        | Geçersiz durum geçişi → 409. (3C)
        |
        | ⚠️ DURUM sorunu: veri geçerli ("closed" gerçek bir durum), yetki
        | var; engelleyen şey markanın ŞU ANKİ durumu. 422 olsaydı panel
        | "gönderdiğin değer bozuk" der ve yönetici yanlış yere bakardı.
        */
        $exceptions->render(function (InvalidTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        $exceptions->render(function (NotPurchasedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        });

        /*
        | Aynı ürüne ikinci yorum → 409.
        |
        | ⚠️ DURUM sorunu: veri geçerli, yetki var; engelleyen şey mevcut
        | kayıt. Veritabanı kısıtına bırakılsaydı müşteri 500 görürdü.
        */
        $exceptions->render(function (DuplicateReviewException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        });

        $exceptions->render(function (ReturnWindowClosedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'resolution' => 'Ürün kusurluysa cayma hakkı dışında talep açabilirsiniz.',
            ], 409);
        });

        /* Sipariş edilenden fazla iade → 422. (1D.4'ün aynası) */
        $exceptions->render(function (OverReturnException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'sku' => $e->sku,
                'ordered' => $e->siparisAdedi,
            ], 422);
        });

        /*
        | Talep para iadesine hazır değil → 409.
        |
        | ⚠️ Bloğun en önemli koruması: ÜRÜN ELE GEÇMEDEN PARA GİTMİYOR.
        */
        $exceptions->render(function (ReturnNotRefundableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'return_status' => $e->durum->value,
            ], 409);
        });

        $exceptions->render(function (UnknownDataSubjectException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        });

        /*
        | Doğrulama bağlantısı geçersiz/süresi dolmuş/kullanılmış → 410.
        |
        | ⚠️ Üç durum TEK mesaj: ayrılsaydı "bu jeton vardı ama süresi
        | doldu" bilgisi, jeton tahmin edene geri bildirim olurdu.
        */
        $exceptions->render(function (InvalidDataRequestException $e) {
            return response()->json(['message' => $e->getMessage()], 410);
        });

        $exceptions->render(function (UnknownPaymentReferenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        });

        /*
        | Tutar uyuşmazlığı → 422. Sipariş ÖDENMİŞ SAYILMIYOR.
        |
        | ⚠️ Tutarlar cevaba KONMUYOR: uç kimlik doğrulamasız ve imzayı
        | geçemeyen biri de 422 alabilir; beklenen tutarı söylemek ona
        | bilgi vermek olurdu.
        */
        $exceptions->render(function (PaymentAmountMismatchException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        });

        $exceptions->render(function (OrderNotShippableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'payment_status' => $e->odemeDurumu->value,
            ], 409);
        });

        /*
        | KATALOG istisnaları — iki taban sınıf, iki eşleme.
        |
        | 1B.3 tek başına yedi yeni kural getiriyor; her birine ayrı render
        | yazmak bu dosyayı okunamaz hâle getirirdi (1A incelemesinde not
        | düşülmüştü). Gerekçeler istisna sınıflarının kendi docblock'larında.
        |
        | Conflict → yetki var, veri geçerli, ZAMAN yanlış.
        | Rule     → verinin kendisi geçersiz; beş dakika sonra da geçersiz.
        */
        $exceptions->render(function (CatalogConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'resolution' => $e->cozum(),
                ...$e->ayrintilar(),
            ], 409);
        });

        $exceptions->render(function (CatalogRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->alanHatalari(),
            ], 422);
        });

        /*
        | Slug üretilemeyen eksen/değer adı → 422.
        |
        | "★" gibi bir girdi `Str::slug`'tan boş dönüyor. Doğrulama
        | katmanında yakalanamıyor çünkü ad o aşamada henüz slug'a
        | çevrilmemiş oluyor.
        */
        $exceptions->render(function (EmptySlugException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['name' => [$e->getMessage()]],
            ], 422);
        });

        /* Boş yasal metin yayınlama denemesi → 422. */
        $exceptions->render(function (EmptyLegalDocumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'document' => $e->tur->value,
            ], 422);
        });

    })->create();
