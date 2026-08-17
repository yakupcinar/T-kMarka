<?php

namespace App\Platform\Domains;

use App\Platform\DomainUnavailableException;
use App\Platform\Models\Domain;
use App\Platform\Models\Tenant;
use App\Platform\ReservedSubdomains;
use Illuminate\Support\Str;

/**
 * Markanın kendi alan adını bağlaması. (3H)
 *
 * ★ 6 NUMARALI KARAR: DNS'i MARKA ekler, BİZ kontrol ederiz.
 *
 * ```
 * 1  marka panelde alan adını yazar        → kayıt, verified_at = null
 * 2  biz talimat veririz                   → CNAME / A kaydı
 * 3  marka kendi DNS panelinde ekler       ← BİZİM erişimimiz YOK
 * 4  marka "kontrol et" der                → DNS sorgusu
 * 5  doğruysa verified_at dolar            → ask ucu artık 200 diyor
 * 6  ilk ziyarette Caddy sertifika alır    (on-demand TLS)
 * ```
 *
 * ⚠️ 3. ADIM İNSAN İŞİ ve destek yükünün tamamı orada. Bu yüzden talimat
 * ve kontrol sonucu AÇIKÇA dönüyor — sessizce beklemek "ekledim ama
 * çalışmıyor" çağrısı demek.
 */
class CustomDomainService
{
    public function __construct(private readonly DnsChecker $dns) {}

    /**
     * Markaya özel alan adı ekler — DOĞRULANMAMIŞ olarak.
     *
     * @throws DomainUnavailableException
     */
    public function ekle(Tenant $marka, string $alanAdi): Domain
    {
        $alanAdi = strtolower(trim($alanAdi));

        $this->dogrulanabilirMi($alanAdi);

        $kayit = new Domain;
        $kayit->domain = $alanAdi;
        $kayit->tenant_id = (string) $marka->id;

        /*
        | ⚠️ Belirteç ALAN ADI BAŞINA rastgele. Sabit olsaydı bir markanın
        | belirtecini gören başkası kendi alan adını doğrulatabilirdi.
        */
        $kayit->verification_token = 'tikmarka-dogrulama='.Str::lower(Str::random(32));

        /*
        | ⚠️ `verified_at` BOŞ doğuyor. Dolu doğsaydı `ask` ucu o alan adına
        | 200 der, Caddy sertifika istemeye çalışır, DNS bize bakmadığı için
        | ACME düşer ve Let's Encrypt kotamız yanardı.
        */
        $kayit->save();

        return $kayit;
    }

    /**
     * Markanın DNS'i gerçekten bize bakıyor mu?
     *
     * ⚠️ ÜÇ YOLDAN BİRİ yeterli — marka sağlayıcısının izin verdiğini
     * kullanabilsin:
     *   CNAME  → bizim bağlantı adresimize
     *   A      → bizim IP'mize
     *   TXT    → doğrulama belirteci (kök alan adında CNAME yasak olduğu
     *            için bazı sağlayıcılarda tek yol bu)
     */
    public function dogrula(Domain $kayit): bool
    {
        $kayitlar = $this->dns->kayitlar($kayit->domain);

        $hedefCname = strtolower((string) config('tenancy.custom_domain_cname', ''));
        $hedefIp = (string) config('tenancy.custom_domain_ip', '');
        $belirtec = (string) $kayit->verification_token;

        $eslesti = ($hedefCname !== '' && in_array($hedefCname, $kayitlar['cname'], true))
            || ($hedefIp !== '' && in_array($hedefIp, $kayitlar['a'], true))
            || ($belirtec !== '' && in_array($belirtec, $kayitlar['txt'], true));

        if (! $eslesti) {
            return false;
        }

        /*
        | ⚠️ Doğrulama tarihi BİR KEZ yazılıyor. Her kontrolde tazelenseydi
        | "ne zaman doğrulandı" bilgisi bugüne kayar ve destek "bu alan adı
        | ne zamandır çalışıyor" sorusunu cevaplayamazdı.
        */
        if ($kayit->verified_at === null) {
            $kayit->verified_at = now();
            $kayit->save();
        }

        return true;
    }

    /**
     * Markanın DNS panelinde ekleyeceği kayıtlar.
     *
     * @return array<string, array<string, string>>
     */
    public function talimat(Domain $kayit): array
    {
        return [
            'cname' => [
                'type' => 'CNAME',
                'name' => $kayit->domain,
                'value' => (string) config('tenancy.custom_domain_cname', ''),
            ],
            'a' => [
                'type' => 'A',
                'name' => $kayit->domain,
                'value' => (string) config('tenancy.custom_domain_ip', ''),
            ],
            'txt' => [
                'type' => 'TXT',
                'name' => $kayit->domain,
                'value' => (string) $kayit->verification_token,
            ],
        ];
    }

    /** @throws DomainUnavailableException */
    private function dogrulanabilirMi(string $alanAdi): void
    {
        if ($alanAdi === '' || ! str_contains($alanAdi, '.')) {
            throw new DomainUnavailableException('Geçerli bir alan adı girin.');
        }

        if (Domain::where('domain', $alanAdi)->exists()) {
            throw new DomainUnavailableException('Bu alan adı zaten kayıtlı.');
        }

        /*
        | ⚠️ MERKEZ alan adlarımız alınamaz. Alınabilseydi marka kendi
        | paneline `localhost` ya da `tikmarka.com` yazar ve kapı görevlisi
        | merkez isteklerini o markaya yönlendirirdi — kontrol düzlemimizi
        | kaybederdik.
        */
        $merkez = (array) config('tenancy.central_domains', []);

        if (in_array($alanAdi, array_map('strtolower', $merkez), true)) {
            throw new DomainUnavailableException('Bu alan adı kullanılamaz.');
        }

        /*
        | ⚠️ Kendi kök alan adımızın ALTINA da özel alan adı alınamaz:
        | `panel.tikmarka.com` gibi bir kayıt, ayrılmış adlar listesini
        | (3D) dolaşmanın arka kapısı olurdu.
        */
        $kok = strtolower((string) config('tenancy.signup_root_domain', ''));

        if ($kok !== '' && str_ends_with($alanAdi, '.'.$kok)) {
            $ilkParca = explode('.', $alanAdi)[0];

            if (ReservedSubdomains::ayrilmisMi($ilkParca)) {
                throw new DomainUnavailableException(sprintf('"%s" ayrılmış bir addır, kullanılamaz.', $ilkParca));
            }
        }
    }
}
