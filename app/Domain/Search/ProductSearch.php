<?php

namespace App\Domain\Search;

use App\Domain\Catalog\ProductQuery;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ürün araması. (2C)
 *
 * ★ İKİ YÖNTEM BİRLİKTE:
 *
 *   FTS       kök bulma — "tişörtler" araması "tişört"ü buluyor
 *   trigram   yazım hatası — "tsiort" araması "tisort"u buluyor
 *
 * Üretimde ikisi birlikte kullanılıyor; tek başına ikisi de eksik:
 * FTS yazım hatasını affetmiyor, trigram kök bilmiyor.
 *
 * ⚠️ `ProductQuery::forStorefront()` üzerinden gidiyor — taslak, arşiv ve
 * satılamayan ürün aramada da çıkmıyor (1B-K10). Doğrudan `Product::query()`
 * yazılsaydı arama, vitrinin göstermediği ürünleri gösterirdi.
 */
class ProductSearch
{
    /**
     * Kelime benzerliği eşiği (`pg_trgm.word_similarity_threshold`).
     *
     * ⚠️ PostgreSQL varsayılanı 0,6 — ÇOK YÜKSEK. Gerçek katalog metinleri
     * üzerinde ölçüldü (hedef = doğru ürün, gürültü = en yüksek yanlış):
     *
     * ```
     * arama         hedef   gürültü
     * cuzdn         0,667   0,000
     * gomlek        1,000   0,286
     * kolleksyon    0,467   0,091
     * tsiort        0,286   0,000
     * ```
     *
     * 0,3 seçildi çünkü en yüksek ÖLÇÜLEN gürültünün (0,286) hemen
     * üstünde. Varsayılan 0,6 bırakılsaydı yazım hatası toleransı hiç
     * çalışmazdı ve bu HATA VERMEZDİ — arama sessizce boş dönerdi.
     *
     * ⚠️ SINIR DÜRÜSTÇE KAYDEDİLİYOR: "tsiort" (6 harfte iki harf yer
     * değiştirmiş) 0,286 alıyor ve BULUNMUYOR. Eşiği oraya indirmek
     * "gomlek"in yanlış ürünü getirmesi demekti. Trigram her yazım
     * hatasını kurtarmaz — kurtardığı iddia edilseydi test yalan söylerdi.
     *
     * ⚠️ Oturum ayarı olduğu için her sorgudan ÖNCE veriliyor.
     */
    public const ESIK = 0.3;

    public function __construct(private readonly ProductQuery $sorgu) {}

