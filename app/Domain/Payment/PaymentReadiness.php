<?php

namespace App\Domain\Payment;

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;

/**
 * Ödeme yapılandırması hazır mı? (1E-K11)
 *
 * ★ `StoreReadiness`'in ödeme karşılığı. İkisi de aynı soruyu soruyor:
 * "eksik ne, ve marka onu GÖREBİLİYOR mu?"
 *
 * ⚠️ Varlık sebebi sessiz bir arıza: ayar ucu serbest biçimli, yani
 * `iyzico_api_key` yerine `iyzico_api` yazan marka hata almaz. Anahtar
 * hiçbir zaman okunmayan bir yere yazılır, panel "ödeme ayarlandı" gibi
 * görünür ve hata ancak İLK GERÇEK MÜŞTERİDE ortaya çıkar.
 */
class PaymentReadiness
{
    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly PaymentProviderFactory $saglayicilar,
    ) {}

    /**
     * Eksik anahtarların listesi. Boşsa ödeme alınabilir.
     *
     * @return list<string>
     */
    public function eksikler(): array
    {
        $saglayici = $this->saglayicilar->coz();
        $mevcut = $this->ayarlar->grup(SettingGroup::Payment);

        $eksik = [];

        foreach ($saglayici->gerekliAnahtarlar() as $anahtar) {
            $deger = $mevcut[$anahtar] ?? null;

            /*
            | ⚠️ BOŞ METİN de eksik sayılıyor.
            |
            | Anahtarı yanlışlıkla silen marka `''` bırakabiliyor; yalnızca
            | `null` kontrol edilseydi "tanımlı" görünür, imza boş anahtarla
            | üretilir ve doğrulama hiçbir şey korumazdı (1E.1'de ölçüldü).
            */
            if (! is_string($deger) || trim($deger) === '') {
                $eksik[] = $anahtar;
            }
        }

        return $eksik;
    }

    public function hazirMi(): bool
    {
        return $this->eksikler() === [];
    }

    /**
     * Panele giden özet.
     *
     * ⚠️ Anahtarların DEĞERİ dönmüyor, yalnızca "tanımlı mı" bilgisi.
     * Panelde okunmalarına gerek yok; yazılmaları yeterli (§4).
     *
     * @return array{provider: string, available: list<string>, keys: array<string, bool>, ready: bool}
     */
    public function ozet(): array
    {
        $saglayici = $this->saglayicilar->coz();
        $eksikler = $this->eksikler();

        $anahtarlar = [];

        foreach ($saglayici->gerekliAnahtarlar() as $anahtar) {
            $anahtarlar[$anahtar] = ! in_array($anahtar, $eksikler, strict: true);
        }

        return [
            'provider' => $saglayici->ad(),
            'available' => PaymentProviderFactory::tanimliAdlar(),
            'keys' => $anahtarlar,
            'ready' => $eksikler === [],
        ];
    }
}
