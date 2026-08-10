<?php

namespace App\Http\Panel\Requests;

use App\Domain\Settings\StorePublication;
use App\Enums\SettingGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ayar güncelleme gövdesi:
 *
 *   {"store": {"legal_name": "A Ltd."}, "shipping": {"flat_fee": 39.90}}
 *
 * Gövde SERBEST BİÇİMLİ — anahtarlar önceden bilinmiyor (marka tema rengi
 * de yazabilir, kargo eşiği de). Bu yüzden doğrulama iki şeye bakıyor:
 * grup adı geçerli mi, ve yazılmaması gereken bir anahtar var mı.
 */
class UpdateSettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // En üst seviye anahtarlar SettingGroup enum'ından olmalı.
            // Olmasaydı 'payments' (fazladan s) yazan biri hata almaz,
            // ayarı hiçbir yerde görünmeyen bir gruba yazardı.
            '*' => ['array'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            function (Validator $dogrulayici): void {
                foreach (array_keys($this->all()) as $grupAdi) {
                    if (! is_string($grupAdi) || SettingGroup::tryFrom($grupAdi) === null) {
                        $dogrulayici->errors()->add(
                            (string) $grupAdi,
                            "'{$grupAdi}' geçerli bir ayar grubu değil."
                        );
                    }
                }

                /*
                | ⚠️ Yayın durumu buradan YAZILAMAZ.
                |
                | `is_published` bir ayar gibi duruyor ama aslında bir DURUM
                | GEÇİŞİ: açılırken hazırlık denetiminden geçmesi gerekiyor.
                | Buradan yazılabilseydi marka, eksik bilgiyle mağazayı
                | açabilir ve bütün denetim atlanabilirdi.
                */
                $magaza = $this->input(SettingGroup::Store->value);

                if (is_array($magaza) && array_key_exists(StorePublication::ANAHTAR, $magaza)) {
                    $dogrulayici->errors()->add(
                        SettingGroup::Store->value.'.'.StorePublication::ANAHTAR,
                        'Yayın durumu buradan değiştirilemez; /panel/store/publish ve /panel/store/close kullanın.'
                    );
                }
            },
        ];
    }
}
