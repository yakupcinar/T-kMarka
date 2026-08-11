<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Stock\StockService;
use App\Enums\ProductStatus;
use App\Enums\ReservationStatus;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/*
| Zamanlanmış görevler (1D.5).
|
| ★ 0.5'te ölçtüğümüz BEŞİNCİ TUZAĞIN ilk gerçek kullanımı:
|   marka verisine dokunan görev `tenants:run` ile sarılmazsa MERKEZ
|   bağlamda koşar, hiçbir şey yapmaz ve HATA DA VERMEZ.
|
| Komutlara kapı koyduk: bağlam yoksa gürültülü hata. Kuralı belgeye
| yazmak yetmiyor, makinenin de söylemesi gerekiyor.
*/

function rezervasyonluVaryant(string $alanAdi, int $stok = 5): ProductVariant
{
    markaKur($alanAdi);

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, [
        'sku' => 'TS-1', 'price' => 100, 'stock' => $stok,
    ]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $varyant;
}

it('★ komut MARKA BAĞLAMI olmadan REDDEDİYOR', function () {
    markaKur('gorev-a.test');
    tenancy()->end();

    /*
    | ⚠️ Bu kapı olmasaydı komut merkez bağlamda "başarılı" döner, hiçbir
    | rezervasyonu düşürmez ve kimse fark etmezdi. Rezervasyonlar birikir,
    | stok sonsuza kadar bağlı kalırdı.
    */
    $this->artisan('stok:rezervasyon-temizle')
        ->expectsOutputToContain('marka bağlamında çalışmalı')
        ->assertFailed();

    $this->artisan('stok:sayac-denetle')
        ->expectsOutputToContain('marka bağlamında çalışmalı')
        ->assertFailed();
});

it('süresi dolan rezervasyon komutla düşüyor', function () {
    $varyant = rezervasyonluVaryant('gorev-b.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    $rezervasyonlar = app(StockService::class)->sepetiRezerveEt($sepet);
    $rezervasyonlar->firstOrFail()->forceFill(['expires_at' => now()->subMinutes(20)])->save();

    expect($varyant->refresh()->committed)->toBe(2);

    $this->artisan('stok:rezervasyon-temizle')->assertSuccessful();

    expect($varyant->refresh()->committed)->toBe(0)
        ->and($varyant->satilabilirAdet())->toBe(5)
        ->and(StockReservation::first()?->status)->toBe(ReservationStatus::Released);
});

it('süresi DOLMAYAN rezervasyona dokunmuyor', function () {
    $varyant = rezervasyonluVaryant('gorev-c.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    app(StockService::class)->sepetiRezerveEt($sepet);

    // Müşteri hâlâ ödeme sayfasında — 15 dakikası dolmadı.
    $this->artisan('stok:rezervasyon-temizle')->assertSuccessful();

    expect($varyant->refresh()->committed)->toBe(2)
        ->and(StockReservation::first()?->status)->toBe(ReservationStatus::Held);
});

it('★ sayaç denetimi tutarsızlığı YAKALIYOR ve ONARMIYOR', function () {
    $varyant = rezervasyonluVaryant('gorev-d.test', stok: 10);
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 3);

    app(StockService::class)->sepetiRezerveEt($sepet);

    // Sağlam durumda sessiz.
    $this->artisan('stok:sayac-denetle')->assertSuccessful();

    // Sayaç bozuldu (gerçekte: bir kod yolu rezervasyonu bırakırken
    // sayacı güncellemedi).
    DB::table('product_variants')->where('id', $varyant->id)->update(['committed' => 9]);

    $this->artisan('stok:sayac-denetle')
        ->expectsOutputToContain('stok sayacı tutmuyor')
        ->assertFailed();

    /*
    | ⚠️ ONARMIYOR — bilerek.
    |
    | Kendiliğinden düzeltseydi asıl sebep (sayacı hangi kod yolu bozdu)
    | hiç görünmez, her gece sessizce onarılır ve sorun kalıcı olurdu.
    | Denetimin işi haber vermek; onarım bilinçli bir karar.
    */
    expect($varyant->refresh()->committed)->toBe(9);
});

it('görevler tenants:run ile zamanlanmış', function () {
    /*
    | ⚠️ Bu test ZAMANLAMANIN KENDİSİNİ koruyor. Biri `tenants:run`
    | önekini kaldırırsa görev sessizce hiçbir şey yapmaya başlar ve
    | hiçbir davranış testi bunu yakalamaz — çünkü komut zaten doğru
    | çalışıyor, yanlış olan ÇAĞRILMA biçimi.
    */
    $zamanlanmis = collect(app(Schedule::class)->events())
        ->map(fn ($olay) => $olay->command ?? '')
        ->filter(fn (string $komut) => str_contains($komut, 'stok:'));

    expect($zamanlanmis)->toHaveCount(2);

    foreach ($zamanlanmis as $komut) {
        expect($komut)->toContain('tenants:run');
    }
});

it('iki markanın rezervasyonları ayrı ayrı temizleniyor', function () {
    $a = rezervasyonluVaryant('gorev-e.test');
    $sepetA = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepetA, $a, 2);
    app(StockService::class)->sepetiRezerveEt($sepetA)
        ->firstOrFail()->forceFill(['expires_at' => now()->subMinutes(20)])->save();

    // A markasında temizlik koştu.
    $this->artisan('stok:rezervasyon-temizle')->assertSuccessful();
    expect($a->refresh()->committed)->toBe(0);

    tenancy()->end();
    $b = rezervasyonluVaryant('gorev-f.test');
    $sepetB = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepetB, $b, 2);
    app(StockService::class)->sepetiRezerveEt($sepetB);

    // B'nin rezervasyonu süresi dolmadığı için duruyor — A'daki koşum
    // buraya hiç dokunmadı.
    expect($b->refresh()->committed)->toBe(2)
        ->and(StockReservation::count())->toBe(1);
});
