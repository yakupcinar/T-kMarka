<?php

namespace App\Platform;

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\EmailNormalizer;
use App\Domain\Settings\DefaultSettings;
use App\Enums\TenantStatus;
use App\Models\User;
use App\Platform\Models\Tenant;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Throwable;

/**
 * Marka açma — TEK YOL. (3D)
 *
 * ★ Hem `tenant:create` komutu hem self-servis kayıt ucu BURAYI çağırıyor.
 *
 * ⚠️ İki ayrı yol yazılsaydı ayrışırlardı ve bu SESSİZ olurdu: 1E.4'te
 * `markaKur` ile `tenant:create` ayrışmış, testler gerçekte var olmayan bir
 * markayı ölçmüştü. Aynı hatayı burada baştan kapatıyoruz.
 *
 * ⚠️ ÖLÇÜLDÜ — marka açmak KUYRUK GEREKTİRMİYOR:
 * ```
 * şema + 28 migration : 240 ms
 * varsayılanlar       :  39 ms
 * ```
 * Plan "şema açma uzun sürer, kuyruğa al" diyordu; ölçüm bunu yanlışladı.
 * Senkron akış hem daha basit hem de kullanıcıya "hazır" diyebilmenin tek
 * dürüst yolu — kuyrukta olsaydı kayıt biter, mağaza henüz olmazdı.
 */
class TenantProvisioning
{
    /**
     * Haftada açılabilecek en fazla marka.
     *
     * ★ 3-K5'in uygulaması. Let's Encrypt kayıtlı alan adı başına HAFTADA
     * 50 sertifika veriyor ve `*.tikmarka.com` altındaki her marka aynı
     * kotadan yiyor.
     *
     * ⚠️ TAVAN SESSİZ OLMAMALI. Sınır aşıldığında marka yine açılsaydı
     * kayıt "başarılı" görünür, panel çalışır, ama SİTE AÇILMAZDI — bugünkü
     * Caddyfile tuzağının ölçekli hâli. Bu yüzden açıkça reddediliyor.
     *
     * ⚠️ 50 değil 45: sertifika yenilemeleri ayrı kotada ama elle açılan
     * markalar ve olası tekrar denemeler için pay bırakılıyor.
     */
    public const HAFTALIK_TAVAN = 45;

    /**
     * Yeni marka açar.
     *
     * @param  string  $ad  markanın görünen adı
     * @param  string  $alanAdi  tam alan adı (marka-a.localhost)
     * @param  string  $sahipEposta  sahip kullanıcının e-postası
     * @param  string  $sahipParola  sahip kullanıcının parolası
     *
     * @throws DomainUnavailableException|WeeklyLimitReachedException
     */
    public function ac(string $ad, string $alanAdi, string $sahipEposta, string $sahipParola): Tenant
    {
        $ad = trim($ad);
        $alanAdi = strtolower(trim($alanAdi));

        $this->alanAdiniDogrula($alanAdi);
        $this->tavaniDogrula();

        /*
        | ★ `provisioning` DURUMUNDA doğuyor.
        |
        | ⚠️ Senkron akışta bu durum saniyenin altında yaşıyor — ama HATA
        | OLURSA kalıcı iz bırakıyor. Doğrudan `trial` yazılsaydı yarıda
        | kalan bir marka "denemede" görünür ve çalışmadığı fark edilmezdi.
        */
        $marka = Tenant::create([
            'name' => $ad,
            'status' => TenantStatus::Provisioning,
        ]);

        try {
            $marka->domains()->create(['domain' => $alanAdi]);

            $eposta = (string) EmailNormalizer::normallestir($sahipEposta);

            $marka->run(function () use ($ad, $eposta, $sahipParola): void {
                (new DefaultRoles)->kur();

                User::create([
                    'name' => $ad.' Sahibi',
                    'email' => $eposta,
                    'password' => $sahipParola,
                ])->forceFill(['is_owner' => true])->save();

                app()->make(DefaultSettings::class)->kur($ad);
            });
        } catch (Throwable $e) {
            /*
            | ⚠️ YARIM KALAN MARKA TEMİZLENİYOR. Bırakılsaydı şeması olan
            | ama alan adı ya da sahibi olmayan bir kayıt kalırdı — 1A.1'de
            | tam bu yaşandı ve "komut başarılı, site açılmıyor" oldu.
            |
            | `delete()` şemayı da düşürüyor.
            */
            $marka->delete();

            throw $e;
        }

        /*
        | ⚠️ Kurulum BİTTİKTEN SONRA denemeye alınıyor. Önce yazılsaydı
        | kurulum patladığında marka "denemede" kalırdı.
        */
        $marka->status = TenantStatus::Trial;
        $marka->trial_ends_at = now()->addDays(self::DENEME_GUN);
        $marka->save();

        return $marka;
    }

