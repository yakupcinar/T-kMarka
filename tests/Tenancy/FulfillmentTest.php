<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Order\FulfillmentService;
use App\Domain\Order\OrderNotShippableException;
use App\Domain\Order\OverShipmentException;
use App\Enums\FulfillmentStatus;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Enums\ShipmentStatus;
use App\Models\Fulfillment;
use App\Models\FulfillmentItem;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

/*
| Kısmi sevkiyat (1D.4).
|
| ★ TEK doğrulama kuralı: bir satırın toplam sevk edilen adedi sipariş
| adedini GEÇEMEZ. Engellenmeseydi marka aynı ürünü iki kez gönderir,
| stok gerçekle uyuşmaz ve iade hesabı tutmazdı — hiçbiri hata vermeden.
|
| Bu blok, "ortadaki katmanı silme dürtüsüne direniyoruz" kararının
| karşılığını verdiği yer (§7).
*/

it('ödenmemiş sipariş SEVK EDİLEMİYOR', function () {
    markaKur('sevk-a.test');
    magazayiHazirla();

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    $sozlesme = app(LegalDocumentService::class)
        ->guncelSurum(LegalDocumentType::DistanceSales);

    // ⚠️ Ödeme YAPILMADI — sipariş pending.
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    $satir = $siparis->items->firstOrFail();

    expect(fn () => app(FulfillmentService::class)->olustur($siparis, [$satir->id => 1]))
        ->toThrow(OrderNotShippableException::class);
});

it('★ KISMİ SEVKİYAT: durum unfulfilled → partial → fulfilled', function () {
    $siparis = sevkiyatlikSiparis('sevk-b.test');
    $servis = app(FulfillmentService::class);
    [$tisort, $kupa] = $siparis->items->all();

    expect($siparis->fulfillment_status)->toBe(FulfillmentStatus::Unfulfilled);

    // 1. paket: tişörtün 2 tanesi (3'ten).
    $servis->olustur($siparis, [$tisort->id => 2], 'Yurtiçi', 'YK123');

    expect($siparis->refresh()->fulfillment_status)->toBe(FulfillmentStatus::Partial);

    // 2. paket: kalan tişört + kupalar.
    $servis->olustur($siparis, [$tisort->id => 1, $kupa->id => 2], 'Yurtiçi', 'YK456');

    /*
    | ⚠️ `fulfillment_status` TÜRETİLİYOR. Elle yazılan bir alan olsaydı
    | marka üçüncü paketi gönderir, alan hâlâ "partial" gösterir ve kimse
    | fark etmezdi.
    */
    expect($siparis->refresh()->fulfillment_status)->toBe(FulfillmentStatus::Fulfilled);
});

it('★ SİPARİŞ ADEDİNDEN FAZLA sevk edilemiyor', function () {
    $siparis = sevkiyatlikSiparis('sevk-c.test');
    $servis = app(FulfillmentService::class);
    $tisort = $siparis->items->firstOrFail();

    // Tek seferde fazla.
    expect(fn () => $servis->olustur($siparis, [$tisort->id => 4]))
        ->toThrow(OverShipmentException::class);

    // Parça parça da olsa toplam aşamaz.
    $servis->olustur($siparis, [$tisort->id => 2]);

    expect(fn () => $servis->olustur($siparis, [$tisort->id => 2]))
        ->toThrow(OverShipmentException::class);

    expect($servis->sevkEdilenAdet($tisort))->toBe(2);
});

it('★ İPTAL EDİLEN paket sevk edilmiş SAYILMIYOR', function () {
    $siparis = sevkiyatlikSiparis('sevk-d.test');
    $servis = app(FulfillmentService::class);
    $tisort = $siparis->items->firstOrFail();

    $paket = $servis->olustur($siparis, [$tisort->id => 3]);

    expect($siparis->refresh()->fulfillment_status)->toBe(FulfillmentStatus::Partial)
        ->and($servis->sevkEdilenAdet($tisort))->toBe(3);

    $servis->iptal($paket);

    /*
    | ⚠️ İptal edilen paketin adetleri geri geliyor: o satırlar yeniden
    | sevk edilebilir olmalı. Sayılsaydı marka iptal ettiği paketi bir daha
    | gönderemezdi.
    */
    expect($servis->sevkEdilenAdet($tisort))->toBe(0)
        ->and($siparis->refresh()->fulfillment_status)->toBe(FulfillmentStatus::Unfulfilled);

    // Ve yeniden sevk edilebiliyor.
    $servis->olustur($siparis, [$tisort->id => 3]);
    expect($servis->sevkEdilenAdet($tisort))->toBe(3);
});

