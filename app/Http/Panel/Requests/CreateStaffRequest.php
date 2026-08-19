<?php

namespace App\Http\Panel\Requests;

use App\Domain\Identity\EmailNormalizer;
use App\Models\Role;
use App\Rules\AsciiEmail;
use App\Rules\DeliverableEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Yeni personel davet doğrulaması.
 */
class CreateStaffRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => EmailNormalizer::normallestir((string) $this->input('email'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email', new AsciiEmail, new DeliverableEmail],
            'password' => ['required', 'string', 'min:8'],

            /*
            | Roller İSİMLE veriliyor: {"roles": ["Katalog"]}
            | id ile olsaydı hem okunmaz olurdu hem de iç kimlikleri sızdırırdı.
            |
            | `exists` kuralı olmadan yazım hatası SESSİZCE yok sayılırdı:
            | "Kataloq" yazan biri rolsüz bir personel oluşturur ve neden
            | hiçbir şey göremediğini anlamazdı.
            */
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists(Role::class, 'name')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Bu e-posta adresiyle bir personel zaten var.',
            'roles.*.exists' => 'Seçilen rollerden biri bulunamadı.',
        ];
    }
}
