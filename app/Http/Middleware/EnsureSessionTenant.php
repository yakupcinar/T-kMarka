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

    /**
     * Oturum tabanlı guard'ların hepsi.
     *
     * ⚠️ 4.5D'de müşteri oturumu eklendi ve bu liste O GÜN GENİŞLETİLDİ.
     * Tek guard'a bakmaya devam etseydi aynı açık müşteri tarafında AÇIK
     * KALIRDI — üstelik sessizce, çünkü personel testi yeşil kalıyordu.
     *
     * @var list<string>
     */
    private const GUARDLAR = ['staff-web', 'customer-web'];

    public function handle(Request $istek, Closure $sonraki): Response
    {
        foreach (self::GUARDLAR as $ad) {
            $guard = Auth::guard($ad);

            /*
            | ⚠️ Kontrol YALNIZCA OTURUMDAN GELEN girişe uygulanıyor.
            |
            | `$guard->check()` tek başına yetmiyor: kullanıcı programatik
            | olarak da atanmış olabilir (testlerde `actingAs`, ileride bir
            | konsol komutu). O durumda ortada TAŞINABİLİR BİR ÇEREZ YOK,
            | yani kapatmaya çalıştığımız saldırı da yok.
            */
            if (! $guard instanceof SessionGuard || ! $istek->session()->has($guard->getName())) {
                continue;
            }

            if (! $guard->check()) {
                continue;
            }

            $oturumdaki = $istek->session()->get(self::ANAHTAR);

            /*
            | ⚠️ EKSİK DEĞER de uyuşmazlık sayılıyor: bu middleware
            | yayına girmeden önce açılmış bir oturum damgasız olur ve
            | damgasızı geçerli saymak kapıyı açık bırakırdı.
            */
            if ($oturumdaki !== null && (string) $oturumdaki === (string) tenant('id')) {
                continue;
            }

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
            | ettiriliyordu ve BU YETMEDİ (4H'de ölçüldü): Laravel
            | middleware'leri ÖNCELİK LİSTESİNE göre yeniden sıralıyor
            | ve `Authenticate` bizden ÖNCE koşuyor — yani kapıyı o
            | çoktan açmış oluyordu. Sayfa `check() === false` ile
            | render ediliyor ama yine de **200** dönüyordu.
            |
            | ⚠️ `prependToPriorityList` ile sırayı düzeltmeyi denedim,
            | tutmadı. İsteği kesmek SIRADAN BAĞIMSIZ.
            |
            | ⚠️ Müşteri panele değil VİTRİNE düşüyor: giriş sayfasına
            | atmak, hiç giriş yapmamış bir ziyaretçiyi de oraya
            | zorlamak olurdu.
            */
            return $ad === 'staff-web'
                ? redirect()->route('panel.giris')
                : redirect()->route('vitrin.anasayfa');
        }

        return $sonraki($istek);
    }
}
