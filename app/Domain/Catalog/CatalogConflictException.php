<?php

namespace App\Domain\Catalog;

use RuntimeException;

/**
 * Katalog DURUM çatışmaları — hepsi 409.
 *
 * Ortak nitelikleri: personelin yetkisi VAR, gönderdiği veri GEÇERLİ;
 * yanlış olan ZAMAN — kaynağın şu anki hâli bu işlemi kaldırmıyor.
 *
 * ⚠️ Neden taban sınıf: 1A incelemesinde `bootstrap/app.php`'deki
 * istisna→HTTP eşlemesinin büyüdüğünü not etmiştik. 1B.3 tek başına yedi
 * yeni kural getiriyor; her birine ayrı `render` yazmak o dosyayı
 * okunamaz hâle getirirdi. Artık tek eşleme yetiyor, gerekçeler de
 * dağılmıyor — her alt sınıf kendi "neden"ini kendi docblock'unda taşıyor.
 */
abstract class CatalogConflictException extends RuntimeException
{
    /** Kullanıcıya "ne yapmalıyım" cevabı. */
    abstract public function cozum(): string;

    /**
     * Cevaba eklenecek alanlar (kaç alt kategori var, kaç ürün kullanıyor…).
     *
     * @return array<string, mixed>
     */
    public function ayrintilar(): array
    {
        return [];
    }
}
