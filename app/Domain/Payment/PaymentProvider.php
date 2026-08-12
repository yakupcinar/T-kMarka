<?php

namespace App\Domain\Payment;

/**
 * Ödeme sağlayıcısı — dış dünyaya açılan TEK kapı. (PLAN 1E)
 *
 * ★ Arayüzün şekli 3D Secure akışından türüyor, bizim tercihimizden değil:
 *
 *   ┌ baslat() ──────────────────────────────────────────────┐
 *   │ müşteri bizden ÇIKIYOR, bankaya gidiyor                │
 *   └────────────────────────────────────────────────────────┘
 *              ⋮  (dakikalar · belki hiç dönmez)
 *   ┌ webhookuDogrula() + webhookuCoz() ─────────────────────┐
 *   │ sağlayıcının SUNUCUSU haber veriyor — GERÇEK BUDUR     │
 *   └────────────────────────────────────────────────────────┘
 *
 * ⚠️ `tahsilEt()` gibi TEK ADIMLI bir metot BİLEREK YOK. Öyle bir metot
 * "çağır, cevabı al, işle" yanılsaması üretirdi; oysa cevap o çağrıdan
 * dönmüyor, dakikalar sonra başka bir istekle geliyor. Arayüz gerçeği
 * yansıtmazsa üstüne yazılan her kod yanlış varsayımla yazılır.
 *
 * ⚠️ Burada `sonucuDogrula()` (callback okuma) metodu da YOK — bilerek.
 * Tarayıcının geri dönüşü ödeme kanıtı değildir; iyzico kendi belgesinde
 * "callback güvenilir gösterge değildir, kullanıcı o ekrana hiç
 * ulaşmayabilir" diyor. Callback ucu yalnızca siparişin O ANKİ durumunu
 * okur — sağlayıcıya hiç sormaz (1E-K1).
 */
interface PaymentProvider
{
    /** `payments.provider` kolonuna yazılan ad: 'fake' · 'iyzico'. */
    public function ad(): string;

    /**
     * Bu sağlayıcının çalışmak için ihtiyaç duyduğu ayar anahtarları.
     *
     * ★ Sağlayıcı kendi ihtiyacını BİLDİRİYOR — panel onu okuyup eksikleri
     * gösteriyor (1E-K11). `StoreReadiness` deseninin aynısı.
     *
     * ⚠️ Bu liste olmasaydı ayar ucu serbest biçimli kalırdı:
     * `iyzico_api_key` yerine `iyzico_api` yazan marka HATA ALMAZ, anahtar
     * hiçbir zaman okunmayan bir yere yazılır ve ödeme "yapılandırılmış"
     * görünürken çalışmazdı. Hata da ancak ilk gerçek müşteride görülürdü.
     *
     * @return list<string>
     */
    public function gerekliAnahtarlar(): array;

    /**
     * Ödemeyi başlatır ve müşterinin yönlendirileceği adresi döndürür.
     *
     * ⚠️ TUTAR PARAMETRE DEĞİL — `PaymentService` onu `orders.grand_total`
     * üzerinden veriyor ve istemciden gelen hiçbir tutara bakılmıyor.
     * Sağlayıcı arayüzü tutarı istemciden alsaydı, bir gün bir uç onu
     * istekten geçirir ve müşteri kendi fiyatını belirlerdi.
     *
     * ⚠️ `$idempotanslikAnahtari` sağlayıcıya GİDİYOR: aynı anahtarla
     * ikinci istek gelirse sağlayıcı yeni çekim yapmaz, ilkinin sonucunu
     * döndürür. Müşterinin "öde"ye iki kez basmasına karşı tek korumamız
     * bu (1E-K4) — veritabanı kısıtı burada işe yaramaz, çünkü sağlayıcı
     * iki FARKLI işlem numarası üretir.
     */
    public function baslat(PaymentRequest $istek): PaymentInitiation;

    /**
     * İmzanın taşındığı HTTP başlığının adı.
     *
     * ⚠️ Arayüzde duruyor çünkü her sağlayıcı başka bir başlık kullanıyor
     * (iyzico: `X-IYZ-SIGNATURE-V3`). Controller'a sabit yazılsaydı ikinci
     * sağlayıcı takıldığı gün imza HİÇ OKUNAMAZ, doğrulama her istekte
     * başarısız olur ve tek bir ödeme bile işlenmezdi.
     */
    public function imzaBasligi(): string;

    /**
     * Webhook imzasını doğrular.
     *
     * ⚠️ Bu uç kimlik doğrulamasız olmak ZORUNDA — sağlayıcı bizim
     * token'ımızı bilmez. Tek koruma imza. `false` dönerse hiçbir kayıt
     * açılmaz, hiçbir stok hareketi olmaz; aksi hâlde herkes sahte istek
     * atıp bedava sipariş oluşturur.
     *
     * @param  array<string, mixed>  $yuk
     */
    public function webhookuDogrula(array $yuk, ?string $imza): bool;

    /**
     * Doğrulanmış webhook yükünü bizim dilimize çevirir.
     *
     * ⚠️ Yalnızca imza doğrulandıktan SONRA çağrılır.
     *
     * @param  array<string, mixed>  $yuk
     */
    public function webhookuCoz(array $yuk): PaymentOutcome;
}
