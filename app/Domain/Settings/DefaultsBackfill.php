<?php

namespace App\Domain\Settings;

use App\Domain\Identity\DefaultRoles;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Legal\LegalTemplates;
use App\Domain\Payment\FakePaymentProvider;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;
use App\Models\LegalDocumentDraft;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Eksik varsayılanları tamamlar — VAR OLANI EZMEDEN. (3A)
 *
 * ★ NEDEN VAR: `tenant:create` yeni markaya varsayılanları kuruyor (1A.4),
 * ama **önceden açılmış** markalara kimse gitmiyor. Yeni bir ayar
 * eklendiğinde eski markalar onsuz kalıyor ve bu çoğu zaman HATA VERMİYOR.
 *
 * İki kez ısırdı:
 *   1A.6  0.5'te açılan B markası yasal TASLAKSIZDI
 *   1E.4  dev markalarının ödeme imza anahtarı yoktu → iki kiracıda
 *         gerçek HTTP koşusu "fake_secret anahtarı ayarlarda yok" ile durdu
 *
 * ⚠️ ÖLÇÜLDÜ (3A): bugün iki gerçek markada `shipping.threshold_after_discount`
 * eksik — 2A'da eklenmişti. Sonucu bu sefer zararsız çünkü okuyan kod
 * `?? true` yazmış; yani **şans eseri** doğruyduk. Bu komut şansı ortadan
 * kaldırıyor.
 *
 * ⚠️ `DefaultSettings::kur()` BURADA ÇAĞRILAMAZ. O metot `yaz()` kullanıyor
 * ve var olanı EZİYOR — mevcut bir markada çalıştırılsaydı:
 *   · marka `is_published`'ı FALSE olur → AÇIK MAĞAZA KAPANIR
 *   · `fake_secret` yenilenir → yoldaki bildirimlerin imzası geçersiz olur
 *   · markanın yazdığı yasal taslak metin SİLİNİR
 *   · değiştirilmiş vergi/kargo ayarları varsayılana döner
 */
class DefaultsBackfill
{
    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly LegalDocumentService $belgeler,
    ) {}

    /**
     * Bu markada neyin eksik olduğunu söyler — HİÇBİR ŞEY YAZMAZ.
     *
     * ⚠️ Ayrı metot olması bilinçli: komut önce "ne yapacağım" diye
     * gösterebiliyor. Geri dönüşü olmayan işlerde önce göstermek,
     * sonra yapmak.
     *
     * @return array{settings: list<string>, drafts: list<string>, roles: list<string>}
     */
    public function eksikler(string $markaAdi): array
    {
        $mevcut = Setting::all()
            ->map(fn (Setting $s) => $s->group->value.'.'.$s->key)
            ->all();

        $eksikAyar = [];

        foreach ($this->beklenenAyarlar($markaAdi) as $tam => $deger) {
            if (! in_array($tam, $mevcut, true)) {
                $eksikAyar[] = $tam;
            }
        }

        $taslaklar = LegalDocumentDraft::pluck('type')
            ->map(fn ($t) => $t instanceof LegalDocumentType ? $t->value : (string) $t)
            ->all();

        $eksikTaslak = [];

        foreach (LegalDocumentType::cases() as $tur) {
            if (! in_array($tur->value, $taslaklar, true)) {
                $eksikTaslak[] = $tur->value;
            }
        }

        $roller = Role::pluck('name')->all();
        $eksikRol = [];

        foreach (array_keys(DefaultRoles::tanimlar()) as $ad) {
            if (! in_array($ad, $roller, true)) {
                $eksikRol[] = (string) $ad;
            }
        }

        return ['settings' => $eksikAyar, 'drafts' => $eksikTaslak, 'roles' => $eksikRol];
    }

    /**
     * Eksikleri tamamlar. Var olana DOKUNMAZ.
     *
     * @return array{settings: int, drafts: int, roles: int}
     */
    public function tamamla(string $markaAdi): array
    {
        $eksik = $this->eksikler($markaAdi);
        $beklenen = $this->beklenenAyarlar($markaAdi);

        foreach ($eksik['settings'] as $tam) {
            [$grup, $anahtar] = explode('.', $tam, 2);

            /*
            | ⚠️ `fake_secret` HER MARKADA AYRI ve rastgele üretiliyor.
            | Sabit yazılsaydı bir markanın ürettiği bildirim diğerinde de
            | geçerli olurdu (1E.1'de ölçülmüştü). Şifreli saklanıyor.
            */
            if ($anahtar === FakePaymentProvider::GIZLI_ANAHTAR) {
                $this->ayarlar->yaz(SettingGroup::from($grup), $anahtar, Str::random(48), sifreli: true);

                continue;
            }

            $this->ayarlar->yaz(SettingGroup::from($grup), $anahtar, $beklenen[$tam]);
        }

        foreach ($eksik['drafts'] as $tur) {
            $this->belgeler->taslagaYaz(LegalDocumentType::from($tur), LegalTemplates::iskelet(LegalDocumentType::from($tur)));
        }

        /*
        | ⚠️ Roller EKSİKSE kuruluyor; `DefaultRoles::kur()` `firstOrCreate`
        | kullandığı için var olanı yeniden yaratmıyor.
        |
        | ⚠️ Ama `syncPermissions` çağırıyor — yani rol VARSA bile izinleri
        | tanıma göre yeniden yazılıyor. Bu BİLİNÇLİ: `Permission` enum'una
        | yeni bir izin eklendiğinde eski markaların sistem rolleri onu
        | almalı. Sistem rolleri zaten panelden düzenlenemiyor
        | (domain-model §3 kapsam sınırı), yani ezilecek özelleştirme yok.
        */
        if ($eksik['roles'] !== []) {
            (new DefaultRoles)->kur();
        }

        return [
            'settings' => count($eksik['settings']),
            'drafts' => count($eksik['drafts']),
            'roles' => count($eksik['roles']),
        ];
    }

    /**
     * Bir markada BULUNMASI GEREKEN bütün ayarlar.
     *
     * @return array<string, mixed> "grup.anahtar" => varsayılan değer
     */
    private function beklenenAyarlar(string $markaAdi): array
    {
        $beklenen = [];

        foreach (DefaultSettings::tanimlar() as $grup => $ayarlar) {
            foreach ($ayarlar as $anahtar => $deger) {
                $beklenen[$grup.'.'.$anahtar] = $deger;
            }
        }

        /*
        | ⚠️ Bu üçü `tanimlar()`'da YOK, `kur()` içinde elle yazılıyor —
        | dolayısıyla listeye burada ekleniyor. Unutulsalardı komut
        | "eksik yok" der ve 1E.4'teki `fake_secret` boşluğu aynen kalırdı.
        */
        $beklenen[SettingGroup::Store->value.'.name'] = $markaAdi;

        /*
        | ⚠️ Eksikse KAPALI yazılıyor. Açık yazılsaydı hazırlık denetiminden
        | geçmemiş bir mağaza kendiliğinden satışa açılırdı.
        |
        | ⚠️ VAR OLAN değere dokunulmuyor — açık mağaza açık kalıyor.
        */
        $beklenen[SettingGroup::Store->value.'.'.StorePublication::ANAHTAR] = false;

        $beklenen[SettingGroup::Payment->value.'.'.FakePaymentProvider::GIZLI_ANAHTAR] = null;

        return $beklenen;
    }
}
