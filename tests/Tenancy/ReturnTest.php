<?php

use App\Domain\Order\FulfillmentService;
use App\Domain\Returns\OverReturnException;
use App\Domain\Returns\RefundService;
use App\Domain\Returns\RefundTotals;
use App\Domain\Returns\ReturnNotRefundableException;
use App\Domain\Returns\ReturnService;
use App\Domain\Returns\ReturnWindowClosedException;
use App\Domain\Returns\WithdrawalWindow;
use App\Domain\Settings\StorePublication;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;

/*
| İade ve cayma hakkı (2B) — Faz 2'nin en zor bloğu.
|
| ★ DÖRT İDDİA:
|   1  iade talebi ≠ para iadesi — ürün gelmeden para gitmiyor
|   2  14 gün TESLİM tarihinden, paket paket
|   3  vergi yeniden hesaplanmıyor, satırınki dönüyor
|   4  tam caymada kargo da geri (yasal zorunluluk)
*/

it('★ CAYMA SÜRESİ TESLİM tarihinden başlıyor', function () {
    $siparis = iadeyeHazirSiparis('iade-a.test');
    $satir = $siparis->items->firstOrFail();

    $pencere = app(WithdrawalWindow::class);

    expect($pencere->teslimTarihi($satir))->not->toBeNull()
        ->and($pencere->acikMi($satir))->toBeTrue();

    /*
    | ⚠️ Mevzuat: süre malın TESLİM ALINDIĞI gün başlıyor; taşıyıcıya
    | teslim BAŞLATMIYOR. Sipariş tarihinden sayılsaydı kargoda geçen
    | her gün müşterinin hakkından yenirdi.
    */
    $this->travel(WithdrawalWindow::GUN + 1)->days();

    expect($pencere->acikMi($satir->refresh()))->toBeFalse();
});

it('★ GEÇ TESLİMATTA hak KISALMIYOR — sipariş tarihi değil TESLİM tarihi', function () {
    $siparis = sevkiyatlikSiparis('iade-q.test');
    $satir = $siparis->items->firstOrFail();

    /*
    | ★ ASIL AYRIMI ÖLÇEN TEST — ve ilk yazdığım sürüm bunu ölçmüyordu.
    |
    | Kırmızı kontrolde süreyi `placed_at`'ten saydırdım ve testler YEŞİL
    | kaldı: her ikisinde de sipariş "az önce" verilmişti, fark
    | görünmüyordu. Ayrımın göründüğü tek senaryo GEÇ TESLİMAT.
    |
    | Sipariş 20 gün önce verildi, ürün DÜN teslim edildi:
    |   sipariş tarihinden sayılsaydı  →  hak dolmuş (yanlış)
    |   teslim tarihinden sayılınca    →  hak açık   (doğru)
    |
    | Mevzuat: süre malın teslim alındığı gün başlar; taşıyıcıya teslim
    | süreyi başlatmaz.
    */
    $this->travel(20)->days();

    $servis = app(FulfillmentService::class);
    $paket = $servis->olustur($siparis, [$satir->id => 1]);
    $servis->kargoyaVer($paket);
    $servis->teslimEdildi($paket->refresh());

    $pencere = app(WithdrawalWindow::class);

    expect($pencere->acikMi($satir->refresh()))->toBeTrue()
        ->and($siparis->placed_at?->diffInDays(now()))->toBeGreaterThan(WithdrawalWindow::GUN);
});

it('★ TESLİM EDİLMEMİŞ satırda süre HENÜZ BAŞLAMADI — hak açık', function () {
    $siparis = sevkiyatlikSiparis('iade-b.test');
    $satir = $siparis->items->firstOrFail();

    /*
    | ⚠️ "Teslim tarihi yok, demek ki hak yok" denseydi kargoda olan ürün
    | için cayma imkânsız olurdu.
    */
    expect(app(WithdrawalWindow::class)->teslimTarihi($satir))->toBeNull()
        ->and(app(WithdrawalWindow::class)->acikMi($satir))->toBeTrue();
});

