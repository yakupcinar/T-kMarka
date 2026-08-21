<?php

namespace App\Domain\Catalog;

use App\Models\Option;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Vitrindeki varyant seçicisinin verisi. (4.6A)
 *
 * ★ NEDEN DOMAIN'DE: "bu değer seçilebilir mi" sorusu satılabilirlik
 * kuralına bağlı ve o kural iş mantığı — `stock − committed` ve varyantın
 * aktifliği (1D-K1).
 *
 * ⚠️ EKRAN KENDİ HESABINI YAPMAMALI. `stock > 0` gibi bir kısayol
 * yazılsaydı müşteri, ödemesi süren başka bir siparişe BAĞLI stoğu
 * seçebilir ve sepete eklerken `InsufficientStockException` alırdı —
 * 4.5J'de rozet/sepet için ölçülen "iki formül" tuzağının aynısı.
 *
 * ⚠️ Bu sınıf HTTP'den habersiz (M-2.7): adres üretmiyor, istek okumuyor.
 * Yalnızca "hangi eksen, hangi değerler, hangi varyant satılabilir"
 * sorusunu cevaplıyor.
 */
class VariantSelector
{
    /**
     * Bir eksende kaç değerden sonra KUTUCUK yerine AÇILIR LİSTE.
     *
     * ⚠️ Sayı ekranda sabit yazılmıyor, buradan gidiyor: eşik
     * değiştiğinde iki taraf ayrışırdı (4.5S'de `maksEksen` için verilen
     * kararın aynısı).
     */
    public const LISTE_ESIGI = 5;

    /**
     * @return array{
     *     eksenler: list<array{slug: string, ad: string, listeMi: bool, degerler: list<array{slug: string, ad: string, swatch: string|null}>}>,
     *     varyantlar: list<array{uuid: string, secenekler: array<string, string>, fiyat: string, satilabilir: bool}>,
     *     tekVaryant: string|null
     * }
     */
    public function coz(Product $urun): array
    {
        /** @var list<array{uuid: string, secenekler: array<string, string>, fiyat: string, satilabilir: bool}> $varyantlar */
        $varyantlar = $urun->variants->map(fn (ProductVariant $v) => [
            'uuid' => (string) $v->uuid,

            /*
            | ⚠️ `options` eksen slug → değer slug eşlemesi. Boş dizi
            | EKSENSİZ ürün demek ve bu geçerli bir durum — kutucuk mantığı
            | onu kapsam dışı bırakmalı, yoksa tek varyantlı ürünlerin
            | sayfası bozulur.
            */
            'secenekler' => $v->options ?? [],
            'fiyat' => (string) $v->price,

            // ★ TEK KAYNAK: sepetin kullandığı kuralın kendisi.
            'satilabilir' => $v->satinAlinabilirMi(),
        ])->values()->all();

        return [
            'eksenler' => $this->eksenler($urun, $varyantlar),
            'varyantlar' => $varyantlar,

            /*
            | ⚠️ Eksensiz üründe seçilecek bir şey yok; sayfa gizli girdiyle
            | çalışmaya devam ediyor.
            */
            'tekVaryant' => $urun->options->isEmpty() && count($varyantlar) === 1
                ? $varyantlar[0]['uuid']
                : null,
        ];
    }

    /**
     * @param  list<array{uuid: string, secenekler: array<string, string>, fiyat: string, satilabilir: bool}>  $varyantlar
     * @return list<array{slug: string, ad: string, listeMi: bool, degerler: list<array{slug: string, ad: string, swatch: string|null}>}>
     */
    private function eksenler(Product $urun, array $varyantlar): array
    {
        /*
        | ⚠️ Yalnızca VARYANTLARDA GEÇEN değerler gösteriliyor. Eksenin
        | tanımlı tüm değerleri basılsaydı müşteri hiç üretilmemiş bir
        | bedeni görür ve neden seçemediğini anlamazdı — "stokta yok" ile
        | "böyle bir şey yok" aynı şey değil.
        */
        $kullanilan = [];

        foreach ($varyantlar as $varyant) {
            foreach ($varyant['secenekler'] as $eksenSlug => $degerSlug) {
                $kullanilan[$eksenSlug][$degerSlug] = true;
            }
        }

        $liste = [];

        foreach ($urun->options as $eksen) {
            /** @var Option $eksen */
            $eksenSlug = (string) $eksen->slug;

            /** @var list<array{slug: string, ad: string, swatch: string|null}> $degerler */
            $degerler = $eksen->values
                ->filter(fn (OptionValue $d) => isset($kullanilan[$eksenSlug][(string) $d->slug]))
                ->map(fn (OptionValue $d) => [
                    'slug' => (string) $d->slug,
                    'ad' => (string) $d->value,

                    // ⚠️ Renk kutucuğu için; yoksa yalnızca metin basılıyor.
                    'swatch' => $d->swatch,
                ])
                ->values()
                ->all();

            if ($degerler === []) {
                continue;
            }

            $liste[] = [
                'slug' => $eksenSlug,
                'ad' => (string) $eksen->name,

                /*
                | ⚠️ Eşiği AŞAN eksen açılır listeye düşüyor: 30 bedenlik
                | bir eksen kutucuk olarak basılsaydı sayfa okunamaz
                | hâle gelirdi.
                */
                'listeMi' => count($degerler) > self::LISTE_ESIGI,
                'degerler' => $degerler,
            ];
        }

        return $liste;
    }
}