it('iptal edilen paket SİLİNMİYOR — denetim izi', function () {
    $siparis = sevkiyatlikSiparis('sevk-e.test');
    $servis = app(FulfillmentService::class);
    $tisort = $siparis->items->firstOrFail();

    $paket = $servis->olustur($siparis, [$tisort->id => 1]);
    $servis->iptal($paket);

    expect($paket->refresh()->status)->toBe(ShipmentStatus::Cancelled)
        ->and($paket->items()->count())->toBe(1)
        ->and($siparis->fulfillments()->count())->toBe(1);
});

it('kargo ve teslim damgaları yazılıyor', function () {
    $siparis = sevkiyatlikSiparis('sevk-f.test');
    $servis = app(FulfillmentService::class);
    $tisort = $siparis->items->firstOrFail();

    $paket = $servis->olustur($siparis, [$tisort->id => 1]);

    expect($paket->status)->toBe(ShipmentStatus::Pending)
        ->and($paket->shipped_at)->toBeNull();

    $servis->kargoyaVer($paket, 'Aras', 'AR789');

    expect($paket->refresh()->status)->toBe(ShipmentStatus::Shipped)
        ->and($paket->carrier)->toBe('Aras')
        ->and($paket->tracking_number)->toBe('AR789')
        ->and($paket->shipped_at)->not->toBeNull();

    $servis->teslimEdildi($paket);

    expect($paket->refresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($paket->delivered_at)->not->toBeNull();
});

it('★ BAŞKA SİPARİŞİN satırı pakete konamıyor', function () {
    $siparisA = sevkiyatlikSiparis('sevk-g.test');
    $siparisB = Order::where('id', '!=', $siparisA->id)->first();

    // İkinci bir sipariş üret.
    $sepetler = app(CartService::class);
    $varyant = ProductVariant::where('sku', 'TS-1')->firstOrFail();
    $sepet = $sepetler->misafirSepetiOlustur();
    $sepetler->ekle($sepet, $varyant, 1);

    $sozlesme = app(LegalDocumentService::class)
        ->guncelSurum(LegalDocumentType::DistanceSales);

    $odeme = app(CheckoutService::class);
    $siparisB = $odeme->odemeBasarili($odeme->baslat($sepet, odemeVerisi((int) $sozlesme?->id)));

    $baskasininSatiri = $siparisB->items->firstOrFail();

    /*
    | 1A.5 deseni: sorgu siparişe daraltılı, yabancı satır sonuç kümesine
    | hiç girmiyor. Düz `OrderItem::find()` kullanılsaydı A'nın paketine
    | B'nin satırı konabilirdi.
    */
    expect(fn () => app(FulfillmentService::class)->olustur($siparisA, [$baskasininSatiri->id => 1]))
        ->toThrow(ModelNotFoundException::class);
});

it('boş paket oluşturulamıyor', function () {
    $siparis = sevkiyatlikSiparis('sevk-h.test');

    expect(fn () => app(FulfillmentService::class)->olustur($siparis, []))
        ->toThrow(OverShipmentException::class);

    expect(fn () => app(FulfillmentService::class)->olustur($siparis, [1 => 0]))
        ->toThrow(OverShipmentException::class);
});

it('aynı satır aynı pakette iki kez olamıyor', function () {
    $siparis = sevkiyatlikSiparis('sevk-i.test');
    $servis = app(FulfillmentService::class);
    $tisort = $siparis->items->firstOrFail();

    $paket = $servis->olustur($siparis, [$tisort->id => 1]);

    // Veritabanı kısıtı: UNIQUE(fulfillment_id, order_item_id).
    // Olsaydı "3 + 2 mi, hangisi geçerli" sorusu doğardı.
    expect(function () use ($paket, $tisort) {
        $kalem = new FulfillmentItem;
        $kalem->fulfillment()->associate($paket);
        $kalem->orderItem()->associate($tisort);
        $kalem->quantity = 1;
        $kalem->save();
    })->toThrow(QueryException::class);
});

it('iki markanın sevkiyatları karışmıyor', function () {
    sevkiyatlikSiparis('sevk-j.test');

    tenancy()->end();
    sevkiyatlikSiparis('sevk-k.test');

    expect(Fulfillment::count())->toBe(0);
});
