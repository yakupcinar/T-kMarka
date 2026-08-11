<?php

namespace App\Domain\Catalog;

/**
 * Desteklenmeyen görsel türü.
 *
 * ⚠️ Karar DOSYANIN İÇERİĞİNE bakılarak veriliyor, istemcinin söylediği
 * türe değil. İstemciye güvenilseydi `zararlı.php` dosyası "image/jpeg"
 * etiketiyle diske yazılabilirdi.
 */
class UnsupportedImageTypeException extends CatalogRuleException
{
    /** @param  list<string>  $izinli */
    public function __construct(public readonly string $tur, public readonly array $izinli)
    {
        parent::__construct("'{$tur}' desteklenmiyor. İzin verilenler: ".implode(', ', $izinli));
    }

    /** @return array<string, list<string>> */
    public function alanHatalari(): array
    {
        return ['image' => [$this->getMessage()]];
    }
}
