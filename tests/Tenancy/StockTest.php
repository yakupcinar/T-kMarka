<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Stock\InsufficientStockException;
use App\Domain\Stock\StockService;
use App\Enums\ProductStatus;
use App\Enums\ReservationStatus;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Stok rezervasyonu ve AŞIRI SATIŞ engeli (1D-K1/K5/K6).
|
| Bu bloğun varlık sebebi tek cümle: iki müşteri son ürünü aynı anda
| almaya çalışırsa yalnızca biri alabilmeli — ve bu, hata vermeden
| bozulabilecek bir kural.
*/

function stokluVaryant(string $alanAdi, int $stok = 5): ProductVariant
{
    markaKur($alanAdi);

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, [
        'sku' => 'TS-1', 'price' => 100, 'stock' => $stok,
    ]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $varyant;
}

it('rezervasyon committed sayacını artırıyor, stoğa dokunmuyor', function () {
    $varyant = stokluVaryant('stok-a.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    app(StockService::class)->sepetiRezerveEt($sepet);

    $varyant->refresh();

    // ⚠️ `stock` (on_hand) DEĞİŞMİYOR — ürün hâlâ depoda.
    // Değişen `committed`: artık başkasına satılamaz.
    expect($varyant->stock)->toBe(5)
        ->and($varyant->committed)->toBe(2)
        ->and($varyant->satilabilirAdet())->toBe(3);
});

it('★ AŞIRI SATIŞ: aynı stoğu iki kez rezerve edemiyor', function () {
    $varyant = stokluVaryant('stok-b.test', stok: 3);
    $sepetler = app(CartService::class);
    $stok = app(StockService::class);

    $birinci = $sepetler->misafirSepetiOlustur();
    $sepetler->ekle($birinci, $varyant, 3);
    $stok->sepetiRezerveEt($birinci);

    // İkinci müşteri: stok 3 ama hepsi bağlanmış.
    $ikinci = $sepetler->misafirSepetiOlustur();
    $satir = $ikinci->items()->make(['quantity' => 1]);
    $satir->variant()->associate($varyant);
    $satir->save();

    expect(fn () => $stok->sepetiRezerveEt($ikinci))
        ->toThrow(InsufficientStockException::class);

    expect($varyant->refresh()->committed)->toBe(3);
});

it('★ EŞZAMANLILIK: iki AYRI BAĞLANTI aynı satıra saldırıyor', function () {
    $varyant = stokluVaryant('stok-c.test', stok: 1);

    /*
    | ⚠️ Bu testin özel olması gerekiyor: aynı bağlantıda iki transaction
    | açmak eşzamanlılığı TAKLİT ETMEZ — PostgreSQL onları sıraya sokmaz,
    | çünkü tek oturum var.
    |
    | Bu yüzden İKİNCİ BİR BAĞLANTI açıyoruz ('tenant' bağlantısının
    | kopyası) ve gerçekten iki oturum çakıştırıyoruz.
    */
    $ikinciBaglanti = 'ikinci_oturum';
    config(["database.connections.{$ikinciBaglanti}" => config('database.connections.tenant')]);
    DB::purge($ikinciBaglanti);

    // 1. oturum: satırı kilitle, HENÜZ COMMIT ETME.
    DB::beginTransaction();
    $kilitli = ProductVariant::where('id', $varyant->id)->lockForUpdate()->firstOrFail();
    $kilitli->committed = 1;
    $kilitli->save();

    /*
    | 2. oturum: aynı satırı kilitlemeye çalışıyor. 1. oturum commit
    | etmediği için BEKLEYECEK — ve `lock_timeout` devreye girecek (1D-K6).
    |
    | Zaman aşımı olmasaydı bu bekleme SONSUZA kadar sürerdi: takılan tek
    | bir işlem arkasındaki bütün ödeme isteklerini asardı.
    */
    DB::connection($ikinciBaglanti)->statement("SET lock_timeout = '1s'");

    $zamanAsimi = false;
    $baslangic = microtime(true);

    try {
        DB::connection($ikinciBaglanti)->select(
            'SELECT * FROM product_variants WHERE id = ? FOR UPDATE',
            [$varyant->id],
        );
    } catch (Throwable $e) {
        $zamanAsimi = str_contains($e->getMessage(), 'lock timeout')
            || str_contains($e->getMessage(), 'canceling statement');
    }

    $gecenSure = microtime(true) - $baslangic;

    DB::rollBack();
    DB::purge($ikinciBaglanti);

    // İkinci oturum GERÇEKTEN bekledi ve zaman aşımına uğradı.
    expect($zamanAsimi)->toBeTrue()
        ->and($gecenSure)->toBeGreaterThan(0.9);
});

it('kesinleştirme stoğu düşürüyor, committed i azaltıyor', function () {
    $varyant = stokluVaryant('stok-d.test', stok: 5);
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    $rezervasyonlar = app(StockService::class)->sepetiRezerveEt($sepet);
    $rezervasyon = $rezervasyonlar->firstOrFail();
    app(StockService::class)->kesinlestir($rezervasyon);

    $varyant->refresh();

    // Artık "bağlanmış" değil, SATILMIŞ: ikisi birden düştü.
    expect($varyant->stock)->toBe(3)
        ->and($varyant->committed)->toBe(0)
        ->and($rezervasyon->refresh()->status)->toBe(ReservationStatus::Committed);
});

it('serbest bırakma STOĞA dokunmuyor, yalnızca bağı çözüyor', function () {
    $varyant = stokluVaryant('stok-e.test', stok: 5);
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    $rezervasyonlar = app(StockService::class)->sepetiRezerveEt($sepet);
    app(StockService::class)->serbestBirak($rezervasyonlar->firstOrFail());

    $varyant->refresh();

    // Stok hiç düşmemişti — ödeme başarısız oldu, ürün depoda kaldı.
    expect($varyant->stock)->toBe(5)
        ->and($varyant->committed)->toBe(0)
        ->and($varyant->satilabilirAdet())->toBe(5);
});

it('süresi dolan rezervasyon düşürülüyor', function () {
    $varyant = stokluVaryant('stok-f.test', stok: 5);
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 3);

    $rezervasyonlar = app(StockService::class)->sepetiRezerveEt($sepet);

    // 15 dakika geçti.
    $rezervasyonlar->firstOrFail()->forceFill(['expires_at' => now()->subMinute()])->save();

    $dusen = app(StockService::class)->suresiDolanlariDusur();

    expect($dusen)->toBe(1)
        ->and($varyant->refresh()->committed)->toBe(0)
        ->and($varyant->satilabilirAdet())->toBe(5);
});

