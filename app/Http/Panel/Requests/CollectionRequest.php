<?php

namespace App\Http\Panel\Requests;

use App\Domain\Catalog\CollectionRules;
use App\Enums\CollectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Koleksiyon oluşturma/güncelleme. (2D)
 *
 * ⚠️ `slug` burada YOK — başlıktan türetiliyor (ürün/kategoriyle aynı).
 *
 * ⚠️ Kuralın İÇİ burada doğrulanmıyor, yalnızca "dizi mi" diye bakılıyor.
 * Asıl doğrulama [CollectionRules]'ta ve oradan geçmek ZORUNLU: kural
 * yalnızca HTTP'den değil, tohumlayıcıdan ve komuttan da gelebiliyor.
 * Kontrolü buraya yazsaydık o yollar atlardı (CLAUDE.md — iş kuralı
 * controller'a yazılmaz).
 */
class CollectionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'is_active' => ['nullable', 'boolean'],

            'type' => ['required', Rule::enum(CollectionType::class)],

            'rules' => ['nullable', 'array'],
            'rules.match' => ['nullable', Rule::in(CollectionRules::ESLESMELER)],
            'rules.conditions' => ['nullable', 'array'],
        ];
    }
}
