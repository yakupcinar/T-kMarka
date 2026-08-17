<?php

namespace App\Platform\Subscription;

use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;

/**
 * Abonelik sağlayıcısı — MARKADAN tahsilat. (3E)
 *
 * ⚠️ 1E'deki `PaymentProvider` ile KARIŞTIRILMAMALI. İkisi zıt yönde çalışıyor:
 *
 * ```
 * 1E  marka → kendi iyzico hesabıyla → KENDİ MÜŞTERİSİNDEN tahsil
 *     anahtarlar MARKA settings'inde, her markada AYRI
 *
 * 3E  BİZ  → kendi iyzico hesabımızla → MARKADAN tahsil
 *     anahtar MERKEZDE, TEK                              ← burası
 * ```
 *
 * ⚠️ İkisi tek arayüzde birleştirilseydi anahtarların hangisinin
 * kullanılacağı çağrı yerine bağlı kalırdı; bir gün biri karıştırır ve
 * markanın parası bize, bizim paramız markaya giderdi.
 *
 * ★ Şekli 1E'den öğrenilenle aynı: tek adımlı `tahsilEt()` YOK. Abonelik
 * ödemeleri AYLARCA sonra, biz istekte bulunmadan gerçekleşiyor; gerçek
 * sonuç webhook'la geliyor.
 */
interface SubscriptionProvider
{
    /** `tenants.subscription_ref` alanına yazılan sağlayıcı adı. */
    public function ad(): string;

    /**
     * Abonelik başlatır ve sağlayıcıdaki referansını döndürür.
     *
     * ⚠️ KART ZORUNLU — iyzico aboneliği bir ödeme isteğiyle başlıyor.
     * Kartsız deneme bu yüzden BİZDE tutuluyor (3-K3) ve bu metot ancak
     * deneme bitip marka kart girince çağrılıyor.
     *
     * ⚠️ Kart verisi BURADAN GEÇİYOR ama HİÇBİR YERE YAZILMIYOR: saklamak
     * bizi PCI kapsamına sokardı. Saklayan taraf sağlayıcı.
     *
     * @param  array<string, string>  $kart
     *
     * @throws SubscriptionProviderException
     */
    public function baslat(Tenant $marka, Plan $plan, array $kart): string;

    /**
     * Aboneliği iptal eder.
     *
     * ⚠️ İptal SAĞLAYICIDA da yapılmak zorunda. Yalnızca kendi kaydımızı
     * kapatsaydık iyzico her ay çekmeye devam ederdi — marka ayrıldığını
     * sanarken parası gitmeye devam ederdi.
     *
     * @throws SubscriptionProviderException
     */
    public function iptal(string $referans): void;

    /**
     * Sağlayıcıdaki GERÇEK durumu sorar.
     *
     * ★ 1E-K12'nin tekrarı: bildirim imzasız ya da şüpheli olduğunda gerçek
     * buradan öğreniliyor. "Cevabın durumuna değil SONUCUNA bak."
     *
     * @throws SubscriptionProviderException
     */
    public function sorgula(string $referans): SubscriptionState;

    /**
     * Gelen bildirimi doğrular.
     *
     * ⚠️ Doğrulanmamış bildirim kabul edilirse herkes "ödeme başarılı"
     * diyebilir ve bedava abonelik açılırdı.
     *
     * @param  array<string, mixed>  $govde
     */
    public function webhookuDogrula(array $govde, string $imza): bool;

    /**
     * Bildirimi anlamlı bir sonuca çevirir.
     *
     * @param  array<string, mixed>  $govde
     */
    public function webhookuCoz(array $govde): SubscriptionOutcome;
}
