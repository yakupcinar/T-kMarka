<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Settings\StorePublication;
use App\Enums\FulfillmentStatus;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;

/*
| ★ 1D'NİN BİTİŞ ÖLÇÜTÜ — uçtan uca, GERÇEK UÇLARDAN.
|
| Misafir müşteri: katalog → sepet → sipariş
| Marka personeli: siparişi gör → KISMİ sevk → durum doğru hesaplanıyor
|
| Servisleri doğrudan çağırmıyoruz; her adım HTTP ucundan geçiyor ki
| izinler, middleware'ler ve doğrulamalar da sınavın parçası olsun.
*/

it('★ UÇTAN UCA: misafir sipariş verir, personel kısmi sevk eder', function () {
    $marka = markaKur('uctan.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    // Marka kataloğu kurdu.
    $urunler = app(ProductService::class);
    $varyantlar = app(VariantService::class);

    $tisort = $urunler->olustur(['title' => 'Basic Tişört']);
    $vTisort = $varyantlar->ekle($tisort, ['sku' => 'TS-1', 'price' => 120, 'stock' => 10]);
    $urunler->durumDegistir($tisort->refresh(), ProductStatus::Active);

    $kupa = $urunler->olustur(['title' => 'Kupa']);
    $vKupa = $varyantlar->ekle($kupa, ['sku' => 'KP-1', 'price' => 60, 'stock' => 10]);
    $urunler->durumDegistir($kupa->refresh(), ProductStatus::Active);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    // ── 1. MİSAFİR KATALOĞU GEZİYOR (kimlik doğrulama yok) ────────────
    $liste = $this->getJson('http://uctan.test/api/products')->assertOk();
    expect($liste->json('products'))->toHaveCount(2);

    /*
    | ★ Bundan sonraki her kimlik UÇTAN geliyor — modelden DEĞİL.
    |
    | ⚠️ Bu ayrım 1D.6'da pahalıya mal oldu: test uca gidiyordu ama uca
    | verdiği uuid'yi modelden okuyordu. Vitrin o uuid'yi hiç döndürmüyordu,
    | yani gerçek müşteri sepete ekleyemiyordu ve test yeşildi.
    */
    $tisortUuid = $this->getJson('http://uctan.test/api/products/basic-tisort')
        ->assertOk()->json('product.variants.0.uuid');

    $kupaUuid = $this->getJson('http://uctan.test/api/products/kupa')
        ->assertOk()->json('product.variants.0.uuid');

    expect($tisortUuid)->toBe($vTisort->uuid)
        ->and($kupaUuid)->toBe($vKupa->uuid);

    // ── 2. SEPETE EKLİYOR ─────────────────────────────────────────────
    $sepetCevabi = $this->postJson('http://uctan.test/api/cart/items', [
        'variant_uuid' => $tisortUuid,
        'quantity' => 3,
    ])->assertStatus(201);

    $sepetToken = $sepetCevabi->json('cart_token');

    $this->withHeader('X-Cart-Token', $sepetToken)
        ->postJson('http://uctan.test/api/cart/items', [
            'variant_uuid' => $kupaUuid,
            'quantity' => 2,
        ])->assertStatus(201);

    // 120×3 + 60×2 = 480
    $sepet = $this->withHeader('X-Cart-Token', $sepetToken)
        ->getJson('http://uctan.test/api/cart')->assertOk();

    expect($sepet->json('subtotal'))->toBe('480.00')
        ->and($sepet->json('blockers'))->toBe([]);

    // ── 3. SÖZLEŞMEYİ OKUYOR ──────────────────────────────────────────
    // ⚠️ Sürüm kimliği de UÇTAN: `/checkout` bunu zorunlu istiyor ve
    // veren tek yer bu uç (1D.6'da eklendi).
    $metin = $this->getJson('http://uctan.test/api/legal/distance_sales')->assertOk();

    $surumId = $metin->json('document.version_id');
    expect($surumId)->toBe($sozlesme?->id);

    // ── 4. SİPARİŞ VERİYOR ────────────────────────────────────────────
    $siparisCevabi = $this->withHeader('X-Cart-Token', $sepetToken)
        ->postJson('http://uctan.test/api/checkout', [
            'email' => 'misafir@ornek.test',
            'legal_version_id' => $surumId,
            'shipping' => [
                'full_name' => 'Ayşe Yılmaz',
                'phone' => '+905321112233',
                'city' => 'İstanbul',
                'district' => 'Kadıköy',
                'line1' => 'Moda Cad. No:12',
            ],
        ])->assertStatus(201);

    $siparisNo = $siparisCevabi->json('order.order_number');

    /*
    | ⚠️ `tax_total` toplama EKLENMİYOR (§8.2): 480 tahsil ediliyor,
    | 80,00 vergi onun İÇİNDE. Eklenseydi müşteriden fazladan KDV
    | alınırdı — vergi dâhil modelde en sık yapılan hata.
    */
    expect($siparisNo)->toStartWith('TM-')
        ->and($siparisCevabi->json('order.items_total'))->toBe('480.00')
        ->and($siparisCevabi->json('order.tax_total'))->toBe('80.00')
        ->and($siparisCevabi->json('order.grand_total'))->toBe('480.00');

    // Stok BAĞLANDI ama henüz düşmedi.
    expect($vTisort->refresh()->stock)->toBe(10)
        ->and($vTisort->committed)->toBe(3);

    // ── 5. ÖDEME (1E'de gerçek sağlayıcı gelecek) ─────────────────────
    $siparis = Order::where('order_number', $siparisNo)->firstOrFail();
    app(CheckoutService::class)->odemeBasarili($siparis);

    // Şimdi gerçekten düştü.
    expect($vTisort->refresh()->stock)->toBe(7)
        ->and($vTisort->committed)->toBe(0);

    // ── 6. PERSONEL SİPARİŞİ GÖRÜYOR ──────────────────────────────────
    $token = panelTokeni('uctan.test', $marka['sahip']->email);

    guardOnbelleginiTemizle();
    $panelSiparis = $this->withToken($token)
        ->getJson("http://uctan.test/panel/orders/{$siparis->uuid}")->assertOk();

    expect($panelSiparis->json('order.fulfillment_status'))->toBe('unfulfilled')
        ->and($panelSiparis->json('order.contract_version'))->toBe($sozlesme?->version_no);

    /** @var list<array{id: int, sku: string}> $satirlar */
    $satirlar = $panelSiparis->json('order.items');

    // sku → order_item_id: aşağıdaki sevkiyat çağrıları satır kimliğiyle çalışıyor.
    $satirId = collect($satirlar)->pluck('id', 'sku');

    // ── 7. KISMİ SEVKİYAT ─────────────────────────────────────────────
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://uctan.test/panel/orders/{$siparis->uuid}/fulfillments", [
            'items' => [['order_item_id' => $satirId['TS-1'], 'quantity' => 2]],
            'carrier' => 'Yurtiçi',
            'tracking_number' => 'YK-1',
        ])
        ->assertStatus(201)
        ->assertJsonPath('fulfillment_status', 'partial');

    // ── 8. KALANI GÖNDER → fulfilled ──────────────────────────────────
    guardOnbelleginiTemizle();
    $ikinciPaket = $this->withToken($token)
        ->postJson("http://uctan.test/panel/orders/{$siparis->uuid}/fulfillments", [
            'items' => [
                ['order_item_id' => $satirId['TS-1'], 'quantity' => 1],
                ['order_item_id' => $satirId['KP-1'], 'quantity' => 2],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonPath('fulfillment_status', 'fulfilled');

    // ── 9. KARGOYA VER, TESLİM ET ─────────────────────────────────────
    $paketUuid = $ikinciPaket->json('fulfillment.uuid');

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://uctan.test/panel/orders/{$siparis->uuid}/fulfillments/{$paketUuid}/ship", [
            'carrier' => 'Aras', 'tracking_number' => 'AR-9',
        ])->assertOk()->assertJsonPath('fulfillment.status', 'shipped');

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://uctan.test/panel/orders/{$siparis->uuid}/fulfillments/{$paketUuid}/deliver")
        ->assertOk()->assertJsonPath('fulfillment.status', 'delivered');

    expect($siparis->refresh()->fulfillment_status)->toBe(FulfillmentStatus::Fulfilled);
});

