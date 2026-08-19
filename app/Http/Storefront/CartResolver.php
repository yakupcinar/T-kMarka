<?php

namespace App\Http\Storefront;

use App\Domain\Cart\CartService;
use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sepeti çözer — sayfalar ve uçlar için TEK yol. (4B)
 *
 * ★ NEDEN AYRI SINIF: aynı mantık 4A'dan önce dört ayrı controller'da
 * kopyalanmıştı ve çerez desteği eklenirken ÜÇÜ ATLANDI. Sonuçları sessizdi:
 * tarayıcıdan kupon ve ödeme "sepet bulunamadı" alıyordu, giriş yapan
 * müşterinin misafir sepeti hiç birleşmiyordu.
 *
 * Kural artık ölçülüyor (`tests/Feature/SepetKimligiTest`): sepet kimliğini
 * yalnızca [CartToken] okuyabilir.
 *
 * ⚠️ Sıra önemli: GİRİŞ YAPMIŞSA müşteri sepeti kazanıyor. Misafir token'ı
 * da varsa birleştirme girişte yapılmış oluyor; burada tekrar denemek çift
 * birleştirmeye yol açardı.
 */
class CartResolver
{
    public function __construct(private readonly CartService $sepetler) {}

    /**
     * Sepeti bulur; yoksa `null`.
     *
     * ⚠️ AÇMIYOR. Her sayfa görüntülemesi sepet açsaydı veritabanı hiç
     * alışveriş yapmayan ziyaretçilerin sepetleriyle dolar, terk edilmiş
     * sepet raporu da (2F) anlamsızlaşırdı.
     */
    public function bul(Request $istek): ?Cart
    {
        /*
        | ★ GUARD AÇIKÇA YAZILIYOR — `user()` DEĞİL `user('customer-web')`.
        |
        | ⚠️ Varsayılan guard `customer` (sanctum, TOKEN). Bu sınıf yalnızca
        | SAYFALARDAN çağrılıyor ve orada kimlik OTURUMDA. Guard yazılmadığı
        | sürece sanctum sorulur, `null` döner ve giriş yapmış müşteri
        | MİSAFİR sayılır.
        |
        | ⚠️ Bedeli sessiz ve kalıcıydı: sepet müşteriye bağlanmıyor, sipariş
        | de `customer_id = null` doğuyordu. Ölçüldü — geliştirme markasında
        | 24 siparişin HEPSİ, ödenmişler dâhil, sahipsizdi. "Siparişlerim"
        | sayfası doğru yazılmıştı ama hiçbir zaman dolamazdı.
        |
        | ⚠️ API katmanı (`api/*`) BUNUN TERSİ: orada kimlik sanctum
        | token'ında ve varsayılan guard doğru. Bu yüzden düzeltme tüm
        | vitrine değil YALNIZCA sayfa katmanına uygulandı.
        */
        $kullanici = $istek->user('customer-web');

        if ($kullanici instanceof Customer) {
            return $this->sepetler->musteriSepeti($kullanici);
        }

        return $this->sepetler->misafirSepetiBul(CartToken::oku($istek));
    }

    /**
     * Sepeti bulur; yoksa AÇAR.
     *
     * ⚠️ Yalnızca müşteri bir şey EKLEDİĞİNDE çağrılır — görüntülemede değil.
     */
    public function bulYaDaAc(Request $istek): Cart
    {
        return $this->bul($istek) ?? $this->sepetler->misafirSepetiOlustur();
    }

    /**
     * Cevaba sepet çerezini iliştirir.
     *
     * ⚠️ MÜŞTERİ sepetine çerez YAZILMIYOR: kimliği zaten oturumu. Yazılsaydı
     * çıkış yapan kullanıcının tarayıcısında sahibi belli bir sepetin
     * token'ı kalırdı.
     */
    public function cerezle(RedirectResponse $cevap, Cart $sepet): RedirectResponse
    {
        if ($sepet->customer_id === null && is_string($sepet->session_token)) {
            $cevap->withCookie(CartToken::cerez($sepet->session_token));
        }

        return $cevap;
    }
}
