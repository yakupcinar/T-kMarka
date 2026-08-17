<?php

namespace App\Http\Storefront;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Misafir sepetinin kimliği — başlıktan VE çerezden. (4A)
 *
 * ★ NEDEN DEĞİŞTİ: 1C-K1'de kimlik `X-Cart-Token` BAŞLIĞINA konmuştu ve
 * gerekçesi [CartController]'da aynen şuydu:
 *
 * > "Çerez değil: vitrin Faz 4'te ve teknolojisi seçilmedi (M-3); çerez
 * >  seçersek API'yi henüz var olmayan bir istemciye bağlarız."
 *
 * M-3 verildi (4-K1): vitrin **sunucuda render edilen Blade**. Ve tarayıcı
 * düz bir sayfa gezinmesinde **özel başlık gönderemez** — çerez gönderir.
 * Başlık tek yol olarak kalsaydı sunucu, üst bardaki sepet sayısını
 * yazamazdı: sayfa sunucuda üretiliyor ama sepetin kim olduğu bilinmiyor.
 *
 * ⚠️ BAŞLIK KALDIRILMIYOR, çerez EKLENİYOR. Kaldırılsaydı bugün çalışan
 * her API istemcisi (ve yarın mobil uygulama) sepetini kaybederdi.
 *
 * ```
 * başlık önce  →  API / mobil / açık niyet
 * sonra çerez  →  tarayıcı gezinmesi
 * ```
 */
class CartToken
{
    public const CEREZ = 'tikmarka_sepet';

    public const BASLIK = 'X-Cart-Token';

    /**
     * Sepetin kaç gün taşınacağı.
     *
     * ⚠️ Oturum çerezi (tarayıcı kapanınca ölen) OLMAZ: müşteri sekmeyi
     * kapatıp ertesi gün dönünce sepetini boş bulurdu. Terk edilmiş
     * ödeme hatırlatması (2F) da anlamsızlaşırdı — hatırlattığımız sepete
     * müşteri geri dönemezdi.
     */
    public const GUN = 30;

    /**
     * İsteğin taşıdığı sepet kimliği; yoksa `null`.
     *
     * ⚠️ SIRA ÖNEMLİ. Başlık önce okunuyor: bir istemci başlığı bilerek
     * gönderiyorsa niyeti açıktır ve tarayıcının kendiliğinden eklediği
     * çerez onu ezmemeli. Ters sırada, çerezi olan bir tarayıcıdan
     * atılan API çağrısı yanlış sepete yazardı.
     */
    public static function oku(Request $istek): ?string
    {
        $baslik = $istek->header(self::BASLIK);

        if (is_string($baslik) && $baslik !== '') {
            return $baslik;
        }

        $cerez = $istek->cookie(self::CEREZ);

        return is_string($cerez) && $cerez !== '' ? $cerez : null;
    }

    /**
     * Tarayıcıya yazılacak çerez.
     *
     * ⚠️ `httpOnly` — JavaScript okuyamıyor. Token bir taşıyıcı sırdır;
     * okunabilseydi sayfaya sızan herhangi bir betik müşterinin sepetini
     * (ne aldığını) dışarı taşıyabilirdi.
     *
     * ⚠️ `sameSite = lax` — başka siteden gelen POST'a çerez gitmiyor,
     * ama normal bağlantıyla gelen müşteri sepetini koruyor.
     */
    public static function cerez(string $token): Cookie
    {
        return cookie(
            name: self::CEREZ,
            value: $token,
            minutes: self::GUN * 24 * 60,
            secure: null,
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