it('★ AŞIRI SEVKİYAT uçtan da engelleniyor', function () {
    $marka = markaKur('uctan-b.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    $sepetCevabi = $this->postJson('http://uctan-b.test/api/cart/items', [
        'variant_uuid' => $varyant->uuid, 'quantity' => 2,
    ])->assertStatus(201);

    $siparisCevabi = $this->withHeader('X-Cart-Token', $sepetCevabi->json('cart_token'))
        ->postJson('http://uctan-b.test/api/checkout', [
            'email' => 'a@ornek.test',
            'legal_version_id' => $sozlesme?->id,
            'shipping' => [
                'full_name' => 'A', 'phone' => '+900000000000',
                'city' => 'İstanbul', 'district' => 'Kadıköy', 'line1' => 'X',
            ],
        ])->assertStatus(201);

    $siparis = Order::where('order_number', $siparisCevabi->json('order.order_number'))->firstOrFail();
    app(CheckoutService::class)->odemeBasarili($siparis);

    $token = panelTokeni('uctan-b.test', $marka['sahip']->email);
    $satirId = $siparis->items->firstOrFail()->id;

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://uctan-b.test/panel/orders/{$siparis->uuid}/fulfillments", [
            'items' => [['order_item_id' => $satirId, 'quantity' => 5]],
        ])
        ->assertStatus(422);
});

