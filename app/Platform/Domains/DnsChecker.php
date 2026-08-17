<?php

namespace App\Platform\Domains;

/**
 * Alan adının DNS kayıtlarını okur. (3H)
 *
 * ★ ARAYÜZ, çünkü gerçek DNS sorgusu TESTTE ÇALIŞTIRILAMAZ: ağa çıkar,
 * yavaştır ve sonucu bizim elimizde değildir. 1E'deki `PaymentProvider`
 * deseninin aynısı.
 *
 * ⚠️ Sahte uygulama GERÇEĞİ TAKLİT ETMELİ, kolaylık sağlamamalı — 1E.7'de
 * sahte sağlayıcı eksik cevap döndürdüğü için `status` kontrolü hiç
 * sınanmamıştı.
 */
interface DnsChecker
{
    /**
     * Alan adının CNAME ve A kayıtları.
     *
     * @return array{cname: list<string>, a: list<string>, txt: list<string>}
     */
    public function kayitlar(string $alanAdi): array;
}
