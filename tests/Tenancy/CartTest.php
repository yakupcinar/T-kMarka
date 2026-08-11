<?php

use App\Domain\Cart\CartService;
use App\Domain\Cart\VariantNotPurchasableException;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Enums\CartStatus;
use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;

/*
| Sepet (1C-K1…K5).
|
| Bu bloğun en kritik kuralı BİRLEŞTİRME: adetler TOPLANMAZ, büyüğü alınır
| — ve birleştirmeden sonra stok kontrolü koşar. Magento topluyor ve bu
| kayıtlı bir hata kaynağı (#26981); WooCommerce bir ara birleştirmeyi
| tamamen kaldırmış.
*/

/**
 * Yayında mağaza + satılabilir bir varyant.
 */
function sepetliMagaza(string $alanAdi, int $stok = 10): ProductVariant
{
    markaKur($alanAdi);
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Basic Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, [
        'sku' => 'TS-1', 'price' => 100, 'stock' => $stok,
    ]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $varyant;
}

it('misafir sepeti açılıyor, token kriptografik uzunlukta', function () {
    sepetliMagaza('sepet-a.test');

    $cevap = $this->getJson('http://sepet-a.test/api/cart')->assertOk();

    // Tahmin edilebilir olsaydı biri başkasının sepetini okurdu.
    expect($cevap->json('cart_token'))->toBeString()->toHaveLength(64)
        ->and($cevap->json('items'))->toBe([])
        ->and($cevap->json('subtotal'))->toBe('0.00');
});

it('★ CHECK kısıtı: sahipsiz ve çift sahipli sepet açılamıyor', function () {
    sepetliMagaza('sepet-b.test');
    $musteri = Customer::factory()->create(['email' => 'a@sepet-b.test']);

    // İkisi de boş → kime ait olduğu bilinemez.
    expect(function () {
        $sepet = new Cart;
        $sepet->status = CartStatus::Active;
        $sepet->save();
    })->toThrow(QueryException::class);

    // İkisi de dolu → hangisi geçerli?
    expect(function () use ($musteri) {
        $sepet = new Cart;
        $sepet->customer()->associate($musteri);
        $sepet->session_token = str_repeat('x', 64);
        $sepet->status = CartStatus::Active;
        $sepet->save();
    })->toThrow(QueryException::class);
});

it('müşteri başına TEK aktif sepet', function () {
    sepetliMagaza('sepet-c.test');
    $musteri = Customer::factory()->create(['email' => 'a@sepet-c.test']);
    $servis = app(CartService::class);

    $bir = $servis->musteriSepeti($musteri);
    $iki = $servis->musteriSepeti($musteri);

    expect($iki->id)->toBe($bir->id)
        ->and(Cart::where('customer_id', $musteri->id)->count())->toBe(1);
});

it('satır ekleniyor, aynı varyant tekrar eklenince adet ARTIYOR', function () {
    $varyant = sepetliMagaza('sepet-d.test');
    $servis = app(CartService::class);
    $sepet = $servis->misafirSepetiOlustur();

    $servis->ekle($sepet, $varyant, 2);
    $servis->ekle($sepet, $varyant, 3);

    // İki ayrı satır değil, tek satır 5 adet (UNIQUE cart_id+variant_id).
    expect($sepet->items()->count())->toBe(1)
        ->and($sepet->items()->first()?->quantity)->toBe(5);
});

it('stok kontrolü YUMUŞAK — reddetmiyor, KIRPIYOR', function () {
    $varyant = sepetliMagaza('sepet-e.test', stok: 3);
    $servis = app(CartService::class);
    $sepet = $servis->misafirSepetiOlustur();

    /*
    | ⚠️ Sert reddetseydik müşteri 5 isterken 3 varsa hiçbir şey eklenmez
    | ve "neden olmadı" sorusuyla kalırdı. Kırpma alabileceğini veriyor.
    | Bağlayıcı kontrol ödeme adımında (engeller) ve 1D'nin rezervasyonunda.
    */
    $satir = $servis->ekle($sepet, $varyant, 5);

    expect($satir->quantity)->toBe(3);
});

