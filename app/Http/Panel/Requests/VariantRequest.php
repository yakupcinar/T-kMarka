<?php

namespace App\Http\Panel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Varyant ekleme/güncelleme.
 *
 * ⚠️ `options` burada yalnızca BİÇİM olarak doğrulanıyor (dizi mi, metin
 * mi). "Bu eksen bu üründe var mı, bu değer tanımlı mı" sorularının cevabı
 * VariantService'te — çünkü cevap ürünün tanımına bağlı ve doğrulama
 * katmanı ürünü bilmiyor.
 */
class VariantRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64'],

            // Para: string olarak da gelebilir, numeric yeterli.
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],

            'stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],

            'options' => ['present', 'array'],
            'options.*' => ['string', 'max:60'],
        ];
    }
}
