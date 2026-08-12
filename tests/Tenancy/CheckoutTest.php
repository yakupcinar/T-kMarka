<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CartNotOrderableException;
use App\Domain\Order\CheckoutService;
use App\Domain\Order\StaleContractException;
use App\Domain\Settings\SettingsService;
use App\Enums\CartStatus;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ReservationStatus;
use App\Enums\SettingGroup;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\StockReservation;

/*
| ÖDEME ADIMI — orkestratör (1D.3).
|
| İki şeyi birden kanıtlaması gerekiyor:
|   SİPARİŞ BİR FOTOĞRAFTIR — sonradan fiyat değişse bile sipariş değişmez
|   STOK BAĞLANIR           — ödeme başarısızsa geri verilir
*/

/**
 * Yayında mağaza + satılabilir varyant + yayınlanmış sözleşme.
 *
 * @return array{varyant: ProductVariant, sozlesmeId: int}
 */
function odemeyeHazirMagaza(string $alanAdi, int $stok = 10): array
{
    markaKur($alanAdi);
    magazayiHazirla();

    $urun = app(ProductService::class)->olustur(['title' => 'Basic Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, [
        'sku' => 'TS-1', 'price' => 120, 'stock' => $stok,
    ]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    return ['varyant' => $varyant, 'sozlesmeId' => (int) $sozlesme?->id];
}

it('sipariş oluşuyor: numara · pending · stok BAĞLANIYOR', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-a.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi($sozlesmeId));

    expect($siparis->order_number)->toStartWith('TM-'.now()->format('Y').'-')
        ->and($siparis->payment_status)->toBe(PaymentStatus::Pending)
        ->and($siparis->items)->toHaveCount(1);

    $varyant->refresh();

    // ⚠️ Stok HENÜZ düşmedi — yalnızca bağlandı. Ödeme başarısız olursa
    // geri verilecek.
    expect($varyant->stock)->toBe(10)
        ->and($varyant->committed)->toBe(2)
        ->and($sepet->refresh()->status)->toBe(CartStatus::Converted);
});

it('★ SİPARİŞ BİR FOTOĞRAF: sonradan fiyat değişse de satır DEĞİŞMİYOR', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-b.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi($sozlesmeId));
    $satir = $siparis->items->firstOrFail();

    // Marka fiyatı ve başlığı değiştirdi, KDV oranını da.
    $varyant->update(['price' => 999, 'sku' => 'YENI-SKU']);
    $varyant->product?->update(['title' => 'Yeni Başlık', 'tax_rate' => 10]);

    $satir->refresh();

    /*
    | ⚠️ Ürüne join'lenip fiyat oradan okunsaydı geçmiş siparişin tutarı
    | da değişirdi — müşterinin ödediği tutarla faturası tutmazdı.
    */
    expect($satir->unit_price)->toBe('120.00')
        ->and($satir->product_title)->toBe('Basic Tişört')
        ->and($satir->sku)->toBe('TS-1')
        ->and($satir->tax_rate)->toBe('20.00')

        // 120,00 ürün + 49,90 kargo. (Kargo ücreti 1E.1'de göründü: test
        // markası artık gerçek marka gibi varsayılan ayarlarla doğuyor.)
        ->and($siparis->refresh()->grand_total)->toBe('169.90');
});

it('★ VARYANT SİLİNSE BİLE satır yaşıyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-c.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi($sozlesmeId));

    // Marka varyantı sildi (yumuşak).
    $varyant->delete();

    $satir = $siparis->items()->firstOrFail();

    // Kopya alanlar siparişin ne olduğunu TEK BAŞINA anlatıyor.
    expect($satir->product_title)->toBe('Basic Tişört')
        ->and($satir->sku)->toBe('TS-1')
        ->and($satir->unit_price)->toBe('120.00');
});