    /**
     * Ücretsiz deneme süresi — KART İSTENMEDEN.
     *
     * ⚠️ Deneme BİZDE tutuluyor, iyzico'da değil: abonelik başlatmak kart
     * gerektiriyor ve kartsız kayıt istiyoruz (3-K3).
     */
    public const DENEME_GUN = 14;

    /**
     * Marka adından alt alan adı üretir — çakışmada sonek ekler.
     *
     * ⚠️ Türkçe karakterler ölçüldü: `Işıl Takı` → `isil-taki`,
     * `ÇİÇEK Dünyası` → `cicek-dunyasi`. Doğru çalışıyor.
     *
     * ⚠️ Ama farklı iki ad AYNI slug'a düşebiliyor (`Işıl` ve `İsil`);
     * bu yüzden çakışma yönetimi ZORUNLU — ürün slug'ında olduğu gibi
     * sonek ekleniyor.
     */
    public function altAlanAdiUret(string $markaAdi, string $kokAlanAdi): string
    {
        $taban = Str::slug($markaAdi);

        if ($taban === '') {
            $taban = 'marka';
        }

        /*
        | ⚠️ Ayrılmış adlar da SONEK alıyor, reddedilmiyor: markanın adı
        | gerçekten "Panel" olabilir. `panel` yasak ama `panel-2` değil.
        */
        $aday = ReservedSubdomains::ayrilmisMi($taban) ? $taban.'-magaza' : $taban;

        $sayac = 2;

        while (Domain::where('domain', $aday.'.'.$kokAlanAdi)->exists()) {
            $aday = $taban.'-'.$sayac;
            $sayac++;
        }

        return $aday.'.'.$kokAlanAdi;
    }

    /** @throws DomainUnavailableException */
    private function alanAdiniDogrula(string $alanAdi): void
    {
        if ($alanAdi === '') {
            throw new DomainUnavailableException('Alan adı boş olamaz.');
        }

        if (Domain::where('domain', $alanAdi)->exists()) {
            /*
            | ⚠️ Veritabanında UNIQUE kısıtı da var; bu kontrol onun YERİNE
            | değil ÖNÜNDE. Kısıta bırakılsaydı kullanıcı 500 görürdü.
            */
            throw new DomainUnavailableException('Bu alan adı zaten kullanımda.');
        }

        /*
        | ⚠️ Alt alan adının İLK parçası kontrol ediliyor:
        | `panel.tikmarka.com` → `panel`. Tam alan adı kontrol edilseydi
        | ayrılmış adlar hiç yakalanmazdı.
        */
        $ilkParca = explode('.', $alanAdi)[0];

        if (ReservedSubdomains::ayrilmisMi($ilkParca)) {
            throw new DomainUnavailableException(sprintf('"%s" ayrılmış bir addır, kullanılamaz.', $ilkParca));
        }
    }

    /** @throws WeeklyLimitReachedException */
    private function tavaniDogrula(): void
    {
        /*
        | ★ 3-K5 — SERTİFİKA KOTASI. Ölçülebilir bir sınır ve aşıldığında
        | GÜRÜLTÜLÜ: marka açılmıyor.
        |
        | ⚠️ `created_at` MERKEZ kayıtta ve artık `timestamptz` (3B) —
        | ofissiz olsaydı bu karşılaştırma oturumun saat dilimine göre
        | kayardı (CLAUDE.md'nin 3. kuralı).
        */
        $buHafta = Tenant::where('created_at', '>=', now()->subDays(7))->count();

        if ($buHafta >= self::HAFTALIK_TAVAN) {
            throw new WeeklyLimitReachedException(sprintf(
                'Bu hafta açılabilecek marka sınırına ulaşıldı (%d). Sertifika kotası dolduğu için yeni marka HTTPS\'e çıkamaz.',
                self::HAFTALIK_TAVAN,
            ));
        }
    }
}
