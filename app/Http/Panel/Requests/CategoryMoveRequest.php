<?php

namespace App\Http\Panel\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kategori taşıma — AYRI uç.
 *
 * ⚠️ Neden `update` içinde değil: taşımanın kendi kuralları var (döngü
 * engeli) ve alt ağacın tamamını yeniden yazıyor. Ad değiştirmekle aynı
 * uçta olsaydı, basit bir yeniden adlandırma isteğinde yanlışlıkla
 * gönderilen bir `parent_uuid` koca bir dalı taşırdı.
 *
 * Mağaza yayınlama/kapatmayı ayrı uçlara koyma kararıyla aynı düşünce (1A.4).
 */
class CategoryMoveRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // null → köke taşı. `present` şart: anahtarın hiç gönderilmemesi
            // ile "köke taşı" niyeti karışmasın.
            'parent_uuid' => ['present', 'nullable', 'uuid', 'exists:categories,uuid'],
        ];
    }
}
