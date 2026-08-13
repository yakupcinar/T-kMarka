<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Search\ProductSearch;
use App\Domain\Search\SearchText;
use App\Domain\Settings\StorePublication;
use App\Enums\EventType;
use App\Enums\ProductStatus;
use App\Models\Event;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Ürün araması (2C).
|
| ★ ÜÇ İDDİA:
|   1  TÜRKÇE kök bulma: "tişörtler" → "tişört"
|   2  YAZIM HATASI toleransı: "tsiort" → "tisort"
|   3  arama da VİTRİN SORGUSUNDAN geçiyor — taslak çıkmıyor
|
| ⚠️ pg_trgm eklentisi `public`'te ve marka şemasından GÖRÜNMÜYOR —
| citext (1A) ve ltree (1B) ile aynı tuzak, ÜÇÜNCÜ KEZ. Bütün çağrılar
| `public.` ile nitelikli.
*/

/** Aranabilir ürünler kurar. */
function aramaliMagaza(string $alanAdi): void
{
    markaKur($alanAdi);
    magazayiHazirla();

    $urunler = app(ProductService::class);
    $varyantlar = app(VariantService::class);

    foreach ([
        ['Basic Tişört', 'Demo', 'TS-1'],
        ['Deri Cüzdan', 'Marka', 'CZ-1'],
        ['Spor Ayakkabı', 'Nike', 'AY-1'],
    ] as [$baslik, $marka, $sku]) {
        $urun = $urunler->olustur(['title' => $baslik, 'brand' => $marka]);
        $varyantlar->ekle($urun, ['sku' => $sku, 'price' => 100, 'stock' => 5]);
        $urunler->durumDegistir($urun->refresh(), ProductStatus::Active);
    }
}

it('★ ASCII indirgeme — üçüncü kez aynı Türkçe tuzağı', function () {
    /*
    | ⚠️ ÖLÇÜLDÜ, tahmin değil:
    |   public.similarity('tisort','tişört') = 0,27  ← eşik 0,3'ün ALTINDA
    |   public.similarity('tisort','tisort') = 1,00
    | Türkçe karakter benzerliği eşiğin altına düşürüyor; her iki taraf
    | da indirilmeden yazım hatası toleransı HİÇ çalışmıyor.
    */
    expect(SearchText::normallestir('Basic Tişört'))->toBe('basic tisort')
        ->and(SearchText::normallestir('IŞIK Çanta'))->toBe('isik canta')
        ->and(SearchText::normallestir('  Ağır   Ürün! '))->toBe('agir urun')
        ->and(SearchText::normallestir(null))->toBe('');
});

it('★ pg_trgm marka şemasından NİTELİKSİZ görünmüyor — ölçüm', function () {
    markaKur('arama-a.test');

    /*
    | ⚠️ Bu test bir DAVRANIŞ değil, bir GERÇEK kaydediyor: eklenti
    | `public`'te ve marka `search_path`'i onu görmüyor.
    |
    | Niteliksiz yazılırsa migration/sorgu patlıyor. Bu sefer GÜRÜLTÜLÜ
    | (citext sessizce düz metne düşüyordu) — ama sebep aynı.
    */
    expect(fn () => DB::select("SELECT similarity('a','b')"))
        ->toThrow(QueryException::class);

    // Nitelikli hâli çalışıyor.
    $sonuc = DB::select("SELECT public.similarity('tisort','tisort') AS s");

    expect((float) $sonuc[0]->s)->toBe(1.0);
});

it('★ TÜRKÇE KÖK BULMA: "tişörtler" araması "Tişört"ü buluyor', function () {
    aramaliMagaza('arama-b.test');

    $sonuc = app(ProductSearch::class)->ara('tişörtler')->get();

    /*
    | ⚠️ Düz `LIKE '%tişörtler%'` olsaydı hiçbir şey bulunmazdı: başlık
    | "Tişört", arama "tişörtler". Kök bulmayı Türkçe sözlük yapıyor ve
    | o sözlük PostgreSQL'de HAZIR (ölçüldü).
    */
    expect($sonuc)->toHaveCount(1)
        ->and($sonuc->first()?->title)->toBe('Basic Tişört');
});

