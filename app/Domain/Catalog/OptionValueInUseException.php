<?php

namespace App\Domain\Catalog;

/**
 * Varyantlarda kullanılan eksen DEĞERİ silinmek istendi.
 *
 * ⚠️ Silinebilseydi varyantın `options` alanında artık var olmayan bir
 * değer kalırdı. Sonuç: vitrin "Kırmızı" yazan ama SEÇİLEMEYEN bir
 * seçenek gösterir; müşteri tıklar, hiçbir şey olmaz.
 *
 * Veritabanı bunu yakalayamıyor — değer jsonb'nin İÇİNDE, yabancı anahtar
 * kurulamıyor. Bu yüzden kontrol yalnızca burada; kaçarsa kaçar.
 */
class OptionValueInUseException extends CatalogConflictException
{
    public function __construct(public readonly string $deger, public readonly int $varyantSayisi)
    {
        parent::__construct("'{$deger}' değeri {$varyantSayisi} varyantta kullanılıyor, silinemez.");
    }

    public function cozum(): string
    {
        return 'Önce bu değeri kullanan varyantları silin veya başka bir değere taşıyın.';
    }

    /** @return array<string, mixed> */
    public function ayrintilar(): array
    {
        return ['variant_count' => $this->varyantSayisi];
    }
}
