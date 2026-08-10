<?php

use App\Domain\Catalog\CategoryCycleException;
use App\Domain\Catalog\CategoryHasChildrenException;
use App\Domain\Catalog\EmptySlugException;
use App\Domain\Identity\RoleInUseException;
use App\Domain\Identity\SystemRoleException;
use App\Domain\Legal\EmptyLegalDocumentException;
use App\Domain\Legal\UnfilledPlaceholderException;
use App\Domain\Settings\SettingLockedException;
use App\Domain\Settings\StoreNotReadyException;
use App\Http\Middleware\RequireOwner;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequirePublishedStore;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
        | Kategori ağacı kuralları → 409 Conflict.
        |
        | Yine ZAMAN/DURUM sorunu: yetki var, veri geçerli — ağacın şu anki
        | hâli bu işlemi kaldırmıyor.
        */
        $exceptions->render(function (CategoryCycleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'resolution' => 'Önce hedef kategoriyi bu dalın dışına taşıyın.',
            ], 409);
        });

        $exceptions->render(function (CategoryHasChildrenException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'children_count' => $e->altSayisi,
                'resolution' => 'Önce alt kategorileri taşıyın veya silin.',
            ], 409);
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
