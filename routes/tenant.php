<?php

declare(strict_types=1);

use App\Http\Panel\AuthController as PanelAuth;
use App\Http\Panel\LegalController;
use App\Http\Panel\OptionController;
use App\Http\Panel\RoleController;
use App\Http\Panel\SettingsController;
use App\Http\Panel\StaffController;
use App\Http\Panel\StoreController;
use App\Http\Storefront\AddressController;
use App\Http\Storefront\AuthController as VitrinAuth;
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
    | VİTRİN — markanın müşterisi
    */
    Route::prefix('api')->group(function () {

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
