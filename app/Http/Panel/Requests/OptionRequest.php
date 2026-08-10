<?php

namespace App\Http\Panel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Eksen oluşturma/güncelleme — "Renk", "Beden".
 *
 * ⚠️ `slug` burada YOK: addan üretiliyor. Dışarıdan alınsaydı marka "Renk"
 * adına "beden" slug'ı verip filtre adreslerini bozabilirdi.
 *
 * Benzersizlik kuralı da burada değil serviste/veritabanında: slug
 * üretildikten SONRA bakılabilir, doğrulama aşamasında ad henüz slug değil.
 */
class OptionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];
    }
}
