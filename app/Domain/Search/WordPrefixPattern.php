<?php

namespace App\Domain\Search;

/**
 * Kelime başından eşleşen arama deseni. (4.5S)
 *
 * ★ NEDEN ORTAK SINIF: aynı desen 4.5P'de panel ürün aramasına yazıldı,
 * sonra merkez marka araması için de istendi. İkinci kez kopyalansaydı
 * biri düzeltilip öteki unutulurdu — bu projede tam olarak o aile
 * (rozet/sepet, çerez okuma) defalarca ısırdı.
 *
 * ⚠️ `\m` POSIX'te KELİME BAŞI sınırı; PostgreSQL'de `~*` ile birlikte
 * büyük/küçük harf ayrımı yapmadan eşleşiyor:
 *
 *   "cüz"  → "Deri Cüzdan"           ✅ kelime başı
 *   "deri" → "Kahverengi Deri Çanta" ✅ İKİNCİ kelimenin başı
 *   "üzd"  → hiçbiri                 ✅ kelime ortası eşleşmiyor
 *
 * ⚠️ Yalnızca `ILIKE 'kelime%'` yazılsaydı (metnin başı) ikinci örnek
 * HİÇ ÇIKMAZDI — kullanıcı kendi kaydını bulamazdı.
 */
class WordPrefixPattern
{
    /**
     * Kullanıcının yazdığı metni güvenli bir POSIX desenine çevirir.
     *
     * ⚠️ KAÇIRMA ŞART: metin doğrudan düzenli ifadeye giriyor.
     * Kaçırılmasaydı `.*` yazan biri TÜM kayıtları döndürür, yarım bir
     * desen (`(`) ise sorguyu doğrudan PATLATIRDI.
     */
    public static function olustur(string $kelime): string
    {
        $kacirilmis = preg_replace('/[.\\\\+*?\[^\]$(){}=!<>|:\-#\/]/', '\\\\$0', $kelime) ?? $kelime;

        return '\m'.$kacirilmis;
    }
}