it('★ SÜRESİ DOLMUŞ satır iade edilemiyor — ama KUSURLU ürün edilebiliyor', function () {
    $siparis = iadeyeHazirSiparis('iade-c.test');
    $satir = $siparis->items->firstOrFail();

    $this->travel(WithdrawalWindow::GUN + 1)->days();

    expect(fn () => app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]))
        ->toThrow(ReturnWindowClosedException::class);

    /*
    | ⚠️ Kusurlu ürün iadesi CAYMA DEĞİL: 14 günle sınırlı değil.
    | Ayrılmasaydı kusurlu ürün 15. günde reddedilirdi.
    */
    $talep = app(ReturnService::class)->talepAc($siparis, [$satir->id => 1], cayma: false, sebep: 'Ürün kusurlu');

    expect($talep->status)->toBe(ReturnStatus::Requested)
        ->and($talep->is_withdrawal)->toBeFalse();
});

it('★ SİPARİŞ EDİLENDEN FAZLA iade edilemiyor', function () {
    $siparis = iadeyeHazirSiparis('iade-d.test');
    $satir = $siparis->items->firstOrFail();   // 3 adet

    app(ReturnService::class)->talepAc($siparis, [$satir->id => 2]);

    /*
    | ⚠️ `OverShipmentException`'ın (1D.4) aynası. Engellenmeseydi müşteri
    | aynı satırı iki talepte iade eder ve ürün bedelinin iki katını geri
    | alırdı — hatasız.
    */
    expect(fn () => app(ReturnService::class)->talepAc($siparis, [$satir->id => 2]))
        ->toThrow(OverReturnException::class);
});

it('REDDEDİLEN talep sayılmıyor — satır yeniden iade edilebiliyor', function () {
    $siparis = iadeyeHazirSiparis('iade-e.test');
    $satir = $siparis->items->firstOrFail();

    $talep = app(ReturnService::class)->talepAc($siparis, [$satir->id => 3]);
    app(ReturnService::class)->reddet($talep, 'Ürün kullanılmış');

    // ⚠️ Sevkiyattaki "iptal edilen paket sayılmaz" kuralının aynısı.
    $yeni = app(ReturnService::class)->talepAc($siparis, [$satir->id => 3]);

    expect($yeni->status)->toBe(ReturnStatus::Requested);
});

it('★ ÜRÜN ELE GEÇMEDEN PARA GİTMİYOR', function () {
    $siparis = iadeyeHazirSiparis('iade-f.test');
    $satir = $siparis->items->firstOrFail();

    $talep = app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]);

    /*
    | ★ BLOĞUN EN ÖNEMLİ KORUMASI (2B-K1).
    |
    | ⚠️ Olmasaydı: müşteri talep açar, marka onaylar, para gider — ürün
    | hiç gelmez. Ve bu bir hata olarak görünmez.
    */
    expect(fn () => app(RefundService::class)->iadeEt($talep))
        ->toThrow(ReturnNotRefundableException::class);

    app(ReturnService::class)->onayla($talep);

    // Onay da yetmiyor — ürün hâlâ yolda.
    expect(fn () => app(RefundService::class)->iadeEt($talep->refresh()))
        ->toThrow(ReturnNotRefundableException::class);

    app(ReturnService::class)->teslimAlindi($talep->refresh());

    $iade = app(RefundService::class)->iadeEt($talep->refresh());

    expect($iade->status)->toBe(RefundStatus::Completed);
});

it('★ STOK OTOMATİK GERİ GİRMİYOR — ayrı karar', function () {
    $siparis = iadeyeHazirSiparis('iade-g.test');
    $satir = $siparis->items->firstOrFail();
    $varyant = ProductVariant::findOrFail($satir->variant_id);

    $stokOnce = $varyant->stock;

    $talep = app(ReturnService::class)->talepAc($siparis, [$satir->id => 2]);
    app(ReturnService::class)->onayla($talep);

    /*
    | ⚠️ 2B-K6. Otomatik olsaydı hasarlı gelen ürün satışa açılır, bir
    | sonraki müşteriye o gönderilirdi. Magento'da da ayrı bir onay kutusu.
    */
    app(ReturnService::class)->teslimAlindi($talep->refresh(), stogaGeriKoy: false);
    expect($varyant->refresh()->stock)->toBe($stokOnce);

    // İkinci talep, bu sefer stoğa geri koyarak.
    $talep2 = app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]);
    app(ReturnService::class)->teslimAlindi($talep2, stogaGeriKoy: true);

    expect($varyant->refresh()->stock)->toBe($stokOnce + 1);
});

