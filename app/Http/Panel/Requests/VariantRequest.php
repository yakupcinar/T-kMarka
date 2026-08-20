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
    /**
     * ⚠️ MESAJLAR ELLE YAZILIYOR: varsayılan metin alan adını olduğu gibi
     * basıyor (*"options.renk metin olmalıdır"*) ve marka bunun eksen
     * seçimi olduğunu anlamıyordu.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.*.string' => 'Her varyant ekseni için bir değer seçin.',
        ];
    }

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

            /*
            | ⚠️ `required` — ve gerekçesi `ConvertEmptyStringsToNull`.
            |
            | Ekrandaki eksen seçicisinin boş seçeneği `value=""`
            | gönderiyor; global middleware onu **null**'a çeviriyor ve
            | `string` kuralı null'da düşüyor. Marka *"options.renk metin
            | olmalıdır"* uyarısı alıyordu — ne dediği anlaşılmayan bir
            | mesaj, üstelik ekranda hiç GÖRÜNMÜYORDU (hata anahtarı
            | `options.renk`, arayüz `options` arıyordu).
            |
            | ⚠️ 4.5I.1'in aynısı, BEŞİNCİ kez: gizlemek/boş bırakmak
            | göndermemek değildir.
            |
            | ⚠️ `required` DENENDİ VE ÇIKARILDI: gereksizdi. Anahtarın
            | HİÇ gönderilmediği durumu Domain zaten yakalıyor
            | (`VariantService::secenekleriDogrula` → *"'renk' ekseni
            | eksik"*) ve kırma denemesi bunu gösterdi — kuralı
            | kaldırınca test yine geçti. Buradaki iş yalnızca null'ı
            | reddetmek ve mesajı anlaşılır kılmak.
            */
            'options.*' => ['string', 'max:60'],
        ];
    }
}
