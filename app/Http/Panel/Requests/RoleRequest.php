<?php

namespace App\Http\Panel\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rol oluşturma ve güncelleme.
 *
 * ⚠️ İzinler yalnızca `Permission` enum'ından seçilebilir. Serbest metin
 * kabul edilseydi marka `"urun.duzenle"` gibi bir satır yazardı; kayıt
 * başarılı olur, hiçbir kapı onu sormaz ve rol sessizce yetkisiz kalırdı.
 *
 * Marka yeni izin TÜRÜ üretemiyor — üretebilseydi "bu izin neyi kontrol
 * ediyor" eşlemesini de tutmak gerekirdi ve izin sistemi kendi başına bir
 * projeye dönerdi (domain-model §3 kapsam sınırı).
 */
class RoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var int|null $rolId */
        $rolId = $this->route('rol');

        return [
            'name' => [
                'required', 'string', 'max:60',

                // Aynı adda iki rol olamaz: personel ataması rol ADIYLA
                // yapılıyor (1A.3), iki aynı ad "hangisi" sorusunu doğururdu.
                Rule::unique('roles', 'name')->ignore($rolId),
            ],

            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permissions.*.enum' => 'Tanımsız izin. Geçerli izinler: '
                .implode(', ', Permission::tumDegerler()),
        ];
    }
}
