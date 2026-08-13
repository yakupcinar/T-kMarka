<?php

namespace App\Domain\Search;

/**
 * Arama metnini ASCII'ye indirger. (2C-K3)
 *
 * ★ `CouponCode` (2A) ve `EmailNormalizer` (1A.2) ile aynı aile —
 * Türkçe karakterin sessizce eşleşmeyi bozduğu ÜÇÜNCÜ yer.
 *
 * ⚠️ ÖLÇÜLDÜ, tahmin değil:
 * ```
 * public.similarity('tisort', 'tişört')  =  0,27   ← eşik 0,3'ün ALTINDA
 * public.similarity('tisort', 'tisort')  =  1,00
 * ```
 * Türkçe karakter, üçlü harf benzerliğini eşiğin altına düşürüyor. Her
 * iki taraf da ASCII'ye indirilmeden "yazım hatası toleransı" hiç
 * çalışmıyor — ve bu hata vermiyor, sadece sonuç bulunamıyor.
 */
class SearchText
{
    /** @var array<string, string> */
    private const HARFLER = [
        'ı' => 'i', 'İ' => 'i', 'I' => 'i',
        'ş' => 's', 'Ş' => 's',
        'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u',
        'ö' => 'o', 'Ö' => 'o',
        'ç' => 'c', 'Ç' => 'c',
    ];

    /**
     * ⚠️ `mb_strtolower` DEĞİL: `İ` onda birleşik noktalı harfe dönüşüyor
     * ve `lower()` ile tutmuyor (1A.2'de ölçüldü). Harf harf çevriliyor.
     */
    public static function normallestir(?string $metin): string
    {
        if ($metin === null) {
            return '';
        }

        $metin = strtr($metin, self::HARFLER);
        $metin = strtolower($metin);

        // Noktalama aramaya girmiyor; boşluklar tek boşluğa iniyor.
        $metin = preg_replace('/[^a-z0-9\s-]/', ' ', $metin) ?? '';

        return trim(preg_replace('/\s+/', ' ', $metin) ?? '');
    }
}
