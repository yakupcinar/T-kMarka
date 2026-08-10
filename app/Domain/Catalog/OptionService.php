<?php

namespace App\Domain\Catalog;

use App\Models\Option;
use App\Models\OptionValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Varyant eksenleri ve değerleri — MAĞAZA seviyesinde (1B-K3).
 *
 * Kurallar burada, controller'da değil: bir artisan komutu ya da içe aktarma
 * işi eksen açarsa da aynı doğrulamalardan geçmeli (1A incelemesinin kuralı).
 */
class OptionService
{
    /** @return Collection<int, Option> */
    public function listele(): Collection
    {
        return Option::with('values')->orderBy('position')->orderBy('id')->get();
    }

    /**
     * @throws EmptySlugException ad slug üretmiyorsa (yalnızca simge vb.)
     */
    public function olustur(string $ad, int $sira = 0): Option
    {
        $option = new Option(['name' => trim($ad), 'position' => $sira]);
        $option->slug = $this->slug($ad);
        $option->save();

        return $option;
    }

    /** @throws EmptySlugException */
    public function guncelle(Option $option, string $ad, int $sira): Option
    {
        $option->fill(['name' => trim($ad), 'position' => $sira]);
        $option->slug = $this->slug($ad);
        $option->save();

        return $option;
    }

    /** @throws EmptySlugException */
    public function degerEkle(Option $option, string $deger, ?string $swatch = null, int $sira = 0): OptionValue
    {
        /*
        | ⚠️ İlişki üzerinden oluşturuluyor — `option_id` $fillable dışında.
        | 1A.5'te adres için kurduğumuz desen: sahiplik alanı istekten gelmez.
        */
        $optionValue = $option->values()->make([
            'value' => trim($deger),
            'swatch' => $swatch,
            'position' => $sira,
        ]);

        $optionValue->slug = $this->slug($deger);
        $optionValue->save();

        return $optionValue;
    }

    /** @throws EmptySlugException */
    public function degerGuncelle(OptionValue $deger, string $yeniDeger, ?string $swatch, int $sira): OptionValue
    {
        $deger->fill(['value' => trim($yeniDeger), 'swatch' => $swatch, 'position' => $sira]);
        $deger->slug = $this->slug($yeniDeger);
        $deger->save();

        return $deger;
    }

    public function sil(Option $option): void
    {
        // TODO(1B.3): ürün bu ekseni kullanıyorsa silinemesin.
        // `product_options` tablosu 1B.3'te doğacak; o blokta buraya
        // rol silmedeki gibi bir kullanım kontrolü eklenecek (409).

        $option->delete();   // değerler cascadeOnDelete ile düşüyor
    }

    public function degerSil(OptionValue $deger): void
    {
        // TODO(1B.3): bu değeri kullanan varyant varsa silinemesin.
        // Silinirse varyantın `options` jsonb'sinde artık var olmayan bir
        // değer kalır ve vitrin "Kırmızı" yazan ama seçilemeyen bir
        // seçenek gösterir.

        $deger->delete();
    }

    /**
     * Ad/değerden slug üretir — BENZERSİZLİK ANAHTARI budur.
     *
     * ⚠️ Küçük harf DEĞİL. Ölçüldü: Türkçe'de `mb_strtolower` bölünüyor
     * ('Kırmızı' → 'kırmızı' ama 'KIRMIZI' → 'kirmizi'), yani aynı değerin
     * iki farklı yazımı iki ayrı satır olurdu — hata vermeden.
     * `Str::slug` hepsini 'kirmizi'de birleştiriyor.
     *
     * ⚠️ Boş slug reddediliyor: "★" ya da "///" gibi bir girdi `Str::slug`'tan
     * boş dönüyor. Boş slug kaydedilseydi ikinci böyle değer benzersizlik
     * kısıtına takılır ve marka sebebini anlamazdı.
     *
     * @throws EmptySlugException
     */
    private function slug(string $metin): string
    {
        $slug = Str::slug($metin);

        if ($slug === '') {
            throw new EmptySlugException($metin);
        }

        return $slug;
    }
}
