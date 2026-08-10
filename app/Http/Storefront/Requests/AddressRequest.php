<?php

namespace App\Http\Storefront\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Adres ekleme ve güncelleme doğrulaması — tek sınıf.
 *
 * Ekleme ve güncelleme aynı alanları alıyor; ayrı iki sınıf olsaydı biri
 * değişince diğerini güncellemeyi unutmak an meselesiydi.
 *
 * ⚠️ `customer_id` burada YOK ve olmayacak. Adres her zaman giriş yapmış
 * müşterinin ilişkisi üzerinden oluşturuluyor; alan burada tanımlansaydı
 * istekten gelen bir değerle başkasının defterine yazılabilirdi.
 */
class AddressRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Müşterinin kendi etiketi: "Ev", "İş", "Annemler".
            'title' => ['required', 'string', 'max:60'],

            // Adresteki kişi müşteriden farklı olabilir (hediye gönderimi),
            // bu yüzden ayrı alan.
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],

            // Türkiye adres yapısı: il → ilçe → mahalle
            'city' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:60'],
            'neighborhood' => ['nullable', 'string', 'max:100'],

            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
