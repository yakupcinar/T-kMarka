<?php

namespace App\Http\Storefront\Requests;

use App\Domain\Identity\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Müşteri girişi doğrulaması.
 *
 * Burada `exists:customers,email` KULLANILMIYOR — kullanılsaydı "böyle bir
 * kullanıcı yok" cevabı dönerdi ve saldırgan hangi e-postaların kayıtlı
 * olduğunu öğrenebilirdi. Kimlik kontrolü serviste, tek mesajla yapılıyor.
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