    /**
     * @return Builder<Product>
     */
    public function ara(string $kelime): Builder
    {
        $normal = SearchText::normallestir($kelime);
        $sorgu = $this->sorgu->forStorefront();

        if ($normal === '') {
            /*
            | ⚠️ Boş arama HER ŞEYİ döndürmüyor — hiçbir şey döndürüyor.
            | Aksi hâlde "?q=" ile gelen istek tüm katalogu tarardı ve
            | arama sayfası liste sayfasından ayırt edilemezdi.
            */
            return $sorgu->whereRaw('1 = 0');
        }

        /*
        | ⚠️ Eşik OTURUM AYARI. `SET LOCAL` transaction gerektiriyor;
        | burada oturum düzeyinde veriliyor ki her sorgu için geçerli olsun.
        */
        DB::statement('SET pg_trgm.word_similarity_threshold = '.self::ESIK);

        return $sorgu->where(function (Builder $alt) use ($kelime, $normal) {
            /*
            | 1 — TÜRKÇE FTS. Sözlük `pg_catalog`'ta, marka şemasından
            | görünüyor (ölçüldü). Kök bulma buradan geliyor.
            */
            $alt->whereRaw("search_vector @@ plainto_tsquery('turkish', ?)", [$kelime]);

            /*
            | 2 — TRIGRAM (yazım hatası toleransı).
            |
            | ⚠️ ★ `similarity()` DEĞİL `<%` (word_similarity) — ÖLÇÜLDÜ:
            |
            | ```
            | similarity('basic tisort demo ts-1', 'tsiort')      = 0,20  ✗
            | word_similarity('tsiort', 'basic tisort demo ts-1') = 0,33  ✓
            | ```
            |
            | `similarity()` metnin TAMAMIYLA karşılaştırıyor: arama
            | kelimesi kısa, metin uzun olduğu için puan eşiğin altına
            | düşüyor ve HİÇBİR ŞEY BULUNMUYOR. `word_similarity` arama
            | kelimesini metnin EN İYİ EŞLEŞEN PARÇASIYLA karşılaştırıyor.
            |
            | ⚠️ Operatör biçimi (`<%`) bilinçli: fonksiyon biçimi GIN
            | indeksini KULLANMIYOR. Ölçüldü — plan `Bitmap Index Scan on
            | products_search_text_trgm_idx` gösteriyor.
            |
            | ⚠️ `OPERATOR(public.<%)` — nitelik ZORUNLU: eklenti
            | `public`'te ve marka `search_path`'i onu görmüyor
            | (citext/ltree ile aynı tuzak, üçüncü kez).
            |
            | ⚠️ Karşılaştırma ASCII'ye indirilmiş metin üzerinde: Türkçe
            | karakter benzerliği eşiğin altına düşürüyor.
            */
            $alt->orWhereRaw('? OPERATOR(public.<%) search_text', [$normal]);
        })
            /*
            | ★ SIRALAMA — FTS'İN ASIL İŞİ.
            |
            | ⚠️ ÖLÇÜLDÜ, ve tasarımı bu ölçüm değiştirdi: eşik 0,3'te
            | trigram, FTS'in bulduğu HER ŞEYİ zaten buluyor —
            |   "tişörtler"    → trigram 0,60
            |   "gömlekleri"   → trigram 0,55
            |   "ayakkabıları" → trigram 0,62
            | FTS kolu koddan silindiğinde hiçbir test kırılmadı. Yani
            | "bulma"da FTS'in katkısı YOK; katkısı SIRALAMADA.
            |
            | `setweight` ile başlık (A) markadan (B) ağır yazılıyor:
            | "Nike" araması, adında Nike geçen ürünü markası Nike olan
            | ürünün ÜSTÜNE taşıyor. Sıralanmasaydı sonuçlar `id` sırasında
            | gelirdi — yani alakaya değil, ekleme sırasına göre.
            |
            | ⚠️ Trigram'la bulunan (FTS'in bulamadığı) satırın rank'i 0;
            | doğal olarak altta kalıyor — istenen davranış.
            */
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('turkish', ?)) DESC", [$kelime])
            ->orderByDesc('id');
    }

    /**
     * Bir ürünün arama alanlarını tazeler.
     *
     * ⚠️ Ürün ya da varyant değiştiğinde ÇAĞRILMAK ZORUNDA. Unutulursa
     * arama bayat kalır ve bu HATA VERMEZ — yalnızca yeni ürün
     * bulunamaz.
     */
    public function tazele(Product $urun): void
    {
        $urun->loadMissing('variants');

        /*
        | ★ SKU `search_text`'E GİRMİYOR — ÖLÇÜMLE DEĞİŞTİ.
        |
        | Önce SKU'lar da buraya yazılıyordu. Testte 9 değil 1 varyant
        | olduğu için sorun görünmedi; GERÇEK markada 9 SKU'lu ürünün
        | metni şu hâle geldi:
        |   "basic tisort demo bt-9 bt-8 bt-4 … bt-5"
        | ve word_similarity('tsiort', …) 0,33'ten 0,286'ya DÜŞTÜ — yani
        | aynı ürün, VARYANT SAYISI ARTTIĞI İÇİN aranamaz oldu.
        |
        | ⚠️ Bu hata testlerde görünmedi, iki markada gerçek HTTP koşusu
        | yakaladı (1D.6'nın aynısı).
        |
        | SKU yazım hatası aranacak bir şey değil — müşteri kodu tam
        | yazar. Bu yüzden SKU FTS'e (C ağırlığı) gidiyor: orada TAM
        | TOKEN eşleşmesi yapıyor ve metnin uzunluğunu etkilemiyor.
        */
        $metin = implode(' ', array_filter([$urun->title, $urun->brand, $urun->model]));

        $skular = $urun->variants->pluck('sku')->implode(' ');

        DB::table('products')->where('id', $urun->id)->update([
            'search_text' => SearchText::normallestir($metin),

            /*
            | ⚠️ Ağırlıklandırma: başlık (A) > marka/model (B) > SKU (C).
            | Tek `to_tsvector` kullanılsaydı marka adı ürün adıyla aynı
            | ağırlıkta çıkar ve "Nike" araması Nike'ın olmayan ürününü
            | de üste taşırdı.
            |
            | ⚠️ SKU'nun burada olması ZORUNLU: `search_text`'ten çıkarıldı
            | (yukarıdaki ölçüm), tek bulunma yolu bu. Ölçüldü —
            | `to_tsvector('turkish','BT-1')` → `'bt':1 '-1':2` ve
            | `plainto_tsquery('turkish','bt-1')` ile eşleşiyor.
            */
            'search_vector' => DB::raw(sprintf(
                "setweight(to_tsvector('turkish', %s), 'A') || setweight(to_tsvector('turkish', %s), 'B') || setweight(to_tsvector('turkish', %s), 'C')",
                DB::connection()->getPdo()->quote((string) $urun->title),
                DB::connection()->getPdo()->quote(trim(((string) $urun->brand).' '.((string) $urun->model))),
                DB::connection()->getPdo()->quote($skular),
            )),
        ]);
    }
}