it('★ ÖDEME BAŞARILI: stok gerçekten düşüyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-d.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 3);

    $servis = app(CheckoutService::class);
    $siparis = $servis->baslat($sepet, odemeVerisi($sozlesmeId));
    $servis->odemeBasarili($siparis);

    $varyant->refresh();

    // "Bağlanmış" değil artık, SATILMIŞ.
    expect($varyant->stock)->toBe(7)
        ->and($varyant->committed)->toBe(0)
        ->and($siparis->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(StockReservation::first()?->status)->toBe(ReservationStatus::Committed);
});

it('★ ÖDEME BAŞARISIZ: stok GERİ VERİLİYOR, sipariş silinmiyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-e.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 3);

    $servis = app(CheckoutService::class);
    $siparis = $servis->baslat($sepet, odemeVerisi($sozlesmeId));
    $servis->odemeBasarisiz($siparis);

    $varyant->refresh();

    expect($varyant->stock)->toBe(10)
        ->and($varyant->committed)->toBe(0)
        ->and($varyant->satilabilirAdet())->toBe(10)
        // ⚠️ Sipariş SİLİNMİYOR: "neden ödeme alınamadı" kayıtta kalmalı.
        ->and($siparis->refresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and(Order::count())->toBe(1);
});

it('★ STOK BAĞLIYSA sipariş HİÇ oluşmuyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-f.test', stok: 2);
    $sepetler = app(CartService::class);

    // Birinci müşteri hepsini bağladı.
    $birinci = $sepetler->misafirSepetiOlustur();
    $sepetler->ekle($birinci, $varyant, 2);
    app(CheckoutService::class)->baslat($birinci, odemeVerisi($sozlesmeId));

    // İkinci müşteri: sepette 2 var ama stok bağlı.
    $ikinci = $sepetler->misafirSepetiOlustur();
    $satir = $ikinci->items()->make(['quantity' => 2]);
    $satir->variant()->associate($varyant);
    $satir->save();

    /*
    | ⚠️ BEKLENTİMİ DÜZELTTİM — kod doğruydu, test yanlıştı.
    |
    | Önce `InsufficientStockException` bekliyordum; gelen
    | `CartNotOrderableException` oldu. Sebep: sepet doğrulaması stok
    | rezervasyonundan ÖNCE koşuyor ve varyant artık satılamaz olduğu için
    | (stok 2, hepsi bağlı) engel listesine düşüyor.
    |
    | Bu daha iyi: kullanıcı "sepetinizde şu ürün alınamıyor" görüyor,
    | ham bir stok hatası değil.
    |
    | `InsufficientStockException` yolu YARIŞ PENCERESİ için duruyor:
    | sepet doğrulaması ile rezervasyon arasında stok düşerse orası
    | devreye giriyor. O yol StockTest'te doğrudan sınanıyor.
    */
    expect(fn () => app(CheckoutService::class)->baslat($ikinci, odemeVerisi($sozlesmeId)))
        ->toThrow(CartNotOrderableException::class);

    // Sipariş oluşmadı ve sepet hâlâ aktif — transaction geri sarıldı.
    expect(Order::count())->toBe(1)
        ->and($ikinci->refresh()->status)->toBe(CartStatus::Active);
});

it('ölü satırlı sepet siparişe dönüşemiyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-g.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    // Marka ürünü arşivledi — sepetteki satır öldü (1C-K2).
    $varyant->product?->update(['status' => ProductStatus::Archived]);

    expect(fn () => app(CheckoutService::class)->baslat($sepet, odemeVerisi($sozlesmeId)))
        ->toThrow(CartNotOrderableException::class);

    expect(Order::count())->toBe(0)
        ->and($varyant->refresh()->committed)->toBe(0);
});

it('boş sepet siparişe dönüşemiyor', function () {
    ['sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-h.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();

    expect(fn () => app(CheckoutService::class)->baslat($sepet, odemeVerisi($sozlesmeId)))
        ->toThrow(CartNotOrderableException::class);
});

it('★ sözleşme: BAŞKA TÜRÜN sürümü kabul edilmiyor', function () {
    ['varyant' => $varyant] = odemeyeHazirMagaza('ode-i.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    // KVKK metninin sürümü gönderiliyor — sözleşme onayı atlanamamalı.
    $kvkk = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::Privacy);

    expect(fn () => app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $kvkk?->id)))
        ->toThrow(StaleContractException::class);
});

