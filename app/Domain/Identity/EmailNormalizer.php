<?php

namespace App\Domain\Identity;

/**
 * E-postayı karşılaştırılabilir tek bir biçime indirger — TEK KAPI.
 *
 * ⚠️ NEDEN `mb_strtolower` YETMİYOR — Türkçe büyük İ tuzağı.
 *
 * Ölçüldü:
 *
 *   'İSMAIL@ornek.com'
 *     PHP  mb_strtolower →  'i̇smail@…'   i + AYRI birleşen nokta (U+0307)
 *     PgSQL lower()      →  'ismail@…'    düz i
 *
 * İkisi farklı metin. `customers_email_lowercase` CHECK kısıtı ikisini de
 * "zaten küçük harf" saydığı için ENGELLEMİYOR ve benzersiz indeks de
 * yakalamıyor — iki dizgi gerçekten farklı.
 *
 * Sonucu: `ismail@ornek.com` ile `İSMAIL@ornek.com` İKİ AYRI HESAP olur.
 * Daha kötüsü, küçük harfle kayıt olan kişi sonra Türkçe klavyeyle büyük
 * yazarsa eşleşme bulunamaz ve "parola yanlış" mesajı alır — hesabı
 * duruyorken kilitlenmiş gibi görünür.
 *
 * Çözüm: 'İ' harfini küçültmeden ÖNCE ASCII `i`'ye eşlemek. Sonuç
 * PostgreSQL'in `lower()` çıktısıyla birebir aynı oluyor — bu bir test
 * tarafından korunuyor.
 *
 * Diğer Türkçe harfler (ş ğ ü ö ç) sorunsuz: PHP ve PostgreSQL aynı sonucu
 * veriyor.
 */
class EmailNormalizer
{
    public static function normallestir(?string $eposta): ?string
    {
        if ($eposta === null) {
            return null;
        }

        /*
        | ⚠️ Küçültmeden ÖNCE: mb_strtolower 'İ'yi birleşen noktalı hâle
        | çevirdiği için sonrasında düzeltmek mümkün olmuyor.
        |
        | ⚠️ YALNIZCA 'İ'. İlk yazımda noktasız 'ı' da ASCII 'i'ye
        | eşlenmişti; test bunu yakaladı: PostgreSQL `lower()` 'ı'yı olduğu
        | gibi bırakıyor, biz çevirince `işik` ≠ `işık` uyuşmazlığı doğuyordu.
        | Bozuk olan tek harf 'İ'; fazlası uyumu bozuyor.
        */
        $eposta = str_replace('İ', 'i', trim($eposta));

        return mb_strtolower($eposta, 'UTF-8');
    }
}
