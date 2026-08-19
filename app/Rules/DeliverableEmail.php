<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * E-posta adresinin alan adı GERÇEK olmalı — `a@a` kabul edilmez. (4.5G)
 *
 * ★ GERÇEK KULLANIMDA BULUNDU. Laravel'in `email` kuralı `a@a` ve `a@aa`
 * adreslerini kabul ediyor (RFC'ye göre geçerliler; `localhost` gibi
 * TLD'siz adlar teknik olarak mümkün). Ama:
 *
 * ```
 * müşteri  a@a yazıyor        → bizim doğrulama GEÇİYOR
 * sipariş  oluşuyor           → STOK BAĞLANIYOR
 * iyzico   "email hatalı format ile gönderilmiştir"
 * sonuç    ödeme patlıyor, stok 60 DAKİKA kimseye satılamıyor
 * ```
 *
 * ⚠️ Yani sorun yalnızca "çirkin hata" değil: doğrulamamız sağlayıcıdan
 * GEVŞEK olduğu için hata en pahalı anda, sipariş oluştuktan SONRA
 * çıkıyordu.
 *
 * ⚠️ [AsciiEmail]'le aynı felsefe: teknik olarak geçerli ama PRATİKTE
 * TESLİM EDİLEMEZ adresi kabul etmek, kullanıcıya "siparişin alındı"
 * deyip onay postasını hiç gönderememek demek.
 *
 * ⚠️ DNS SORGUSU YAPILMIYOR (`email:dns` kuralı gibi). Ödeme akışının
 * ortasında ağa çıkmak isteği yavaşlatır ve ağ kesintisinde satışı
 * tamamen durdururdu — 4.5C'de test tarafında ölçtüğümüz maliyetin aynısı
 * (tek DNS sorgusu 24 saniye sürmüştü). Burada yalnızca BİÇİM kontrol
 * ediliyor: sağlayıcıların istediği de bu.
 */
class DeliverableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;   // tip denetimi `string` kuralının işi
        }

        $at = strrpos($value, '@');

        if ($at === false) {
            return;   // biçim denetimi `email` kuralının işi
        }

        $alanAdi = substr($value, $at + 1);

        /*
        | ⚠️ En az bir NOKTA ve sonrasında en az iki HARF.
        |
        | `a@a`      → nokta yok        → red
        | `a@aa`     → nokta yok        → red
        | `a@a.c`    → TLD tek harf     → red (gerçek TLD yok)
        | `a@a.co`   → geçerli
        | `a@a.b.co` → geçerli (alt alan adı)
        */
        if (preg_match('/\.[a-zA-Z]{2,}$/', $alanAdi) !== 1) {
            $fail('E-posta adresinin alan adı geçersiz görünüyor (örnek: ad@ornek.com).');
        }
    }
}
