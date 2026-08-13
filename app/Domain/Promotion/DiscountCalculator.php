<?php

namespace App\Domain\Promotion;

use App\Enums\CouponType;
use App\Models\Coupon;

/**
 * İndirim tutarı — TEK yer. (2A)
 *
 * ⚠️ `OrderTotals` gibi: para hesabı dağıtılmıyor. Yüzde hesabı iki yerde
 * yazılsaydı biri yuvarlamayı unutur ve kuruş kaymaları başlardı (§0).
 */
class DiscountCalculator
{
    private const BASAMAK = 2;

    /**
     * Kuponun ürün toplamı üzerindeki indirimi.
     *
     * @return numeric-string
     */
    public function indirim(Coupon $kupon, string $urunToplami): string
    {
        $toplam = $this->sayi($urunToplami);

        $ham = match ($kupon->type) {
            /*
            | ⚠️ `bcdiv` KESİYOR (1D.3'ün dersi). Yarım yukarı yuvarlama
            | elle: 100,00 × %33 = 33,00 değil 33,000 → sorun yok ama
            | 99,99 × %33 = 32,9967 → 33,00 olmalı, 32,99 değil.
            */
            CouponType::Percentage => $this->yuvarla(
                bcdiv(bcmul($toplam, $this->sayi($kupon->value), 6), '100', 6),
            ),

            CouponType::Fixed => $this->sayi($kupon->value),

            // ⚠️ Ücretsiz kargo ürün tutarına DOKUNMUYOR.
            CouponType::FreeShipping => '0.00',
        };

        /*
        | ⚠️ İNDİRİM SEPETTEN BÜYÜK OLAMAZ. Olsaydı `grand_total` eksiye
        | düşer, sağlayıcıya negatif tutar gider ve ödeme başlatılamazdı.
        */
        return bccomp($ham, $toplam, self::BASAMAK) > 0 ? $toplam : $ham;
    }

    /** @return numeric-string */
    private function yuvarla(string $deger): string
    {
        /** @var numeric-string $ham */
        $ham = $deger;

        /** @var numeric-string $sonuc */
        $sonuc = bcadd($ham, '0.005', self::BASAMAK);

        return $sonuc;
    }

    /** @return numeric-string */
    private function sayi(mixed $deger): string
    {
        return is_numeric($deger) ? (string) $deger : '0';
    }
}