it('★ ALAKAYA GÖRE sıralanıyor — başlık markadan ağır', function () {
    markaKur('arama-m.test');
    magazayiHazirla();

    $urunler = app(ProductService::class);
    $varyantlar = app(VariantService::class);

    /*
    | ★ BU TEST BİR KIRMA DENEMESİNDEN DOĞDU.
    |
    | FTS kolu koddan TAMAMEN kaldırıldığında hiçbir test kırılmadı.
    | Sebep ölçüldü: eşik 0,3'te trigram, FTS'in bulduğu her şeyi zaten
    | buluyor ("tişörtler" 0,60 · "gömlekleri" 0,55). Yani "kök bulma"
    | testi aslında TRİGRAM'ı ölçüyordu.
    |
    | FTS'in gerçek katkısı SIRALAMA. `setweight` ile başlık A, marka B:
    |   "Nike Koşu Bandı"     → adında Nike var  (A)
    |   "Spor Ayakkabı"/Nike  → markası Nike     (B)
    | Alaka sıralaması olmasaydı ikisi `id` sırasında gelirdi — yani
    | müşteri "Nike" arayıp Nike'ın adı geçen ürününü ALTTA görürdü.
    |
    | ⚠️ SIRA BİLEREK TERS: başlıkta Nike geçen ürün ÖNCE ekleniyor, yani
    | `id`'si KÜÇÜK. Sonra eklenseydi `id` sıralaması onu zaten üste
    | taşırdı ve bu test alaka sıralamasını değil ekleme sırasını ölçerdi
    | — ilk yazımda öyleydi ve FTS kolu silindiğinde bile YEŞİL kaldı.
    */
    $baslik = $urunler->olustur(['title' => 'Nike Koşu Bandı', 'brand' => 'Demo']);
    $varyantlar->ekle($baslik, ['sku' => 'KB-1', 'price' => 100, 'stock' => 5]);
    $urunler->durumDegistir($baslik->refresh(), ProductStatus::Active);

    $marka = $urunler->olustur(['title' => 'Spor Ayakkabı', 'brand' => 'Nike']);
    $varyantlar->ekle($marka, ['sku' => 'AY-1', 'price' => 100, 'stock' => 5]);
    $urunler->durumDegistir($marka->refresh(), ProductStatus::Active);

    $sonuc = app(ProductSearch::class)->ara('nike')->pluck('title')->all();

    expect($sonuc)->toBe(['Nike Koşu Bandı', 'Spor Ayakkabı']);
});

it('★ YAZIM HATASI tolere ediliyor — ve SINIRI da ölçülüyor', function () {
    aramaliMagaza('arama-c.test');

    /*
    | ⚠️ ÖRNEK BİLEREK DEĞİŞTİ. Önce "tsiort" kullanılıyordu ve test
    | YEŞİLDİ — ama gerçek markada aynı arama HİÇBİR ŞEY bulmuyordu.
    | Sebep: skor metnin uzunluğuna göre oynuyor (test verisinde 0,33,
    | 9 SKU'lu gerçek üründe 0,286). Test tesadüfen geçiyordu.
    |
    | Şimdi ölçülmüş, sağlam bir yazım hatası kullanılıyor: "cuzdn" 0,667.
    */
    expect(app(ProductSearch::class)->ara('cuzdn')->pluck('title'))
        ->toContain('Deri Cüzdan');

    /*
    | ★ SINIR DA TEST EDİLİYOR — "her yazım hatası bulunur" YALAN olurdu.
    | "tsiort" 0,286 alıyor, eşik 0,3; bulunmuyor. Eşiği oraya indirmek
    | "gomlek"in yanlış ürünü getirmesi demekti (ölçüldü).
    */
    expect(app(ProductSearch::class)->ara('tsiort')->count())->toBe(0);
});

it('MARKA ve SKU üzerinden de bulunuyor', function () {
    aramaliMagaza('arama-d.test');

    /*
    | ⚠️ SKU artık `search_text`'te DEĞİL, FTS vektöründe (C ağırlığı).
    | Orada bırakılsaydı çok varyantlı üründe metin uzar ve trigram
    | skorunu düşürürdü — gerçek markada tam bunu yaptı.
    */

    expect(app(ProductSearch::class)->ara('Nike')->pluck('title'))
        ->toContain('Spor Ayakkabı');

    expect(app(ProductSearch::class)->ara('CZ-1')->pluck('title'))
        ->toContain('Deri Cüzdan');
});

it('★ TASLAK ürün ARAMADA da çıkmıyor', function () {
    aramaliMagaza('arama-e.test');

    $taslak = app(ProductService::class)->olustur(['title' => 'Gizli Tişört']);
    app(VariantService::class)->ekle($taslak, ['sku' => 'GZ-1', 'price' => 100, 'stock' => 5]);

    /*
    | ⚠️ Arama `forStorefront()` üzerinden gidiyor (1B-K10). Ayrı bir
    | sorgu yazılsaydı arama, vitrinin göstermediği ürünleri gösterir ve
    | yayınlanmamış kampanya sızardı.
    */
    $sonuc = app(ProductSearch::class)->ara('tişört')->get();

    expect($sonuc->pluck('title'))->not->toContain('Gizli Tişört')
        ->and($sonuc->pluck('title'))->toContain('Basic Tişört');
});