it('order.fulfill izni OLMAYAN personel sevk edemiyor', function () {
    markaKur('uctan-c.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ "Katalog" rolünde `product.write` var ama `order.view`/`order.fulfill`
    | YOK. Bu ayrım 1A.3'te yapılmıştı; ilk kez burada gerçek bir kapıyı
    | koruyor.
    */
    $personel = User::factory()->create(['email' => 'katalog@uctan-c.test', 'password' => 'sifre1234']);
    $personel->roles()->sync(Role::where('name', 'Katalog')->pluck('id'));

    $token = panelTokeni('uctan-c.test', $personel->email);

    guardOnbelleginiTemizle();
    $this->withToken($token)->getJson('http://uctan-c.test/panel/orders')->assertStatus(403);
});

it('mağaza kapalıyken sipariş verilemiyor', function () {
    markaKur('uctan-d.test');
    magazayiHazirla();

    // Mağaza hiç açılmadı.
    $this->postJson('http://uctan-d.test/api/checkout', [])->assertStatus(503);
});

it('iki markanın siparişleri uçlarda da karışmıyor', function () {
    $markaA = markaKur('uctan-e.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);
    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    $sepetCevabi = $this->postJson('http://uctan-e.test/api/cart/items', [
        'variant_uuid' => $varyant->uuid, 'quantity' => 1,
    ])->assertStatus(201);

    $this->withHeader('X-Cart-Token', $sepetCevabi->json('cart_token'))
        ->postJson('http://uctan-e.test/api/checkout', [
            'email' => 'a@ornek.test',
            'legal_version_id' => $sozlesme?->id,
            'shipping' => [
                'full_name' => 'A', 'phone' => '+900000000000',
                'city' => 'İstanbul', 'district' => 'Kadıköy', 'line1' => 'X',
            ],
        ])->assertStatus(201);

    tenancy()->end();
    $markaB = markaKur('uctan-f.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $tokenB = panelTokeni('uctan-f.test', $markaB['sahip']->email);

    guardOnbelleginiTemizle();
    $this->withToken($tokenB)
        ->getJson('http://uctan-f.test/panel/orders')
        ->assertOk()
        ->assertJsonCount(0, 'orders');

    expect(Order::count())->toBe(0)
        ->and(ProductVariant::count())->toBe(0);
});
