<?php

namespace App\Platform\Subscription;

use App\Enums\TenantStatus;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use App\Platform\TenantLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Abonelik iş akışı. (3E)
 *
 * ★ ZAMAN ÇİZGİSİ:
 *
 * ```
 * kayıt ──▶ trial (14 gün, KARTSIZ)
 *             │
 *             │ marka kart girer
 *             ▼
 *           active ◀────────────┐
 *             │                 │ ödeme düzeldi
 *             │ ödeme başarısız │
 *             ▼                 │
 *           past_due (7 gün) ───┘
 *             │
 *             │ nezaket doldu
 *             ▼
 *           suspended     panel kapalı · VİTRİN AÇIK
 * ```
 *
 * ⚠️ Durum geçişlerini bu sınıf KENDİ yazmıyor, [TenantLifecycle]'a
 * veriyor: geçiş kuralları (kapalı liste) orada ve iki yerde tekrarlanırsa
 * ayrışırlar.
 */
class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionProvider $saglayici,
        private readonly TenantLifecycle $yasam,
    ) {}

    /**
     * Marka kart girer, abonelik başlar.
     *
     * @param  array<string, string>  $kart
     *
     * @throws SubscriptionProviderException|AlreadySubscribedException
     */
    public function baslat(Tenant $marka, Plan $plan, array $kart): Tenant
    {
        if ($marka->subscription_ref !== null) {
            /*
            | ⚠️ İkinci abonelik AÇILAMAZ. Açılabilseydi marka iki kez
            | ücretlendirilir ve ilk abonelik sağlayıcıda öksüz kalırdı —
            | kimse iptal etmediği için her ay çekmeye devam ederdi.
            */
            throw new AlreadySubscribedException('Bu markanın zaten bir aboneliği var.');
        }

        $referans = $this->saglayici->baslat($marka, $plan, $kart);

        /*
        | ⚠️ Referans ÖNCE yazılıyor, durum sonra. Sıra tersine olsaydı ve
        | araya bir hata girseydi marka `active` görünür ama sağlayıcıdaki
        | aboneliğe bağı KOPUK olurdu: iptal edemez, sorgulayamazdık.
        */
        DB::transaction(function () use ($marka, $plan, $referans): void {
            $marka->subscription_ref = $referans;
            $marka->plan()->associate($plan);
            $marka->save();

            $this->yasam->gecir($marka, TenantStatus::Active);

            /*
            | ⚠️ Deneme bitişi TEMİZLENİYOR. Kalsaydı "denemesi bitmiş
            | markaları askıya al" görevi ödeyen markayı da toplardı.
            */
            $marka->trial_ends_at = null;
            $marka->save();
        });

        return $marka;
    }

    /**
     * Abonelik iptal edilir.
     *
     * @throws SubscriptionProviderException
     */
    public function iptal(Tenant $marka): Tenant
    {
        $referans = $marka->subscription_ref;

        if ($referans !== null) {
            /*
            | ★ SAĞLAYICIDA DA İPTAL EDİLİYOR — önce.
            |
            | ⚠️ Yalnızca kendi kaydımızı kapatsaydık iyzico her ay
            | çekmeye devam ederdi: marka ayrıldığını sanarken parası
            | gitmeye devam ederdi. Bu, sessiz hatanın en pahalı türü.
            */
            $this->saglayici->iptal($referans);
        }

        $marka->subscription_ref = null;
        $marka->save();

        return $this->yasam->gecir($marka, TenantStatus::Closed);
    }

    /**
     * Sağlayıcıdan gelen bildirimi işler.
     *
     * ★ GERÇEK BURADAN GELİYOR — 1E'nin dersi. Abonelik ödemeleri aylarca
     * sonra, biz istekte bulunmadan gerçekleşiyor.
     *
     * @throws SubscriptionProviderException
     */
    public function bildirimiIsle(SubscriptionOutcome $sonuc): ?Tenant
    {
        $marka = Tenant::where('subscription_ref', $sonuc->referans)->first();

        if ($marka === null) {
            /*
            | ⚠️ Bilinmeyen referans HATA DEĞİL. Sağlayıcı iptal edilmiş
            | bir aboneliğin son bildirimini geç gönderebiliyor; 500
            | dönseydi sağlayıcı tekrar tekrar denerdi (1E.6'da webhook
            | zinciri tam böyle kırılmıştı).
            */
            Log::info('Bilinmeyen abonelik referansı', ['ref' => $sonuc->referans]);

            return null;
        }

        return match ($sonuc->durum) {
            SubscriptionState::Active => $this->odemeBasarili($marka),
            SubscriptionState::Unpaid => $this->odemeBasarisiz($marka),
            SubscriptionState::Canceled, SubscriptionState::Expired => $this->saglayiciIptalEtti($marka),

            /*
            | ⚠️ `Pending` (duraklatılmış) bizde karşılığı OLMAYAN bir
            | durum. Sessizce `active` sayılsaydı ödeme alınmayan bir marka
            | ödüyormuş gibi görünürdü. Dokunmuyoruz ve kaydediyoruz.
            */
            SubscriptionState::Pending => $this->bilinmeyenDurum($marka, $sonuc),
        };
    }

    /** Ödeme alındı — nezaket süresindeyse geri dönüyor. */
    private function odemeBasarili(Tenant $marka): Tenant
    {
        if ($marka->status === TenantStatus::Active) {
            return $marka;
        }

        return $this->yasam->gecir($marka, TenantStatus::Active);
    }

    /**
     * Ödeme alınamadı — nezaket süresi başlıyor.
     *
     * ⚠️ ASKIYA ALMIYOR. Çoğu başarısız ödeme kasıtlı değil (kart
     * yenilenmiş, limit dolmuş); ilk günde kapatmak müşteriyi kaybetmenin
     * en hızlı yolu. Askı `abonelik:nezaket-denetle` görevinde, süre
     * dolduktan sonra.
     */
    private function odemeBasarisiz(Tenant $marka): Tenant
    {
        /*
        | ⚠️ "Zaten `past_due` ise dokunma" kontrolü BURADA YOK — ÖLÇÜLDÜ,
        | ölü koddu: [TenantLifecycle::gecir()] aynı duruma geçişte zaten
        | erken dönüyor ve tarihleri yeniden yazmıyor.
        |
        | Kırma denemesinde kontrol kaldırıldı ve HİÇBİR test düşmedi —
        | yani hiçbir şey korumuyordu. 2F'deki `whereNotNull('email')`
        | ölü savunmasının aynısı.
        |
        | Korumanın gerçek yeri `gecir()`; oradaki testi ölçüyor.
        */
        return $this->yasam->gecir($marka, TenantStatus::PastDue);
    }

    private function saglayiciIptalEtti(Tenant $marka): Tenant
    {
        /*
        | ⚠️ Sağlayıcı iptal ettiyse referans TEMİZLENİYOR: artık geçerli
        | bir abonelik yok. Kalsaydı "aboneliği var" sanıp yeni abonelik
        | açılmasını engellerdik.
        */
        $marka->subscription_ref = null;
        $marka->save();

        return $this->yasam->gecir($marka, TenantStatus::Suspended);
    }

    private function bilinmeyenDurum(Tenant $marka, SubscriptionOutcome $sonuc): Tenant
    {
        Log::warning('Abonelikte karşılığı olmayan durum', [
            'tenant' => $marka->id,
            'state' => $sonuc->durum->value,
        ]);

        return $marka;
    }

    /**
     * Sağlayıcıdaki gerçek durumla kendi kaydımızı karşılaştırır.
     *
     * ★ 1E-K12 ve `committed`/`rating_avg` denetimlerinin aynısı:
     * materyalleştirilmiş bir durumun bedeli denetimdir.
     *
     * ⚠️ ONARMIYOR, haber veriyor.
     *
     * @return list<array{tenant: string, bizdeki: string, saglayicida: string}>
     */
    public function tutarsizliklar(): array
    {
        $sonuc = [];

        foreach (Tenant::whereNotNull('subscription_ref')->cursor() as $marka) {
            $referans = (string) $marka->subscription_ref;

            try {
                $gercek = $this->saglayici->sorgula($referans);
            } catch (SubscriptionProviderException $e) {
                $sonuc[] = [
                    'tenant' => (string) $marka->id,
                    'bizdeki' => $marka->status instanceof TenantStatus ? $marka->status->value : 'bilinmeyen',
                    'saglayicida' => 'SORULAMADI: '.$e->getMessage(),
                ];

                continue;
            }

            $beklenen = match ($gercek) {
                SubscriptionState::Active => TenantStatus::Active,
                SubscriptionState::Unpaid => TenantStatus::PastDue,
                SubscriptionState::Canceled, SubscriptionState::Expired => TenantStatus::Suspended,
                SubscriptionState::Pending => null,
            };

            /*
            | ⚠️ `past_due` ile `suspended` FARK SAYILMIYOR: nezaket süresi
            | dolmuş bir marka bizde askıda, sağlayıcıda hâlâ `unpaid`
            | olabiliyor. İkisi de "ödenmemiş" ailesinden.
            */
            $kabul = $beklenen === TenantStatus::PastDue
                ? [TenantStatus::PastDue, TenantStatus::Suspended]
                : [$beklenen];

            if ($beklenen !== null && ! in_array($marka->status, $kabul, true)) {
                $sonuc[] = [
                    'tenant' => (string) $marka->id,
                    'bizdeki' => $marka->status instanceof TenantStatus ? $marka->status->value : 'bilinmeyen',
                    'saglayicida' => $gercek->value,
                ];
            }
        }

        return $sonuc;
    }
}