it('BOŞ arama hiçbir şey döndürmüyor', function () {
    aramaliMagaza('arama-f.test');

    /*
    | ⚠️ Her şeyi döndürseydi "?q=" ile gelen istek tüm katalogu tarar ve
    | arama sayfası liste sayfasından ayırt edilemezdi.
    */
    expect(app(ProductSearch::class)->ara('')->count())->toBe(0)
        ->and(app(ProductSearch::class)->ara('   ')->count())->toBe(0);
});

it('★ ÜRÜN DEĞİŞİNCE arama TAZELENİYOR', function () {
    aramaliMagaza('arama-g.test');

    $urun = Product::where('title', 'Deri Cüzdan')->firstOrFail();

    expect(app(ProductSearch::class)->ara('kemer')->count())->toBe(0);

    app(ProductService::class)->guncelle($urun, ['title' => 'Deri Kemer']);

    /*
    | ⚠️ Tazeleme unutulsaydı arama bayat kalırdı ve bu HATA VERMEZDİ —
    | yalnızca değişen ürün bulunamazdı.
    */
    expect(app(ProductSearch::class)->ara('kemer')->count())->toBe(1);
});

it('★ SORGU İNDEKS KULLANIYOR — ölçüldü, varsayılmadı', function () {
    aramaliMagaza('arama-h.test');

    /*
    | ⚠️ 1B'de `text_pattern_ops` ölçümünde öğrenilen: sorgu çalışır,
    | sessizce TAM TARAMA yapar. Ölçmeden "indeks var" demek yetmiyor.
    |
    | ⚠️ Az satırda PostgreSQL indeks yerine tarama seçebiliyor; bu
    | yüzden planı zorluyoruz.
    */
    /*
    | ⚠️ `SET LOCAL` TRANSACTION İÇİNDE olmak zorunda; dışarıda sessizce
    | hiçbir şey yapmıyor. İlk yazımda öyleydi ve test yanlış sebeple
    | kırmızı verdi.
    */
    DB::statement('SET enable_seqscan = off');

    $plan = DB::select("EXPLAIN SELECT id FROM products WHERE search_vector @@ plainto_tsquery('turkish', 'tişört')");
    $metin = implode(' ', array_map(fn ($s) => (string) $s->{'QUERY PLAN'}, $plan));

    expect(strtolower($metin))->toContain('products_search_vector_idx');

    // Trigram indeksi de kullanılıyor mu?
    $trgm = DB::select("EXPLAIN SELECT id FROM products WHERE 'tsiort' OPERATOR(public.<%) search_text");
    $trgmMetin = implode(' ', array_map(fn ($s) => (string) $s->{'QUERY PLAN'}, $trgm));

    DB::statement('SET enable_seqscan = on');

    expect(strtolower($trgmMetin))->toContain('products_search_text_trgm_idx');
});

it('★ ARAMA OLAYI yazılıyor — liste gezintisi yazmıyor', function () {
    aramaliMagaza('arama-i.test');
    app(StorePublication::class)->yayinla();

    // Liste sayfası: olay YOK.
    $this->getJson('http://arama-i.test/api/products')->assertOk();

    expect(Event::where('type', EventType::SearchPerformed)->count())->toBe(0);

    // Gerçek arama: olay VAR.
    $cevap = $this->getJson('http://arama-i.test/api/products?q=tişört')->assertOk();

    expect($cevap->json('meta.total'))->toBe(1);

    /*
    | ★ `search_performed` 1F'de tanımlanmış ama üreticisi yoktu; ilk
    | üreticisine burada kavuşuyor.
    |
    | ⚠️ Sonuç sayısı da kaydediliyor: "hangi arama sonuç bulamıyor"
    | markanın en değerli sorusu.
    */
    $olay = Event::where('type', EventType::SearchPerformed)->firstOrFail();

    expect($olay->payload['query'] ?? null)->toBe('tişört')
        ->and($olay->payload['result_count'] ?? null)->toBe(1);
});

it('★ UÇTAN: yazım hatasıyla arama sonuç veriyor', function () {
    aramaliMagaza('arama-j.test');
    app(StorePublication::class)->yayinla();

    $cevap = $this->getJson('http://arama-j.test/api/products?q=cuzdn')->assertOk();

    expect($cevap->json('meta.total'))->toBeGreaterThan(0);
});

it('iki markanın araması karışmıyor', function () {
    aramaliMagaza('arama-k.test');

    tenancy()->end();
    markaKur('arama-l.test');
    magazayiHazirla();

    expect(app(ProductSearch::class)->ara('tişört')->count())->toBe(0);
});