it('satın alınamayan varyant sepete GİRMİYOR', function () {
    $varyant = sepetliMagaza('sepet-f.test');
    $varyant->update(['stock' => 0]);

    $servis = app(CartService::class);
    $sepet = $servis->misafirSepetiOlustur();

    expect(fn () => $servis->ekle($sepet, $varyant, 1))
        ->toThrow(VariantNotPurchasableException::class);
});

it('★ SEPETTEYKEN ölen satır SİLİNMİYOR, işaretleniyor', function () {
    $varyant = sepetliMagaza('sepet-g.test');
    $servis = app(CartService::class);
    $sepet = $servis->misafirSepetiOlustur();
    $servis->ekle($sepet, $varyant, 2);

    // Marka ürünü arşivledi.
    $varyant->product?->update(['status' => ProductStatus::Archived]);

    $cevap = $this->withHeader('X-Cart-Token', $sepet->session_token)
        ->getJson('http://sepet-g.test/api/cart')->assertOk();

    /*
    | ⚠️ Sessizce silinseydi kullanıcı ne kaybettiğini bilmezdi.
    | 1A.4'teki "sessiz yanlış yerine görünür eksik" kararının aynısı.
    */
    expect($cevap->json('items'))->toHaveCount(1)
        ->and($cevap->json('items.0.available'))->toBeFalse()
        // Ölü satır TOPLAMA girmiyor.
        ->and($cevap->json('subtotal'))->toBe('0.00')
        // Ve ödeme adımı kilitli.
        ->and($cevap->json('blockers'))->toHaveCount(1);
});

it('fiyat CANLI — marka fiyatı değiştirince sepette de değişiyor', function () {
    $varyant = sepetliMagaza('sepet-h.test');
    $servis = app(CartService::class);
    $sepet = $servis->misafirSepetiOlustur();
    $servis->ekle($sepet, $varyant, 2);

    $ilk = $this->withHeader('X-Cart-Token', $sepet->session_token)
        ->getJson('http://sepet-h.test/api/cart')->json('subtotal');

    $varyant->update(['price' => 150]);

    $sonra = $this->withHeader('X-Cart-Token', $sepet->session_token)
        ->getJson('http://sepet-h.test/api/cart')->json('subtotal');

    // Satırda fiyat saklansaydı müşteri vitrinde 150 görüp sepette 100
    // öderdi (ya da tersi).
    expect($ilk)->toBe('200.00')->and($sonra)->toBe('300.00');
});

it('★ BİRLEŞTİRME: adetler TOPLANMIYOR, BÜYÜĞÜ alınıyor', function () {
    $varyant = sepetliMagaza('sepet-i.test');
    $servis = app(CartService::class);
    $musteri = Customer::factory()->create(['email' => 'a@sepet-i.test']);

    // Cihaz 1: müşteri sepetinde 2 adet.
    $musteriSepeti = $servis->musteriSepeti($musteri);
    $servis->ekle($musteriSepeti, $varyant, 2);

    // Cihaz 2: misafir sepetinde 3 adet.
    $misafir = $servis->misafirSepetiOlustur();
    $servis->ekle($misafir, $varyant, 3);

    $birlesik = $servis->birlestir($misafir, $musteri);

    /*
    | ⚠️ Magento burada 5 yazıyor (topluyor) ve bu kayıtlı bir hata
    | kaynağı (#26981). "İki cihazda ekledim" diyen kullanıcının niyeti
    | 5 almak değil.
    */
    expect($birlesik->items)->toHaveCount(1)
        ->and($birlesik->items->first()?->quantity)->toBe(3)
        ->and(Cart::count())->toBe(1);   // misafir sepeti tüketildi
});

