<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel oturumu AÇILDIĞI MARKAYA bağlı. (4H)
 *
 * ★ GERÇEK BİR AÇIK KAPATIYOR — 4H'de ölçüldü:
 *
 * ```
 * A markasında giriş yap        → oturum çerezi
 * aynı çerezle B'nin paneline   → 200, PANEL AÇILIYOR
 * ```
 *
 * Oturum yalnızca kullanıcı KİMLİĞİNİ (id) tutuyor; guard o kimliği
 * ISTEĞIN kiracısının şemasından çözüyor. İki markada da `id = 1` olan
 * birer kullanıcı olduğu için A'nın oturumu B'de geçerli sayılıyordu.
 *
 * ⚠️ BUGÜN TARAYICI BUNU YAPMAZ: oturum çerezi alan adına bağlı
 * (`SESSION_DOMAIN=null`), yani `marka-a.test` çerezi `marka-b.test`'e
 * gönderilmez. Ama koruma buna bırakılamaz:
 *
 *   · 3D'deki self-servis kayıt markalara ALT ALAN ADI veriyor
 *     (`marka.tikmarka.com`). Biri `SESSION_DOMAIN`'i `.tikmarka.com`
 *     yaparsa — ki alt alan adları arasında oturum paylaşmak için yapılan
 *     TEK ŞEY budur — her markanın oturumu her markanın panelini açar.
 *   · Çalınan/kopyalanan bir çerez elle başka alan adına gönderilebilir.
 *
 * Yani tek savunma bir ORTAM DEĞİŞKENİYDİ. Artık sunucu da kontrol ediyor.
 */
class EnsureSessionTenant
{
    /** Oturumda markanın kimliğini tutan anahtar. */
    public const ANAHTAR = 'panel_tenant';

    public function handle(Request $istek, Closure $sonraki): Response
    {
        $guard = Auth::guard('staff-web');

        /*
        | ⚠️ Kontrol YALNIZCA OTURUMDAN GELEN girişe uygulanıyor.
        |
        | `$guard->check()` tek başına yetmiyor: kullanıcı programatik
        | olarak da atanmış olabilir (testlerde `actingAs`, ileride bir
        | konsol komutu). O durumda ortada TAŞINABİLİR BİR ÇEREZ YOK,
        | yani kapatmaya çalıştığımız saldırı da yok.
        |
        | `getName()` guard'ın oturumdaki anahtarını veriyor — giriş
        | gerçekten oturuma yazıldıysa orada duruyor.
        */
        $oturumdanMi = $guard instanceof SessionGuard && $istek->session()->has($guard->getName());

        if ($oturumdanMi && $guard->check()) {
            $oturumdaki = $istek->session()->get(self::ANAHTAR);
            $simdiki = tenant('id');

            /*
            | ⚠️ EKSİK DEĞER de uyuşmazlık sayılıyor: bu middleware
            | yayına girmeden önce açılmış bir oturum damgasız olur ve
            | damgasızı geçerli saymak kapıyı açık bırakırdı.
            */
            if ($oturumdaki === null || (string) $oturumdaki !== (string) $simdiki) {
                $guard->logout();

                /*
                | ⚠️ Oturum TAMAMEN geçersiz kılınıyor. Yalnızca `logout()`
                | çağrılsaydı oturum verisi (ve CSRF token'ı) tarayıcıda
                | kalır, saldırgan aynı oturumla denemeye devam ederdi.
                */
                $istek->session()->invalidate();
                $istek->session()->regenerateToken();

                /*
                | ★ İSTEK BURADA KESİLİYOR — `$sonraki` ÇAĞRILMIYOR.
                |
                | ⚠️ Önce yalnızca `logout()` yapılıp istek devam
                | ettiriliyordu ve BU YETMEDİ (ölçüldü): Laravel
                | middleware'leri ÖNCELİK LİSTESİNE göre yeniden
                | sıralıyor ve `Authenticate` bizden ÖNCE koşuyor —
                | yani kapıyı o çoktan açmış oluyordu. Sayfa
                | `check() === false` ile render ediliyor ama yine de
                | **200** dönüyordu.
                |
                | ⚠️ `prependToPriorityList` ile sırayı düzeltmeyi denedim,
                | tutmadı. İsteği kesmek SIRADAN BAĞIMSIZ: middleware
                | zincirin neresinde olursa olsun aynı sonucu veriyor.
                */
                return redirect()->route('panel.giris');
            }
        }

        return $sonraki($istek);
    }
}
