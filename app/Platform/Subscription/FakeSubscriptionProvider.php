<?php

namespace App\Platform\Subscription;

use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Geliştirme ve test sağlayıcısı. (3E)
 *
 * ⚠️ 1E'nin dersi: sahte sağlayıcı GERÇEĞİ TAKLİT ETMELİ, kolaylık
 * sağlamamalı. 1E.7'de sahte cevapta token yoktu ve `status` kontrolü hiç
 * sınanmıyordu — test yeşildi, gerçek sağlayıcıda patladı.
 *
 * Bu yüzden burada da:
 *   · referans RASTGELE (türetilebilir olsaydı testler tahmin ederdi)
 *   · imza GERÇEKTEN hesaplanıyor (boş anahtarla imzalama YASAK)
 *   · durum sağlayıcı tarafında TUTULUYOR — `sorgula()` gerçek cevap versin
 */
class FakeSubscriptionProvider implements SubscriptionProvider
{
    /**
     * ⚠️ Anahtar MERKEZ yapılandırmadan geliyor, marka ayarlarından DEĞİL:
     * bu sağlayıcı BİZİM tahsilatımız için (3E), markanın değil (1E).
     */
    public function __construct(private readonly string $gizliAnahtar) {}

    public function ad(): string
    {
        return 'fake';
    }

    public function baslat(Tenant $marka, Plan $plan, array $kart): string
    {
        /*
        | ⚠️ Kart alanları YOKLANIYOR ama SAKLANMIYOR. Gerçek sağlayıcı da
        | eksik kartı reddediyor; sahte sağlayıcı kabul etseydi testler
        | "kart göndermeyi unutan" bir kodu yeşil geçirirdi.
        */
        foreach (['number', 'holder', 'expiry', 'cvc'] as $alan) {
            if (($kart[$alan] ?? '') === '') {
                throw new SubscriptionProviderException("Kart bilgisi eksik: {$alan}");
            }
        }

        $referans = 'FAKESUB-'.Str::upper(Str::random(16));

        Cache::put($this->anahtar($referans), SubscriptionState::Active->value, now()->addDays(400));

        return $referans;
    }

    public function iptal(string $referans): void
    {
        Cache::put($this->anahtar($referans), SubscriptionState::Canceled->value, now()->addDays(400));
    }

    public function sorgula(string $referans): SubscriptionState
    {
        $deger = Cache::get($this->anahtar($referans));

        if (! is_string($deger)) {
            throw new SubscriptionProviderException("Abonelik bulunamadı: {$referans}");
        }

        return SubscriptionState::from($deger);
    }

    public function webhookuDogrula(array $govde, string $imza): bool
    {
        /*
        | ⚠️ BOŞ ANAHTARLA İMZALAMA YASAK — 1E.7'de ölçüldü:
        | `hash_hmac(..., '')` geçerli GÖRÜNEN bir imza üretiyor ve
        | doğrulama hiçbir şeyi korumuyordu.
        */
        if ($this->gizliAnahtar === '') {
            /*
            | ⚠️ AYRI İSTİSNA — sorumlu taraf farklı. `Provider`
            | istisnası olsaydı webhook 400 döner ve "senin gönderdiğin
            | bozuk" derdi; oysa sorun BİZİM yapılandırmamızda.
            */
            throw new MissingSubscriptionSecretException('Abonelik imza anahtarı yapılandırılmamış.');
        }

        return hash_equals($this->imzala($govde), $imza);
    }

    public function webhookuCoz(array $govde): SubscriptionOutcome
    {
        $referans = (string) ($govde['subscriptionReference'] ?? '');

        if ($referans === '') {
            throw new SubscriptionProviderException('Bildirimde abonelik referansı yok.');
        }

        $durum = SubscriptionState::tryFrom((string) ($govde['status'] ?? ''))
            ?? throw new SubscriptionProviderException('Bildirimde geçersiz durum.');

        /*
        | ⚠️ Sağlayıcının durumu KENDİ tarafında da güncelleniyor ki
        | `sorgula()` bildirimle tutarlı cevap versin. Güncellenmeseydi
        | "bildirim başarısız dedi ama sorgu aktif diyor" gibi gerçekte
        | olmayan bir çelişki testlerde görünmezdi.
        */
        Cache::put($this->anahtar($referans), $durum->value, now()->addDays(400));

        return new SubscriptionOutcome(
            referans: $referans,
            durum: $durum,
            tutar: isset($govde['price']) ? (string) $govde['price'] : null,
        );
    }

    /**
     * Test ve geliştirme için imza üretir.
     *
     * @param  array<string, mixed>  $govde
     */
    public function imzala(array $govde): string
    {
        if ($this->gizliAnahtar === '') {
            throw new MissingSubscriptionSecretException('Abonelik imza anahtarı yapılandırılmamış.');
        }

        ksort($govde);

        return hash_hmac('sha256', (string) json_encode($govde), $this->gizliAnahtar);
    }

    private function anahtar(string $referans): string
    {
        /*
        | ⚠️ Önbellek anahtarı MARKA ETİKETİ TAŞIMIYOR — bilerek. Bu
        | sağlayıcı merkez tarafta çalışıyor; kiracı etiketi eklenseydi
        | (0.5'in 2. tuzağı) merkez bağlamda okunamazdı.
        */
        return 'sub:fake:'.$referans;
    }
}