it('★ birleştirmeden SONRA stok kontrolü koşuyor', function () {
    $varyant = sepetliMagaza('sepet-j.test', stok: 4);
    $servis = app(CartService::class);
    $musteri = Customer::factory()->create(['email' => 'a@sepet-j.test']);

    $musteriSepeti = $servis->musteriSepeti($musteri);
    $servis->ekle($musteriSepeti, $varyant, 4);

    $misafir = $servis->misafirSepetiOlustur();
    $servis->ekle($misafir, $varyant, 3);

    // Stok bu arada düştü.
    $varyant->update(['stock' => 2]);

    $birlesik = $servis->birlestir($misafir, $musteri);

    // ⚠️ Magento'nun atladığı adım: birleştirme sonrası stok denetimi.
    expect($birlesik->items->first()?->quantity)->toBe(2);
});

it('birleştirmede FARKLI varyantlar taşınıyor', function () {
    $varyantA = sepetliMagaza('sepet-k.test');
    $urunB = app(ProductService::class)->olustur(['title' => 'İkinci Ürün']);
    $varyantB = app(VariantService::class)->ekle($urunB, ['sku' => 'IK-1', 'price' => 50, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urunB->refresh(), ProductStatus::Active);

    $servis = app(CartService::class);
    $musteri = Customer::factory()->create(['email' => 'a@sepet-k.test']);

    $servis->ekle($servis->musteriSepeti($musteri), $varyantA, 1);

    $misafir = $servis->misafirSepetiOlustur();
    $servis->ekle($misafir, $varyantB, 2);

    $birlesik = $servis->birlestir($misafir, $musteri);

    expect($birlesik->items)->toHaveCount(2);
});

it('★ GİRİŞ ANINDA birleştirme kendiliğinden oluyor', function () {
    $varyant = sepetliMagaza('sepet-l.test');
    $servis = app(CartService::class);

    Customer::factory()->create(['email' => 'ayse@sepet-l.test', 'password' => 'sifre1234']);

    $misafir = $servis->misafirSepetiOlustur();
    $servis->ekle($misafir, $varyant, 2);

    guardOnbelleginiTemizle();
    $cevap = $this->withHeader('X-Cart-Token', $misafir->session_token)
        ->postJson('http://sepet-l.test/api/login', [
            'email' => 'ayse@sepet-l.test',
            'password' => 'sifre1234',
        ])->assertOk();

    // İstemci artık misafir token'ını atması gerektiğini biliyor.
    expect($cevap->json('cart_merged'))->toBeTrue();

    guardOnbelleginiTemizle();
    $sepet = $this->withToken($cevap->json('token'))
        ->getJson('http://sepet-l.test/api/cart')->assertOk();

    expect($sepet->json('items'))->toHaveCount(1)
        ->and($sepet->json('items.0.quantity'))->toBe(2);
});

it('başkasının sepetine token olmadan erişilemiyor', function () {
    $varyant = sepetliMagaza('sepet-m.test');
    $servis = app(CartService::class);
    $baskasi = $servis->misafirSepetiOlustur();
    $servis->ekle($baskasi, $varyant, 2);

    // Token yoksa YENİ boş sepet açılıyor, başkasınınki gelmiyor.
    $cevap = $this->getJson('http://sepet-m.test/api/cart')->assertOk();

    expect($cevap->json('items'))->toBe([])
        ->and($cevap->json('cart_token'))->not->toBe($baskasi->session_token);
});

it('mağaza kapalıyken sepet uçları da 503', function () {
    sepetliMagaza('sepet-n.test');

    app(StorePublication::class)->kapat();

    $this->getJson('http://sepet-n.test/api/cart')->assertStatus(503);
});

it('adet 0 yapılınca satır siliniyor', function () {
    $varyant = sepetliMagaza('sepet-o.test');
    $servis = app(CartService::class);
    $sepet = $servis->misafirSepetiOlustur();
    $servis->ekle($sepet, $varyant, 2);

    $this->withHeader('X-Cart-Token', $sepet->session_token)
        ->putJson("http://sepet-o.test/api/cart/items/{$varyant->uuid}", ['quantity' => 0])
        ->assertOk();

    // `quantity > 0` CHECK kısıtı var; 0 bir satır değil, satırın yokluğu.
    expect($sepet->items()->count())->toBe(0);
});
