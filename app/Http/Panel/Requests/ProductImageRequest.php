<?php

namespace App\Http\Panel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Görsel yükleme.
 *
 * ⚠️ `image` ve `mimes` kuralları dosyanın İÇERİĞİNE bakıyor, adına değil.
 * Servis bunu bir kez daha denetliyor: bu uç dışından (artisan komutu, içe
 * aktarma işi) gelen yükleme de aynı süzgeçten geçmeli.
 */
class ProductImageRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // 5 MB — telefon fotoğrafı rahat sığıyor, ama sunucuyu
            // tıkayacak boyutlar giremiyor.
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],

            'alt' => ['nullable', 'string', 'max:200'],

            // Doluysa görsel o varyanta ait. Varyantın bu ürüne ait olduğu
            // controller'da daraltılmış sorguyla doğrulanıyor.
            'variant_uuid' => ['nullable', 'uuid'],
        ];
    }
}
