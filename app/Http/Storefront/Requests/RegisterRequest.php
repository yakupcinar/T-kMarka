<?php

namespace App\Http\Storefront\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Müşteri kaydı doğrulaması.
 *
 * pre-setup §6.4: "Girdi doğrulama — FormRequest, istisnasız her yazma ucunda."
 */
class RegisterRequest extends FormRequest
{
    /**
     * ⚠️ E-posta DOĞRULAMADAN ÖNCE küçültülüyor.
     *
     * Olmasaydı: kullanıcı "Ali@Site.com" yazar → `unique` kuralı veritabanına
     * o hâliyle sorar → bulamaz → geçer → model küçültür → veritabanındaki
     * `unique`/`CHECK` kısıtı patlar ve kullanıcı 500 görür.
     * Aynı e-posta zaten kayıtlıyken "bu adres kullanılıyor" demesi gerekirdi.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:customers,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'accepts_marketing' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Bu e-posta adresiyle bir hesap zaten var.',
            'password.min' => 'Parola en az 8 karakter olmalı.',
        ];
    }
}
