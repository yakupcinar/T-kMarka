<?php

namespace App\Http\Panel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ürün oluşturma/güncelleme.
 *
 * ⚠️ `slug` ve `status` burada YOK.
 * slug   → başlıktan üretiliyor ve SONRADAN DEĞİŞMİYOR (adres kırılmasın)
 * status → ayrı uçta, çünkü satışa almanın kendi şartı var (varyant)
 */
class ProductRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'attributes' => ['nullable', 'array'],

            // Boş bırakılırsa mağaza varsayılanından doldurulur.
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'category_uuid' => ['nullable', 'uuid', 'exists:categories,uuid'],
        ];
    }
}
