<?php

namespace App\Domain\Promotion;

/**
 * Kupon kodunun tek doğru biçimi. (2A)
 *
 * ★ `EmailNormalizer`'ın (1A.2) kardeşi — aynı tuzak, ters yön.
 *
 * ⚠️ TÜRKÇE BÜYÜTME TUZAĞI. `mb_strtoupper('indirim')` Türkçe yerelde
 * `İNDİRİM` üretiyor; müşteri klavyesinden `INDIRIM` yazıyor ve kupon
 * BULUNAMIYOR. Hata da vermiyor — yalnızca "geçersiz kupon" diyor ve
 * marka kampanyasının neden tutmadığını anlayamıyor.
 *
 * Çözüm: `i`→`I`, `ı`→`I`, `ş`→`S` gibi ASCII'ye indirgeme. Kod zaten
 * insanın yazacağı bir şey; Türkçe karakter içermesine gerek yok.
 */
class CouponCode
{
    /** @var array<string, string> */
    private const HARFLER = [
        'ı' => 'I', 'i' => 'I', 'İ' => 'I', 'I' => 'I',
        'ş' => 'S', 'Ş' => 'S',
        'ğ' => 'G', 'Ğ' => 'G',
        'ü' => 'U', 'Ü' => 'U',
        'ö' => 'O', 'Ö' => 'O',
        'ç' => 'C', 'Ç' => 'C',
    ];

    /**
     * Normalleştirir: Türkçe harfler ASCII'ye, kalanı büyük harfe.
     *
     * ⚠️ `strtoupper` DEĞİL `mb_` de değil: ikisi de Türkçe harfte
     * beklenmedik sonuç veriyor. Harf harf çevriliyor.
     */
    public static function normallestir(?string $kod): ?string
    {
        if ($kod === null) {
            return null;
        }

        $kod = trim($kod);

        if ($kod === '') {
            return null;
        }

        $kod = strtr($kod, self::HARFLER);

        /*
        | Kalan harfler ASCII; `strtoupper` güvenli. Boşluk ve nokta gibi
        | karakterler atılıyor — kod yalnızca harf, rakam, tire, alt çizgi.
        */
        $kod = strtoupper($kod);

        return preg_replace('/[^A-Z0-9_-]/', '', $kod) ?: null;
    }
}
