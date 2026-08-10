<?php

namespace App\Http\Panel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Eksen değeri — "Kırmızı", "M", "42".
 */
class OptionValueRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:60'],

            // Renk kodu (#c00) ya da kumaş görseli yolu. Yalnızca renk gibi
            // eksenlerde dolu; panel doluysa kutucuk boyar.
            'swatch' => ['nullable', 'string', 'max:40'],

            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];
    }
}
