<?php

namespace App\Http\Panel\Requests;

use App\Domain\Identity\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Personel girişi doğrulaması.
 *
 * Vitrin tarafındakiyle aynı kurallar — ama AYRI sınıf. Paylaşılsaydı ileride
 * panel tarafına ek bir kural (örneğin iki adımlı doğrulama kodu) eklendiğinde
 * müşteri girişini de etkilerdi.
 */
class LoginRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
