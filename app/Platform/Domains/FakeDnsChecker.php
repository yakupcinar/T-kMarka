<?php

namespace App\Platform\Domains;

/**
 * Test ve geliştirme için DNS taklidi. (3H)
 *
 * ⚠️ Varsayılan olarak HİÇBİR KAYIT döndürmüyor — yani doğrulama başarısız.
 * Boş sonuç "doğrulandı" sayılsaydı testler doğrulamanın çalıştığını
 * sanır, gerçekte her alan adı kabul edilirdi.
 */
class FakeDnsChecker implements DnsChecker
{
    /** @var array<string, array{cname: list<string>, a: list<string>, txt: list<string>}> */
    private array $kayitlar = [];

    /**
     * @param  list<string>  $cname
     * @param  list<string>  $a
     * @param  list<string>  $txt
     */
    public function ayarla(string $alanAdi, array $cname = [], array $a = [], array $txt = []): void
    {
        $this->kayitlar[strtolower($alanAdi)] = ['cname' => $cname, 'a' => $a, 'txt' => $txt];
    }

    public function kayitlar(string $alanAdi): array
    {
        return $this->kayitlar[strtolower($alanAdi)] ?? ['cname' => [], 'a' => [], 'txt' => []];
    }
}