it('★ VERGİ satırın kendi vergisinden, ORANSAL dönüyor', function () {
    $siparis = iadeyeHazirSiparis('iade-h.test');
    $satir = $siparis->items->firstOrFail();   // 3 adet × 100 TL

    $talep = app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]);

    $tutarlar = app(RefundTotals::class)->hesapla($talep);

    /*
    | ⚠️ Vergi YENİDEN HESAPLANMIYOR: satırın donmuş KDV'sinin 1/3'ü
    | dönüyor. Yeniden hesaplansaydı KDV oranı yarın değiştiğinde eski
    | siparişin iadesi yeni oranla hesaplanır ve tutar tutmazdı.
    */
    /*
    | ⚠️ Beklenen değer YARIM YUKARI yuvarlanıyor — `bcdiv` keserdi.
    | 3×100 TL, %20 dâhil → satır vergisi 50,00; üçte biri 16,666… →
    | 16,67. Test `bcdiv` ile yazılsaydı 16,66 bekler ve kodu haksız yere
    | kırmızıya düşürürdü (1D.3'teki bir kuruş dersinin aynısı).
    */
    $beklenenVergi = bcadd(bcdiv((string) $satir->tax_amount, '3', 6), '0.005', 2);

    expect($tutarlar['items'])->toBe('100.00')
        ->and(bccomp($tutarlar['tax'], $beklenenVergi, 2))->toBe(0);
});

it('★ TAM CAYMADA KARGO DA GERİ — kısmi iadede değil', function () {
    $siparis = iadeyeHazirSiparis('iade-i.test');
    [$tisort, $kupa] = $siparis->items->all();

    // Kısmi: yalnızca bir satır.
    $kismi = app(ReturnService::class)->talepAc($siparis, [$tisort->id => 1]);

    expect(app(RefundTotals::class)->hesapla($kismi)['shipping'])->toBe('0.00');

    app(ReturnService::class)->reddet($kismi);

    // Tam: siparişin bütün adetleri.
    $tam = app(ReturnService::class)->talepAc($siparis, [
        $tisort->id => $tisort->quantity,
        $kupa->id => $kupa->quantity,
    ]);

    /*
    | ⚠️ YASAL ZORUNLULUK, tasarım tercihi değil: satıcı "teslim
    | masrafları dâhil tahsil edilen tüm ödemeleri" iade etmekle yükümlü.
    | İlk tasarımda "kısmi iadede kargo geri verilmez, tam iptalde
    | verilir" denmişti; araştırma bunu düzeltti (2B-K5).
    */
    expect(app(RefundTotals::class)->hesapla($tam)['shipping'])
        ->toBe((string) $siparis->shipping_total);
});

it('★ AYNI TALEP İKİ KEZ para iadesi açmıyor', function () {
    $siparis = iadeyeHazirSiparis('iade-j.test');
    $satir = $siparis->items->firstOrFail();

    $talep = app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]);
    app(ReturnService::class)->teslimAlindi($talep);

    app(RefundService::class)->iadeEt($talep->refresh());

    /*
    | ⚠️ 2B-K7 — İKİ KATMANLI koruma:
    |
    |   1  DURUM: iade tamamlanınca talep `completed` oluyor, ikinci
    |      çağrı buradan dönüyor (sıralı istekler)
    |   2  UNIQUE (order_id, idempotency_key): iki istek AYNI ANDA
    |      gelirse ikincisi veritabanında çarpıyor (yarış)
    |
    | İki kez iade, iki kez tahsilattan beter: müşteriye fazladan para
    | gider ve geri istemek gerekir.
    */
    expect(fn () => app(RefundService::class)->iadeEt($talep->refresh()))
        ->toThrow(ReturnNotRefundableException::class);

    expect(Refund::count())->toBe(1);
});

