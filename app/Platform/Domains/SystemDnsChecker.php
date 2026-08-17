<?php

namespace App\Platform\Domains;

/**
 * Gerçek DNS sorgusu. (3H)
 *
 * ⚠️ Ağa çıkıyor ve YAVAŞ olabilir. Bu yüzden yalnızca markanın "kontrol
 * et" dediği anda çağrılıyor; TLS el sıkışmasının kritik yolunda DEĞİL.
 * Orada `ask` ucu var ve o yalnızca veritabanına bakıyor.
 */
class SystemDnsChecker implements DnsChecker
{
    public function kayitlar(string $alanAdi): array
    {
        /*
        | ⚠️ `@` ile hata bastırılıyor: DNS çözülemeyen alan adı bir
        | ARIZA değil, beklenen durum — marka kaydı henüz eklememiş
        | olabilir. İstisna fırlasaydı panel 500 gösterirdi.
        */
        $cname = @dns_get_record($alanAdi, DNS_CNAME) ?: [];
        $a = @dns_get_record($alanAdi, DNS_A) ?: [];
        $txt = @dns_get_record($alanAdi, DNS_TXT) ?: [];

        return [
            'cname' => array_values(array_filter(array_map(
                fn (array $k): string => strtolower(rtrim((string) ($k['target'] ?? ''), '.')),
                $cname,
            ))),
            'a' => array_values(array_filter(array_map(
                fn (array $k): string => (string) ($k['ip'] ?? ''),
                $a,
            ))),
            'txt' => array_values(array_filter(array_map(
                fn (array $k): string => (string) ($k['txt'] ?? ''),
                $txt,
            ))),
        ];
    }
}
