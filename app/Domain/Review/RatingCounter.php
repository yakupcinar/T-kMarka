<?php

namespace App\Domain\Review;

use App\Enums\ReviewStatus;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Ürünün ortalama puanı ve yorum sayısı. (2E-K3)
 *
 * ★ `products.rating_avg` / `rating_count` MATERYALLEŞTİRİLMİŞ sayaç —
 * `committed`'ın (1D-K1) aynısı. Her listede `AVG()` çalıştırmak 50
 * ürünlük sayfada ek maliyet demekti.
 *
 * ⚠️ Bedeli DENETİM. Sayaç kendiliğinden düzelmiyor; bozulursa vitrinde
 * yanlış puan görünmeye devam eder ve bu HATA VERMEZ.
 */
class RatingCounter
{
    /**
     * Bir ürünün sayaçlarını ONAYLI yorumlardan yeniden hesaplar.
     *
     * ⚠️ Artırma/azaltma DEĞİL, YENİDEN HESAPLAMA. Artırma yazsaydık her
     * durum geçişinde (onay → red → onay) ayrı bir düzeltme gerekirdi ve
     * biri unutulduğunda sayaç sessizce kayardı.
     */
    public function tazele(Product $urun): void
    {
        /*
        | ⚠️ Yalnızca `approved`. Bekleyen yorum ortalamaya girseydi
        | moderasyonun bir anlamı kalmazdı: yorum vitrinde görünmez ama
        | puanı çoktan etkilemiş olurdu.
        */
        /*
        | ⚠️ `DB::table` — Eloquent DEĞİL: `selectRaw` ile üretilen toplam
        | alanları model üzerinde tanımsız kalıyor ve statik analiz haklı
        | olarak uyarıyor. Burada model davranışına ihtiyaç yok.
        |
        | ⚠️ `deleted_at` ELLE dışlanıyor: `DB::table` yumuşak silmeyi
        | bilmiyor, silinmiş yorum ortalamada kalırdı.
        */
        $satir = DB::table('reviews')
            ->where('product_id', $urun->id)
            ->where('status', ReviewStatus::Approved->value)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) AS adet, AVG(rating) AS ortalama')
            ->first();

        $adet = $satir === null ? 0 : (int) $satir->adet;
        $ortalama = $satir === null ? null : $satir->ortalama;

        /*
        | ⚠️ Yorumu olmayan ürünün ortalaması `null`, `0` DEĞİL. Sıfır
        | yazılsaydı vitrin "0 yıldız" gösterir ve hiç yorum almamış ürün
        | kötü puanlı görünürdü.
        */
        DB::table('products')->where('id', $urun->id)->update([
            'rating_count' => $adet,
            'rating_avg' => $adet === 0 ? null : round((float) $ortalama, 2),
        ]);
    }

    /**
     * Sayaç denetimi — kayıtlı değer gerçekten doğru mu? (2E-K3)
     *
     * ⚠️ ONARMIYOR, haber veriyor. Kendiliğinden düzeltseydi sayacı hangi
     * kod yolunun bozduğu hiç görünmez, her gece sessizce onarılırdı.
     *
     * @return list<array{slug: string, rating_count: int, gercek_adet: int, rating_avg: ?string, gercek_ortalama: ?string}>
     */
    public function tutarsizliklar(): array
    {
        $satirlar = DB::table('products as u')
            ->leftJoin('reviews as y', function ($birlestir): void {
                $birlestir->on('y.product_id', '=', 'u.id')
                    ->where('y.status', '=', ReviewStatus::Approved->value)
                    ->whereNull('y.deleted_at');
            })
            ->whereNull('u.deleted_at')
            ->groupBy('u.id', 'u.slug', 'u.rating_count', 'u.rating_avg')
            /*
            | ⚠️ `IS DISTINCT FROM` — `<>` DEĞİL. `null <> null` sonucu
            | `null`, yani "farklı" sayılmaz: yorumu olmayan ürünlerdeki
            | bozukluk sessizce denetimden kaçardı.
            */
            ->havingRaw('u.rating_count <> COUNT(y.id) OR u.rating_avg IS DISTINCT FROM ROUND(AVG(y.rating), 2)')
            ->select(
                'u.slug',
                'u.rating_count',
                'u.rating_avg',
                DB::raw('COUNT(y.id) AS gercek_adet'),
                DB::raw('ROUND(AVG(y.rating), 2) AS gercek_ortalama'),
            )
            ->get();

        $sonuc = [];

        foreach ($satirlar as $satir) {
            $sonuc[] = [
                'slug' => (string) $satir->slug,
                'rating_count' => (int) $satir->rating_count,
                'gercek_adet' => (int) $satir->gercek_adet,
                'rating_avg' => $satir->rating_avg === null ? null : (string) $satir->rating_avg,
                'gercek_ortalama' => $satir->gercek_ortalama === null ? null : (string) $satir->gercek_ortalama,
            ];
        }

        return $sonuc;
    }
}
