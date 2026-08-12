<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;

/*
| ★ 1E'NİN BİTİŞ ÖLÇÜTÜ — uçtan uca, GERÇEK UÇLARDAN, ÖDEMELİ.
|
| katalog → sepet → sözleşme → sipariş → ÖDEME BAŞLAT → dönüş ekranı
|         → WEBHOOK → stok düşer → panel görür → sevk edilir
|
| ⚠️ 1D.6'nın kuralı burada da geçerli: isteğe giren her kimlik bir
| önceki UÇTAN geliyor. Modelden okunan tek şey yok.
*/

it('★ UÇTAN UCA: misafir öder, stok düşer, personel sevk eder', function () {
    $marka = markaKur('ode2e.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Basic Tişört']);
    app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 120, 'stock' => 10]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    // ── 1. KATALOG ────────────────────────────────────────────────────
    $varyantUuid = $this->getJson('http://ode2e.test/api/products/basic-tisort')
        ->assertOk()->json('product.variants.0.uuid');

    // ── 2. SEPET ──────────────────────────────────────────────────────
    $sepetToken = $this->postJson('http://ode2e.test/api/cart/items', [
        'variant_uuid' => $varyantUuid,
        'quantity' => 2,
    ])->assertStatus(201)->json('cart_token');

    // ── 3. SÖZLEŞME ───────────────────────────────────────────────────
    $surumId = $this->getJson('http://ode2e.test/api/legal/distance_sales')
        ->assertOk()->json('document.version_id');

    // ── 4. SİPARİŞ ────────────────────────────────────────────────────
    $siparisCevabi = $this->withHeader('X-Cart-Token', $sepetToken)
        ->postJson('http://ode2e.test/api/checkout', [
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

    $siparisUuid = $siparisCevabi->json('order.uuid');
    $siparisNo = $siparisCevabi->json('order.order_number');
    $tutar = $siparisCevabi->json('order.grand_total');

    // 120×2 + 49,90 kargo
    expect($tutar)->toBe('289.90');

    // ── 5. ÖDEME BAŞLAT ───────────────────────────────────────────────
    $odeme = $this->postJson("http://ode2e.test/api/orders/{$siparisUuid}/pay")->assertOk();

    $adres = $odeme->json('redirect_url');
    $referans = $odeme->json('reference');

    expect($adres)->toContain('ode2e.test/odeme/donus');

    // ── 6. MÜŞTERİ BANKADAN DÖNDÜ — ama webhook henüz gelmedi ─────────
    $this->getJson("http://ode2e.test/odeme/donus?ref={$referans}&status=success")
        ->assertOk()
        ->assertJsonPath('state', 'processing');

    /*
    | ⚠️ Müşteri adres çubuğunda "success" yazsa bile hiçbir şey olmuyor:
    | stok bağlı ama düşmemiş, sipariş hâlâ pending (1E-K1).
    */
    $varyant = ProductVariant::where('sku', 'TS-1')->firstOrFail();

    expect($varyant->stock)->toBe(10)
        ->and($varyant->committed)->toBe(2);

    // ── 7. WEBHOOK — GERÇEK burada ────────────────────────────────────
    bildirimGonder('ode2e.test', $siparisNo, $referans, $tutar)
        ->assertOk()
        ->assertJsonPath('result', 'paid');

    expect($varyant->refresh()->stock)->toBe(8)
        ->and($varyant->committed)->toBe(0);

    // Tekrar teslim: stok bir kez daha düşmüyor.
    bildirimGonder('ode2e.test', $siparisNo, $referans, $tutar)
        ->assertJsonPath('result', 'already_processed');

    expect($varyant->refresh()->stock)->toBe(8)
        ->and(Payment::count())->toBe(1);

    // ── 8. DÖNÜŞ EKRANI artık başarılı ────────────────────────────────
    $this->getJson("http://ode2e.test/odeme/donus?ref={$referans}")
        ->assertOk()
        ->assertJsonPath('state', 'success');

    // ── 9. PERSONEL SİPARİŞİ GÖRÜYOR ──────────────────────────────────
    $token = panelTokeni('ode2e.test', $marka['sahip']->email);

    guardOnbelleginiTemizle();
    $panel = $this->withToken($token)
        ->getJson("http://ode2e.test/panel/orders/{$siparisUuid}")->assertOk();

    expect($panel->json('order.payment_status'))->toBe('paid')
        ->and($panel->json('order.stock_shortfall'))->toBeFalse()
        ->and($panel->json('order.fulfillment_status'))->toBe('unfulfilled');

    /** @var list<array{id: int, sku: string}> $satirlar */
    $satirlar = $panel->json('order.items');
    $satirId = collect($satirlar)->pluck('id', 'sku');

    // ── 10. SEVKİYAT ──────────────────────────────────────────────────
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://ode2e.test/panel/orders/{$siparisUuid}/fulfillments", [
            'items' => [['order_item_id' => $satirId['TS-1'], 'quantity' => 2]],
            'carrier' => 'Yurtiçi',
            'tracking_number' => 'YK-1',
        ])
        ->assertStatus(201)
        ->assertJsonPath('fulfillment_status', 'fulfilled');
});

it('★ ÖDENMEMİŞ sipariş sevk EDİLEMİYOR — uçtan da', function () {
    // ⚠️ `odemeAsamasiSiparisi` markayı KENDİSİ kuruyor; ayrıca
    // `markaKur` çağrılırsa alan adı ikinci kez kaydedilmeye çalışılır.
    ['siparis' => $siparis] = odemeAsamasiSiparisi('ode2e-b.test');

    app(StorePublication::class)->yayinla();

    $sahip = User::where('is_owner', true)->firstOrFail();
    $token = panelTokeni('ode2e-b.test', $sahip->email);
    $satir = $siparis->items->firstOrFail();

    /*
    | ⚠️ Ödeme ile sevkiyat İKİ AYRI EKSEN ama bağımsız değil: ödenmemiş
    | sipariş kargoya verilemez. Kural 1D.4'te yazıldı, burada uçtan
    | doğrulanıyor — panelde bir düğme onu atlayamıyor.
    */
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://ode2e-b.test/panel/orders/{$siparis->uuid}/fulfillments", [
            'items' => [['order_item_id' => $satir->id, 'quantity' => 1]],
        ])
        ->assertStatus(409)
        ->assertJsonPath('payment_status', 'pending');
});