it('★ sipariş GÖSTERİLEN sözleşme sürümüne bağlanıyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $eskiSurum] = odemeyeHazirMagaza('ode-j.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    // Müşteri sözleşmeyi okurken marka YENİ sürüm yayınladı.
    $belgeler = app(LegalDocumentService::class);
    $belgeler->taslagaYaz(LegalDocumentType::DistanceSales, 'iade süresi 7 gün');
    $yeniSurum = $belgeler->yayinla(LegalDocumentType::DistanceSales);

    // Müşteri GÖRDÜĞÜ sürümü gönderiyor.
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi($eskiSurum));

    /*
    | ⚠️ "En son sürüm" yazılsaydı müşteri GÖRMEDİĞİ bir metne imza atmış
    | olurdu. Sipariş, onayladığı sürüme bağlı.
    */
    expect($siparis->legal_version_id)->toBe($eskiSurum)
        ->and($siparis->legal_version_id)->not->toBe($yeniSurum->id)
        ->and($siparis->terms_accepted_at)->not->toBeNull();
});

it('kargo eşiği uygulanıyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-k.test');
    app(SettingsService::class)->yaz(SettingGroup::Shipping, 'flat_fee', 49.90);
    app(SettingsService::class)->yaz(SettingGroup::Shipping, 'free_threshold', 500);

    $sepetler = app(CartService::class);

    // 120 × 2 = 240 → eşiğin altında, kargo var.
    $ucuz = $sepetler->misafirSepetiOlustur();
    $sepetler->ekle($ucuz, $varyant, 2);
    $siparisUcuz = app(CheckoutService::class)->baslat($ucuz, odemeVerisi($sozlesmeId));

    expect($siparisUcuz->shipping_total)->toBe('49.90')
        ->and($siparisUcuz->grand_total)->toBe('289.90');

    // 120 × 5 = 600 → eşiğin üstünde, kargo bedava.
    $pahali = $sepetler->misafirSepetiOlustur();
    $sepetler->ekle($pahali, $varyant, 5);
    $siparisPahali = app(CheckoutService::class)->baslat($pahali, odemeVerisi($sozlesmeId));

    expect($siparisPahali->shipping_total)->toBe('0.00')
        ->and($siparisPahali->grand_total)->toBe('600.00');
});

it('sipariş numaraları çakışmıyor', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-l.test', stok: 20);
    $sepetler = app(CartService::class);
    $numaralar = [];

    for ($i = 0; $i < 5; $i++) {
        $sepet = $sepetler->misafirSepetiOlustur();
        $sepetler->ekle($sepet, $varyant, 1);
        $numaralar[] = app(CheckoutService::class)->baslat($sepet, odemeVerisi($sozlesmeId))->order_number;
    }

    // Dizi (sequence) kullanılıyor; MAX+1 olsaydı eşzamanlılıkta çakışırdı.
    expect(array_unique($numaralar))->toHaveCount(5);
});

it('misafir siparişinde e-posta DOLU, customer_id boş', function () {
    ['varyant' => $varyant, 'sozlesmeId' => $sozlesmeId] = odemeyeHazirMagaza('ode-m.test');
    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi($sozlesmeId));

    // E-posta misafir siparişinin TEK iletişim kanalı.
    expect($siparis->misafirSiparisiMi())->toBeTrue()
        ->and($siparis->email)->toBe('ayse@ornek.test');
});

it('iki markanın siparişleri karışmıyor', function () {
    ['varyant' => $a, 'sozlesmeId' => $sozlesmeA] = odemeyeHazirMagaza('ode-n.test');
    $sepetA = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepetA, $a, 1);
    app(CheckoutService::class)->baslat($sepetA, odemeVerisi($sozlesmeA));

    tenancy()->end();
    odemeyeHazirMagaza('ode-o.test');

    expect(Order::count())->toBe(0)
        ->and(Cart::count())->toBe(0);
});
