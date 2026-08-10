<?php

namespace App\Domain\Settings;

use App\Enums\SettingGroup;

/**
 * Mağazanın yayın durumu — durum makinesi.
 *
 *       ┌──────────┐   yayinla()  (hazırlık denetimi)   ┌──────────┐
 *       │  KAPALI  │───────────────────────────────────▶│ YAYINDA  │
 *       │          │◀──────────── kapat() ──────────────│          │
 *       └──────────┘             (serbest)              └──────────┘
 *        vitrin 503                                      vitrin açık
 *        her şey düzenlenebilir                          kilitli alanlar 409
 *
 * Neden "yayındayken düzenlemeyi tek tek engelle" değil de "önce kapat":
 * tek tek engelleme, alanı BOŞALTMAYI yasaklar ama YANLIŞ YAZMAYI
 * yasaklayamaz. Vergi dairesi değişince, doğru değeri yazana kadar geçen
 * anda yayında yanlış bilgi durur. Kapalıyken o anı kimse görmüyor.
 */
class StorePublication
{
    /** Yayın durumunun tutulduğu ayar. */
    public const ANAHTAR = 'is_published';

    /**
     * Mağaza YAYINDAYKEN değiştirilemeyen ayarlar.
     *
     * Ayırt edici soru: "bu değer müşterinin onayladığı sözleşmenin içine
     * giriyor mu?" Giriyorsa kilitli — sözleşme metni değişirse eski
     * siparişin dayanağı da değişmiş olur.
     *
     * Kilitli OLMAYANLAR ve nedenleri:
     *   tax.default_rate   KDV oranı kanunla değişir, mağaza kapatılması beklenemez
     *   shipping.*         kampanya, günlük iş
     *   store.name         vitrin adı; sözleşmede geçen `legal_name` ayrı
     *   payment.api_key    sağlayıcı anahtarı yenilenebilir, sözleşmede geçmez
     *
     * @var array<string, list<string>>
     */
    public const KILITLI = [
        'store' => StoreReadiness::ZORUNLU_AYARLAR,
    ];

    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly StoreReadiness $hazirlik,
    ) {}

    public function yayindaMi(): bool
    {
        return $this->ayarlar->al(SettingGroup::Store, self::ANAHTAR, false) === true;
    }

    /**
     * Mağazayı yayına alır.
     *
     * ⚠️ Ya hepsi ya hiçbiri: tek bir eksik varsa bayrak DEĞİŞMEZ ve
     * eksiklerin TAMAMI istisnayla bildirilir.
     *
     * @throws StoreNotReadyException
     */
    public function yayinla(): void
    {
        $eksikler = $this->hazirlik->eksikler();

        if ($eksikler !== []) {
            throw new StoreNotReadyException($eksikler);
        }

        $this->ayarlar->yaz(SettingGroup::Store, self::ANAHTAR, true);
    }

    /**
     * Mağazayı kapatır — denetimsiz.
     *
     * Kapatmak her zaman serbest olmalı: markanın düzenleme yapabilmesinin
     * ve acil durumda satışı durdurabilmesinin tek yolu bu. Kapanmayı
     * şarta bağlamak, hatalı bir mağazayı açık kalmaya zorlardı.
     */
    public function kapat(): void
    {
        $this->ayarlar->yaz(SettingGroup::Store, self::ANAHTAR, false);
    }

    /** Bu ayar, mağaza yayındayken kilitli mi? */
    public function kilitliMi(SettingGroup $grup, string $anahtar): bool
    {
        return in_array($anahtar, self::KILITLI[$grup->value] ?? [], strict: true);
    }

    /**
     * Yazma denemesini yayın durumuna göre denetler.
     *
     * @throws SettingLockedException
     */
    public function yazmayiDogrula(SettingGroup $grup, string $anahtar): void
    {
        if ($this->yayindaMi() && $this->kilitliMi($grup, $anahtar)) {
            throw new SettingLockedException("{$grup->value}.{$anahtar}");
        }
    }
}
