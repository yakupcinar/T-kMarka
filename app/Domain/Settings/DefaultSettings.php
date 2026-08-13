<?php

namespace App\Domain\Settings;

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Legal\LegalTemplates;
use App\Domain\Payment\FakePaymentProvider;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;
use Illuminate\Support\Str;

/**
 * Yeni marka açılırken kurulan varsayılan ayarlar ve yasal taslaklar.
 *
 * ⚠️ Marka KAPALI doğuyor (`is_published = false`). Açılması için zorunlu
 * bilgilerin doldurulup üç yasal metnin yayınlanması gerekiyor — kimse
 * yanlışlıkla eksik bir mağazayla satışa başlamasın diye.
 *
 * ⚠️ `contact_email` BİLEREK boş: sahibin e-postasıyla doldurulabilirdi ama
 * o adres genelde kişisel ("ahmet@..."), müşteriye gösterilecek kurumsal
 * adres değil. Dolu doğsaydı marka fark etmeden kişisel adresini
 * sözleşmesine basardı — dolduramadığımızda susmak, yanlış doldurmaktan iyi.
 */
class DefaultSettings
{
    /**
     * Ayar varsayılanları. Zorunlu alanlar (`StoreReadiness::ZORUNLU_AYARLAR`)
     * burada YOK — onları markanın bilinçli girmesi gerekiyor.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function tanimlar(): array
    {
        return [
            SettingGroup::Tax->value => [
                // Türkiye'de genel oran. Kanunla değişebildiği için kilitli
                // değil, mağaza açıkken de düzenlenebiliyor.
                'default_rate' => 20,

                // docs/domain-model.md §8: vitrinde gösterilen fiyat KDV dâhil.
                'prices_include_tax' => true,
            ],

            SettingGroup::Shipping->value => [
                'flat_fee' => 49.90,
                'free_threshold' => 500,

                /*
                | ★ 2A-K1 — ücretsiz kargo eşiği hangi tutara baksın?
                |
                | `true`  indirim DÜŞÜLDÜKTEN sonraki tutar (varsayılan)
                | `false` indirimden önceki ara toplam
                |
                | ⚠️ Kuruş değil YÜZDE farkı: `false`'ta müşteri indirimle
                | birlikte bedava kargo da kazanıyor. WooCommerce'in
                | varsayılanı da `true` ama onlar da ayar bırakmış —
                | satıcılar anlaşamıyor.
                */
                'threshold_after_discount' => true,
            ],

            SettingGroup::Payment->value => [
                /*
                | Faz 1'in sağlayıcısı. Gerçek sağlayıcı takılınca marka
                | bunu panelden değiştirecek (1E.1).
                |
                | ⚠️ Sağlayıcı ANAHTARLARI burada YOK — gerçek kimlik
                | bilgisi varsayılan olamaz. Yalnızca sahte sağlayıcının
                | imza anahtarı `kur()` içinde rastgele üretiliyor.
                */
                'provider' => 'fake',
            ],

            SettingGroup::Checkout->value => [
                // docs/domain-model.md §6: müşteri hesap açmadan sipariş
                // verebilir; `customers.email` bu yüzden nullable.
                'guest_enabled' => true,
            ],
        ];
    }

    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly LegalDocumentService $belgeler,
    ) {}

    /**
     * Kiracı bağlamı ÇAĞIRAN tarafından açılmış olmalı — bu sınıf hangi
     * markada olduğunu bilmiyor (M-2.7).
     */
    public function kur(string $markaAdi): void
    {
        foreach (self::tanimlar() as $grupAdi => $ayarlar) {
            $grup = SettingGroup::from($grupAdi);

            foreach ($ayarlar as $anahtar => $deger) {
                $this->ayarlar->yaz($grup, $anahtar, $deger);
            }
        }

        // Vitrinde görünecek ad — komuta verilen marka adından geliyor.
        // `legal_name` (ticari unvan) ile aynı şey DEĞİL; sözleşmede unvan
        // geçiyor ve onu marka kendisi girmek zorunda.
        $this->ayarlar->yaz(SettingGroup::Store, 'name', $markaAdi);

        // ⚠️ Mağaza KAPALI doğuyor.
        $this->ayarlar->yaz(SettingGroup::Store, StorePublication::ANAHTAR, false);

        /*
        | Sahte sağlayıcının imza anahtarı — MARKA BAŞINA rastgele.
        |
        | ⚠️ Sabit bir değer yazılsaydı (`'test'` gibi) imza doğrulaması
        | her markada aynı olurdu ve testler gerçekte imzayı sınamazdı:
        | bir markanın ürettiği bildirim diğerinde de geçerli olurdu.
        |
        | ⚠️ `is_encrypted = true` — düz metin kaydedilseydi veritabanı
        | yedeğini gören herkes geçerli bildirim üretebilirdi.
        */
        $this->ayarlar->yaz(
            SettingGroup::Payment,
            FakePaymentProvider::GIZLI_ANAHTAR,
            Str::random(48),
            sifreli: true,
        );

        /*
        | Yasal metinler TASLAK olarak kuruluyor, yayınlanmıyor.
        |
        | Yayınlansaydı mağaza doğar doğmaz "hazır" görünürdü ve marka
        | metinleri hiç okumadan satışa başlayabilirdi. Taslakta durdukları
        | için hazırlık denetimi geçilemiyor — markanın her birini bilerek
        | yayınlaması gerekiyor.
        */
        foreach (LegalDocumentType::cases() as $tur) {
            $this->belgeler->taslagaYaz($tur, LegalTemplates::iskelet($tur));
        }
    }
}
