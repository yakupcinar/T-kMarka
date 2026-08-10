<?php

namespace App\Http\Panel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kategori oluşturma/güncelleme.
 *
 * ⚠️ `slug`, `path` ve `level` burada YOK. Üçü de TÜRETİLİYOR:
 * slug addan, path/level ağaçtaki yerden. Dışarıdan alınsaydı ağaç sessizce
 * tutarsız hâle gelir ve "Giyim'in altındaki her şey" sorgusu eksik sonuç
 * dönerdi.
 */
class CategoryRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],

            // Yalnızca OLUŞTURMADA kullanılıyor; taşımak ayrı uç.
            'parent_uuid' => ['nullable', 'uuid', 'exists:categories,uuid'],
        ];
    }
}