it('★ TUTARLILIK DENETİMİ bozuk sayacı yakalıyor', function () {
    $varyant = stokluVaryant('stok-g.test', stok: 10);
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 4);

    $stok = app(StockService::class);
    $stok->sepetiRezerveEt($sepet);

    // Sağlam durumda tutarsızlık yok.
    expect($stok->tutarsizliklar())->toBe([]);

    /*
    | ⚠️ Sayacı elle bozuyoruz — gerçekte bu, bir rezervasyon serbest
    | bırakılırken sayacın güncellenmemesiyle olurdu.
    |
    | Materyalleştirilmiş sayacın BEDELİ bu; denetim de karşılığı.
    | Shopify'ın "her konumda tutması gereken özdeşlik" dediği şey.
    */
    DB::table('product_variants')->where('id', $varyant->id)->update(['committed' => 7]);

    $tutarsiz = $stok->tutarsizliklar();

    expect($tutarsiz)->toHaveCount(1)
        ->and($tutarsiz[0]['sku'])->toBe('TS-1')
        ->and($tutarsiz[0]['committed'])->toBe(7)
        ->and($tutarsiz[0]['rezervasyon_toplami'])->toBe(4);
});

it('committed negatife düşemiyor', function () {
    $varyant = stokluVaryant('stok-h.test');

    expect(fn () => DB::table('product_variants')
        ->where('id', $varyant->id)->update(['committed' => -1]))
        ->toThrow(QueryException::class);
});

