<?php

namespace App\Domain\Catalog;

use RuntimeException;

/**
 * Katalog VERİ kuralları — hepsi 422.
 *
 * Çatışmadan farkı: burada gönderilen verinin kendisi geçersiz (tanımsız
 * eksen değeri, sınır aşımı, eksik varyant). Zamanla ilgisi yok; aynı
 * istek beş dakika sonra da geçersiz.
 */
abstract class CatalogRuleException extends RuntimeException
{
    /**
     * Doğrulama hatası biçiminde alan → mesajlar.
     *
     * Panel, doğrulama hatalarını zaten bu şekilde gösteriyor; ayrı bir
     * biçim döndürmek arayüzde ikinci bir hata gösterme yolu açardı.
     *
     * @return array<string, list<string>>
     */
    public function alanHatalari(): array
    {
        return [];
    }
}
