<?php

namespace App\Domain\Catalog;

use RuntimeException;

/**
 * Girilen metinden slug üretilemedi.
 *
 * "★", "///", "…" gibi girdiler `Str::slug`'tan boş dönüyor. Boş slug
 * kaydedilseydi ikinci böyle değer benzersizlik kısıtına takılır ve marka
 * "neden ekleyemiyorum" sorusunun cevabını bulamazdı.
 */
class EmptySlugException extends RuntimeException
{
    public function __construct(public readonly string $metin)
    {
        parent::__construct(
            "'{$metin}' adresde kullanılabilir bir karşılık üretmiyor; en az bir harf ya da rakam içermeli."
        );
    }
}