it('vitrin satılabilir adedi committed düşülerek gösteriyor', function () {
    $varyant = stokluVaryant('stok-i.test', stok: 5);
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 5);

    app(StockService::class)->sepetiRezerveEt($sepet);

    // Stok 5 ama hepsi bağlanmış → ürün artık satılamaz.
    expect($varyant->refresh()->satinAlinabilirMi())->toBeFalse()
        ->and(ProductVariant::satinAlinabilir()->count())->toBe(0);
});

it('rezervasyon kilit SIRASI sabit — kilitlenme olmuyor', function () {
    markaKur('stok-j.test');
    $urunler = app(ProductService::class);
    $varyantlar = app(VariantService::class);

    $urunA = $urunler->olustur(['title' => 'A']);
    $a = $varyantlar->ekle($urunA, ['sku' => 'A-1', 'price' => 10, 'stock' => 5]);
    $urunB = $urunler->olustur(['title' => 'B']);
    $b = $varyantlar->ekle($urunB, ['sku' => 'B-1', 'price' => 10, 'stock' => 5]);

    $sepetler = app(CartService::class);
    $sepet = $sepetler->misafirSepetiOlustur();

    // Sepete TERS sırada ekleniyor; servis yine de id sırasına göre kilitlemeli.
    $sepetler->ekle($sepet, $b, 1);
    $sepetler->ekle($sepet, $a, 1);

    $rezervasyonlar = app(StockService::class)->sepetiRezerveEt($sepet);

    // Sıra sabit olmasaydı iki eşzamanlı sepet birbirini kilitlerdi.
    expect($rezervasyonlar->pluck('variant_id')->all())->toBe([$a->id, $b->id]);
});

it('iki markanın stoğu karışmıyor', function () {
    $a = stokluVaryant('stok-k.test', stok: 3);
    $sepetA = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepetA, $a, 3);
    app(StockService::class)->sepetiRezerveEt($sepetA);

    tenancy()->end();
    stokluVaryant('stok-l.test', stok: 3);

    // B markasında hiç rezervasyon yok.
    expect(StockReservation::count())->toBe(0)
        ->and(ProductVariant::first()?->committed)->toBe(0);
});

it('★ rezervasyon sorgusu GERÇEKTEN "for update" içeriyor', function () {
    $varyant = stokluVaryant('stok-m.test', stok: 5);
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    /*
    | ⚠️ BU TESTİN VARLIK SEBEBİ BİR BOŞLUK.
    |
    | Ölçüldü: `lockForUpdate()` servisten silindiğinde diğer 11 testin
    | HİÇBİRİ kırılmıyor. Eşzamanlılık testi PostgreSQL'in kilidinin
    | çalıştığını kanıtlıyor ama BİZİM SERVİSİMİZİN onu kullandığını
    | kanıtlamıyor — gerçek eşzamanlılık için servisi ikinci bir
    | bağlantıda koşturmak gerekirdi ve modeller sabit bağlantı kullanıyor.
    |
    | Bu yüzden YAPISAL bir test: üretilen SQL'de kilit var mı?
    | Davranışsal testten zayıf, ama silinmeyi yakalıyor — ve boşluğun
    | kendisi burada yazılı duruyor.
    */
    DB::flushQueryLog();
    DB::enableQueryLog();

    app(StockService::class)->sepetiRezerveEt($sepet);

    $kilitliSorgu = collect(DB::getQueryLog())
        ->contains(fn (array $kayit) => str_contains(strtolower((string) $kayit['query']), 'for update'));

    DB::disableQueryLog();

    expect($kilitliSorgu)->toBeTrue();
});

it('lock_timeout transaction ile SINIRLI (SET LOCAL)', function () {
    $varyant = stokluVaryant('stok-n.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    app(StockService::class)->sepetiRezerveEt($sepet);

    /*
    | ⚠️ `SET` yerine `SET LOCAL` kullanılıyor: ayar yalnızca o
    | transaction'da geçerli. `SET` olsaydı bağlantıda kalır ve havuzdan
    | gelen SONRAKİ isteği de etkilerdi — hiçbir ilgisi olmayan bir sorgu
    | 3 saniyede zaman aşımına uğrardı ve sebebi bulunamazdı.
    */
    $ayar = DB::selectOne('SHOW lock_timeout');

    expect($ayar->lock_timeout)->toBe('0');   // 0 = sınırsız (varsayılan)
});
