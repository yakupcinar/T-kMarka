<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * E-posta adresinde ASCII dışı karakter yasak.
 *
 * ⚠️ Neden yasaklıyoruz: adreste uluslararası karakter desteği (RFC 6531 /
 * SMTPUTF8) pratikte YOK — alan adlarının yaklaşık %10'unda destekleniyor.
 * Türkçe karakter içeren bir adrese posta çoğu sunucudan teslim edilemez;
 * kabul etmek, kullanıcıya "kaydoldun" deyip hiçbir e-postayı alamayacağı
 * bir hesap açmak olurdu.
 *
 * ⚠️ SIRA ÖNEMLİ: bu kural, `EmailNormalizer` metni düzelttikten SONRA
 * çalışıyor (`prepareForValidation` → `rules`). Önce çalışsaydı Türkçe
 * klavyede Caps Lock'la `İSMAIL@ornek.com` yazan kişi, aslında geçerli olan
 * kendi adresinin reddedildiğini görürdü. Normalleştirme 'İ'yi 'i' yapıyor,
 * bu kural yalnızca GERÇEKTEN ASCII dışı kalanları eliyor.
 */
class AsciiEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;   // tip denetimi `string` kuralının işi
        }

        // Yazdırılabilir ASCII aralığı (boşluk hariç zaten e-postada olamaz).
        if (preg_match('/^[\x21-\x7E]+$/', $value) !== 1) {
            $fail('E-posta adresi yalnızca İngiliz alfabesi harfleri, rakam ve işaret içerebilir.');
        }
    }
}