it('★ SİPARİŞ DURUMU türetiliyor: kısmi → tam iade', function () {
    $siparis = iadeyeHazirSiparis('iade-k.test');
    [$tisort, $kupa] = $siparis->items->all();

    $kismi = app(ReturnService::class)->talepAc($siparis, [$tisort->id => 1]);
    app(ReturnService::class)->teslimAlindi($kismi);
    app(RefundService::class)->iadeEt($kismi->refresh());

    /*
    | ⚠️ 1D.4'teki `fulfillment_status` dersinin aynısı: elle yazılan alan
    | üçüncü kısmi iadeden sonra gerçekle uyuşmazdı.
    */
    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded);

    $kalan = app(ReturnService::class)->talepAc($siparis, [
        $tisort->id => $tisort->quantity - 1,
        $kupa->id => $kupa->quantity,
    ]);
    app(ReturnService::class)->teslimAlindi($kalan);
    app(RefundService::class)->iadeEt($kalan->refresh());

    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

it('ÖDENMEMİŞ siparişin iadesi açılamıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iade-l.test');
    $satir = $siparis->items->firstOrFail();

    /*
    | ⚠️ Geri verilecek para yok. Kontrol edilmeseydi sağlayıcıya hiç var
    | olmayan bir tahsilatın iadesi gönderilirdi.
    */
    expect(fn () => app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]))
        ->toThrow(ReturnNotRefundableException::class);
});

it('★ UÇTAN: müşteri talep açıyor, panel para iadesini yapıyor', function () {
    $siparis = iadeyeHazirSiparis('iade-m.test');
    app(StorePublication::class)->yayinla();

    // Müşteri neyi ne zamana kadar iade edebileceğini görüyor.
    $bilgi = $this->getJson("http://iade-m.test/api/orders/{$siparis->uuid}/returns")->assertOk();

    expect($bilgi->json('items.0.withdrawal_open'))->toBeTrue()
        ->and($bilgi->json('items.0.delivered_at'))->not->toBeNull();

    $satirId = $bilgi->json('items.0.id');

    $talepUuid = $this->postJson("http://iade-m.test/api/orders/{$siparis->uuid}/returns", [
        'items' => [['order_item_id' => $satirId, 'quantity' => 1]],
        'reason' => 'Beğenmedim',
    ])->assertStatus(201)->json('return.uuid');

    // Panel tarafı.
    $sahip = User::where('is_owner', true)->firstOrFail();
    $token = panelTokeni('iade-m.test', $sahip->email);

    guardOnbelleginiTemizle();
    $this->withToken($token)->postJson("http://iade-m.test/panel/returns/{$talepUuid}/approve")->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)->postJson("http://iade-m.test/panel/returns/{$talepUuid}/receive", ['restock' => true])->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://iade-m.test/panel/returns/{$talepUuid}/refund")
        ->assertStatus(201)
        ->assertJsonPath('payment_status', 'partially_refunded');
});

it('★ UÇTAN: order.refund İZNİ OLMAYAN personel iade yapamıyor', function () {
    $siparis = iadeyeHazirSiparis('iade-n.test');
    $satir = $siparis->items->firstOrFail();

    $talep = app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]);
    app(ReturnService::class)->teslimAlindi($talep);

    /*
    | ⚠️ "Katalog" rolünde `product.write` var ama `order.refund` YOK.
    */
    $personel = User::factory()->create(['email' => 'katalog@iade-n.test', 'password' => 'sifre1234']);
    $personel->roles()->sync(Role::where('name', 'Katalog')->pluck('id'));

    $token = panelTokeni('iade-n.test', $personel->email);

    /*
    | ⚠️ `order.view` YETMİYOR: para geri gönderen işlem, siparişi
    | görebilen herkese açık olamaz.
    */
    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://iade-n.test/panel/returns/{$talep->uuid}/refund")
        ->assertForbidden();

    expect(Refund::count())->toBe(0);
});

it('iki markanın iadeleri karışmıyor', function () {
    $a = iadeyeHazirSiparis('iade-o.test');
    app(ReturnService::class)->talepAc($a, [$a->items->firstOrFail()->id => 1]);

    tenancy()->end();
    iadeyeHazirSiparis('iade-p.test');

    expect(OrderReturn::count())->toBe(0);
});
