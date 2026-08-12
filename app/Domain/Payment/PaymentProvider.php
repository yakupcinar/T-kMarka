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
     * Dönüş isteğinden sağlayıcı referansını çıkarır. (1E.5)
     *
     * ★ Arayüzde olmasının sebebi ÖLÇÜLDÜ: her sağlayıcı referansı başka
     * yerde taşıyor. Sahte sağlayıcı yönlendirme adresine `?ref=` koyuyor,
     * iyzico ise callback'e `token` alanını **POST gövdesinde** yolluyor.
     *
     * ⚠️ 1E.7.3'te gerçek sandbox'ta yakalandı: dönüş ucu yalnızca
     * `?ref=` okuyordu ve iyzico'nun üç callback denemesi de 404 aldı.
     * Müşteri ödemeyi bitirdikten sonra "sayfa bulunamadı" gördü.
     * Sahte sağlayıcı bunu gizlemişti — çünkü adresi kendisi üretiyordu.
     *
     * @param  array<string, mixed>  $veri  sorgu + gövde birleşimi
     */
    public function donusReferansi(array $veri): ?string;

    /**
     * İmzanın taşındığı HTTP başlık adları — ÖNCELİK SIRASIYLA.
     *
     * ⚠️ Arayüzde duruyor çünkü her sağlayıcı başka bir başlık kullanıyor
     * (iyzico: `X-IYZ-SIGNATURE-V3`). Controller'a sabit yazılsaydı ikinci
     * sağlayıcı takıldığı gün imza HİÇ OKUNAMAZ, doğrulama her istekte
     * başarısız olur ve tek bir ödeme bile işlenmezdi.
     *
     * ⚠️ LİSTE, tek ad değil: iyzico sandbox'ta eski adı (`X-Iyz-Signature`)
     * da gönderiyor. Tek ada bağlansaydık gelen bildirimi hiç doğrulayamaz,
     * hepsini reddederdik.
     *
     * ⚠️ BOŞ başlık imza SAYILMAZ — controller boş olanları atlıyor ve
     * hiçbiri yoksa `null` geçiyor, doğrulama da `false` dönüyor.
     *
     * @return list<string>
     */
    public function imzaBasliklari(): array;

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
     * ★ Ödemenin TUTARINI doğrular — gerekirse SAĞLAYICIYA SORARAK. (1E-K9)
     *
     * ⚠️ Ayrı metot olmasının sebebi: `webhookuCoz()` saf bir çeviri işi,
     * ağa çıkmıyor. Sorgu oraya gömülseydi "bu metot ne kadar sürer,
     * düşerse ne olur" sorusu görünmez olurdu.
     *
     * ⚠️ Neden gerekli: sahte sağlayıcının bildiriminde tutar var, ama
     * iyzico'nun HPP bildiriminde YOK — yalnızca ödeme kimliği ve durum
     * geliyor. Tutar doğrulaması 1E.4'te "imzaya rağmen ikinci savunma"
     * diye konmuştu; gerçek sağlayıcıda onu kaybetmemek için sorulacak.
     *
     * Bildirimde tutar zaten varsa sağlayıcı onu döndürür, ağa çıkmaz.
     *
     * @return numeric-string
     */
    public function tutariDogrula(PaymentOutcome $sonuc): string;

    /**
     * Doğrulanmış webhook yükünü bizim dilimize çevirir.
     *
     * ⚠️ Yalnızca imza doğrulandıktan SONRA çağrılır.
     *
     * @param  array<string, mixed>  $yuk
     */
    public function webhookuCoz(array $yuk): PaymentOutcome;
}
