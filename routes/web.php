<?php

use App\Http\Middleware\HandlePlatformInertia;
use App\Http\Platform\DomainCheckController;
use App\Http\Platform\PlatformAuthPageController as PlatformAuthPage;
use App\Http\Platform\PlatformPageController as PlatformPage;
use App\Models\Event;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
| MERKEZ rotaları — yalnızca kontrol düzleminin adreslerinde geçerli.
|
| Marka rotaları routes/tenant.php'de. İki dosya da aynı adresi (örn "/")
| tanımlayabildiği için burayı alan adına KİLİTLİYORUZ; yoksa sonra
| yüklenen dosya diğerini gölgeler ve merkez adres erişilemez olur.
|
| Adres listesi config/tenancy.php → central_domains
*/

/*
|--------------------------------------------------------------------------
| SUNUM VİTRİNİ — markanın kendi alan adında
|--------------------------------------------------------------------------
|
| ★ TEK DOSYA: bütün arayüz `resources/views/showcase.blade.php` içinde.
| `app/` altında SUNUMA AİT HİÇBİR SINIF YOK — controller, servis, kaynak
| sınıfı, hiçbiri. Sayfa gerçek API uçlarını tarayıcıdan `fetch` ile
| çağırıyor; yani gösterdiği şey gerçekten çalışan sistem.
|
| ⚠️ Buradaki iki kapanış (closure) "backend kodu" değil, YÖNLENDİRME:
| biri şablonu basıyor, diğeri olay tablosunun güvenli özetini okuyor.
| Olaylar için API ucu YOK (1F'de bilerek açılmadı) — sunum için bir uç
| eklemek, API yüzeyini sunuma göre şekillendirmek olurdu.
|
| ⚠️ Kiracı middleware'i ŞART: `events` marka şemasında. Onsuz sayfa
| merkez bağlamda koşar ve hiçbir şey bulamaz (M-2.4).
|
| ⚠️ `magaza-acik` kapısının DIŞINDA: mağaza kapalıyken de sunum açılsın,
| kapanın davranışı sayfada gösterilebilsin diye.
*/
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('showcase')->group(function (): void {

    Route::get('/', fn () => view('showcase', [
        'marka' => (string) tenant('id'),
        'alanAdi' => request()->getHost(),
    ]))->name('showcase.index');

    /*
    | Olay akışı — SALT OKUNUR.
    |
    | ⚠️ Yalnızca tip ve zaman dönüyor. `payload` DIŞARI VERİLMİYOR:
    | ngrok adresi izleyicilere açık ve yükte sipariş kimlikleri var.
    */
    Route::get('/events', fn () => response()->json([
        'events' => Event::query()
            ->select(['type', 'occurred_at'])
            ->latest('occurred_at')
            ->limit(15)
            ->get()
            ->map(fn (Event $o): array => [
                'type' => $o->type->value,
                'at' => $o->occurred_at?->toIso8601String(),
            ])
            ->all(),
        'counts' => Event::query()
            ->selectRaw('type, count(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type'),
    ]))->name('showcase.events');

});

foreach (config('tenancy.central_domains') as $centralDomain) {
    Route::domain($centralDomain)->group(function () {

        Route::get('/', function () {
            return 'TıkMarka kontrol düzlemi';
        });

        // Caddy on-demand TLS için sorar: "bu alan adı sizin mi?" (M-4.1/1)
        // 200 = kayıtlı, sertifika alınabilir · 404 = kayıtlı değil
        /*
        |------------------------------------------------------------------
        | KONTROL DÜZLEMİ ARAYÜZÜ (4F) — TıkMarka'yı işletenin ekranı
        |------------------------------------------------------------------
        |
        | ★ 4-K1: ayrım ALAN ADI + YOL ile.
        |   marka alan adı  + /yonetim → MARKANIN paneli
        |   merkez alan adı + /yonetim → BİZİM kontrol düzlemimiz
        |
        | ⚠️ `web` grubu: oturum ve CSRF gerekiyor. Merkez API'si
        | (`routes/platform.php`) `api` grubunda kalıyor — 3C'de o ayrım
        | gerçek `curl` koşusuyla öğrenilmişti.
        |
        | ⚠️ Buradaki her işlem BÜTÜN MARKALARA uzanıyor.
        */
        Route::prefix('yonetim')->middleware(HandlePlatformInertia::class)->group(function () {

            Route::middleware('guest:platform-web')->group(function () {
                Route::get('/giris', [PlatformAuthPage::class, 'form'])->name('yonetim.giris');

                Route::post('/giris', [PlatformAuthPage::class, 'giris'])
                    ->middleware('throttle:giris')->name('yonetim.giris.gonder');
            });

            Route::middleware('auth:platform-web')->group(function () {
                Route::get('/', [PlatformPage::class, 'pano'])->name('yonetim.pano');
                Route::post('/cikis', [PlatformAuthPage::class, 'cikis'])->name('yonetim.cikis');

                Route::get('/markalar', [PlatformPage::class, 'markalar'])->name('yonetim.markalar');
                Route::get('/markalar/{tenant}', [PlatformPage::class, 'marka'])->name('yonetim.marka');
                Route::post('/markalar/{tenant}/durum', [PlatformPage::class, 'durumDegistir'])->name('yonetim.marka.durum');

                /*
                | ★ BAŞVURU ONAY/RED (4.5N) — durum değiştirmeden AYRI
                | uçlar.
                |
                | ⚠️ Genel `durum` ucuyla yapılabilirdi ama iki yan etkisi
                | var: onay DENEME SÜRESİNİ başlatıyor, red SEBEBİ
                | kaydediyor. Genel uca yığılsaydı "durumu trial yap"
                | diyen her çağrı sessizce deneme süresini de yeniden
                | yazardı.
                */
                Route::post('/markalar/{tenant}/onayla', [PlatformPage::class, 'basvuruOnayla'])->name('yonetim.marka.onayla');
                Route::post('/markalar/{tenant}/reddet', [PlatformPage::class, 'basvuruReddet'])->name('yonetim.marka.reddet');
                Route::post('/markalar/{tenant}/plan', [PlatformPage::class, 'planAta'])->name('yonetim.marka.plan');

                /*
                | ★ MARKA VERİSİNİN DIŞA AKTARIMI — Faz 3'ten devredilen
                | borç. KVKK: veri işleyen, sözleşme bitince veriyi İADE
                | EDİP siler. Silme 3G'de vardı, iade yoktu.
                */
                Route::get('/markalar/{tenant}/disa-aktar', [PlatformPage::class, 'disaAktar'])
                    ->name('yonetim.marka.disa-aktar');
            });

        });

        Route::get('/tenancy/domain-check', DomainCheckController::class)
            ->name('tenancy.domain-check');

    });
}
