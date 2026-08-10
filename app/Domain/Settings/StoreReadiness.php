<?php

namespace App\Domain\Settings;

use App\Domain\Legal\LegalDocumentService;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;

/**
 * "Bu mağaza yayına girebilir mi?" sorusunun tek cevabı.
 *
 * Denetim YAYIN ANINDA koşuyor, düzenleme anında değil. Marka bilgileri
 * birkaç oturumda doldurur; her kaydetmede "eksik!" demek işi imkânsız
 * kılardı. Kapı tek yerde: yayınlama.
 */
class StoreReadiness
{
    /**
     * Mesafeli satış sözleşmesinin yasal olarak içermek ZORUNDA olduğu
     * satıcı bilgileri. Biri eksikse yayınlanan sözleşme geçersiz olur.
     *
     * @var list<string>
     */
    public const ZORUNLU_AYARLAR = [
        'legal_name',
        'tax_number',
        'tax_office',
        'address',
        'phone',
        'contact_email',
    ];

    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly LegalDocumentService $belgeler,
    ) {}

    /**
     * Eksik olan her şeyin listesi. Boşsa mağaza yayına hazır.
     *
     * ⚠️ İlk eksikte durmuyor, HEPSİNİ topluyor. Tek tek bildirseydi marka
     * altı kez "yayınla → eksik" turu atardı.
     *
     * @return list<string>
     */
    public function eksikler(): array
    {
        $eksikler = [];

        $magaza = $this->ayarlar->grup(SettingGroup::Store);

        foreach (self::ZORUNLU_AYARLAR as $anahtar) {
            $deger = $magaza[$anahtar] ?? null;

            // Boş metin de eksik sayılıyor: `''` teknik olarak "dolu" ama
            // sözleşmede boş satır bırakır.
            if ($deger === null || (is_string($deger) && trim($deger) === '')) {
                $eksikler[] = "store.{$anahtar}";
            }
        }

        foreach (LegalDocumentType::cases() as $tur) {
            // Taslağın yazılmış olması yetmez — YAYINLANMIŞ sürüm şart.
            // Sipariş taslağa bağlanamaz, taslak değişebilir.
            if ($this->belgeler->guncelSurum($tur) === null) {
                $eksikler[] = "legal.{$tur->value}";
            }
        }

        return $eksikler;
    }

    public function hazirMi(): bool
    {
        return $this->eksikler() === [];
    }
}
