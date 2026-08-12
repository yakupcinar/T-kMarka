<?php

use App\Domain\Cart\CartService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Payment\PaymentService;
use App\Domain\Stock\StockService;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;

/*
| "Para geldi, mal yok" (1E-K5 · 1E.6).
|
| ★ Kaçınılmaz senaryo — sağlayıcıyı 60 dakikaya zorlayamıyoruz:
|
|   10:00  sipariş verildi, 2 adet rezerve
|   11:05  rezervasyon öldü (60 dk), stok serbest
|   11:06  başka müşteri o adetleri aldı
|   11:08  webhook: "ödeme başarılı"        ← PARA ÇEKİLDİ, MAL YOK
|
| ⚠️ Karar (1E-K5): ödemeyi REDDETME, siparişi KABUL ET ve İŞARETLE.
| Ama Shopify'ın uyarısı da kararın parçası: sorun eksi stoğa izin vermek
| değil, HABER VERMEDEN izin vermek. O yüzden işaret PANELDE görünüyor.
*/

/**
 * Aktif markada İKİNCİ bir sipariş açar — `markaKur` çağırmadan.
 *
 * ⚠️ Yeni marka kurulsaydı iki sipariş ayrı şemalarda olur, panel listesi
 * testi hiçbir sıralama iddiasını sınayamazdı.
 */
function ayniMarkadaSiparis(): Order
{
    $varyant = ProductVariant::where('sku', 'TS-1')->firstOrFail();
    $varyant->forceFill(['stock' => 20])->save();

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 1);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    return app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));
}

it('★ rezervasyon ÖLDÜKTEN sonra ödeme gelirse sipariş İŞARETLENİYOR', function () {
    ['siparis' => $s, 'varyant' => $v, 'referans' => $ref, 'tutar' => $tutar] =
        bildirimeHazirSiparis('acik-a.test');

    // Müşteri bankada oyalandı; 60 dakika doldu.
    $this->travel(70)->minutes();
    expect(app(StockService::class)->suresiDolanlariDusur())->toBe(1);

    // Serbest kalan stoğu başkası aldı.
    $v->refresh()->forceFill(['stock' => 0])->save();

    // Şimdi bildirim geliyor — geç ama geçerli.
    bildirimGonder('acik-a.test', $s->order_number, $ref, $tutar)
        ->assertOk()
        ->assertJsonPath('result', 'paid');

    /*
    | ⚠️ Sipariş KABUL EDİLİYOR: reddedip iade etmek müşteriyi 3-5 gün
    | parasız bırakırdı, tedarik edebilen marka da satışı kaybederdi.
    | Ama SESSİZ KALMIYOR.
    */
    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($s->stock_shortfall)->toBeTrue();
});

it('SAĞLAM siparişte işaret KONMUYOR', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('acik-b.test');

    bildirimGonder('acik-b.test', $s->order_number, $ref, $tutar)->assertOk();

    /*
    | ⚠️ Simetrik sınav. İşaret her siparişe konsaydı panelde uyarı
    | gürültüye dönüşür, marka bakmayı bırakır ve gerçek durum
    | görünmez olurdu.
    */
    expect($s->refresh()->stock_shortfall)->toBeFalse();
});

it('★ PANELDE listede GÖRÜNÜYOR ve EN ÜSTTE', function () {
    ['siparis' => $acikli, 'referans' => $r1, 'tutar' => $t1] = bildirimeHazirSiparis('acik-c.test');
    $sahip = User::where('is_owner', true)->firstOrFail();

    // Birinci sipariş: rezervasyonu ölmüş, ödemesi geç gelmiş.
    $this->travel(70)->minutes();
    app(StockService::class)->suresiDolanlariDusur();
    bildirimGonder('acik-c.test', $acikli->order_number, $r1, $t1)->assertOk();

    // İkinci sipariş: sağlam ve DAHA YENİ.
    $saglam = ayniMarkadaSiparis();
    $sonuc = app(PaymentService::class)->baslat($saglam, 'http://acik-c.test/odeme/donus');
    bildirimGonder('acik-c.test', $saglam->order_number, $sonuc->saglayiciReferansi, (string) $saglam->grand_total)
        ->assertOk();

    $token = panelTokeni('acik-c.test', $sahip->email);

    guardOnbelleginiTemizle();
    $liste = $this->withToken($token)->getJson('http://acik-c.test/panel/orders')->assertOk();

    /** @var list<array{order_number: string, stock_shortfall: bool}> $siparisler */
    $siparisler = $liste->json('orders');

    /*
    | ⚠️ SORUNLU OLAN EN ÜSTTE — daha ESKİ olmasına rağmen. Tarihe göre
    | sıralansaydı yoğun bir günde uyarı üçüncü sayfaya düşer ve pratikte
    | görünmez olurdu.
    */
    expect($siparisler[0]['order_number'])->toBe($acikli->order_number)
        ->and($siparisler[0]['stock_shortfall'])->toBeTrue()
        ->and($siparisler[1]['stock_shortfall'])->toBeFalse();
});

it('★ varyant KATALOGDAN KALDIRILSA da ödeme işlenebiliyor', function () {
    ['siparis' => $s, 'varyant' => $v, 'referans' => $ref, 'tutar' => $tutar] =
        bildirimeHazirSiparis('acik-d.test');

    /*
    | ★ 1E.6'da BU TEST GERÇEK BİR HATA BULDU.
    |
    | Varyant `SoftDeletes` kullanıyor ve varsayılan sorgu silinmişleri
    | görmüyordu. Marka ödemesi yolda olan bir siparişin varyantını
    | katalogdan kaldırdığında kilit sorgusu `firstOrFail()` ile
    | patlıyordu: webhook 404 dönüyor, sağlayıcı üç kez deniyor, üçü de
    | düşüyor ve TAHSİLAT HİÇ KAYDEDİLMİYORDU. Para çekilmiş, sistemde iz yok.
    |
    | Katalogdan kaldırmak bir VİTRİN kararı; yolda olan siparişin
    | muhasebesini bozmamalı.
    */
    $v->delete();

    bildirimGonder('acik-d.test', $s->order_number, $ref, $tutar)
        ->assertOk()
        ->assertJsonPath('result', 'paid');

    /*
    | Rezervasyon duruyordu, yani gerçek bir stok açığı YOK — işaret
    | konmamalı. Sipariş satırı zaten bir FOTOĞRAF: varyant silinse de
    | yaşıyor (1D).
    */
    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($s->stock_shortfall)->toBeFalse()
        ->and($v->refresh()->stock)->toBe(3);
});

it('KISMİ açık da işaretleniyor', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('acik-e.test');

    // İki adetlik rezervasyonun tamamı düştü.
    $this->travel(70)->minutes();
    app(StockService::class)->suresiDolanlariDusur();

    bildirimGonder('acik-e.test', $s->order_number, $ref, $tutar)->assertOk();

    expect($s->refresh()->stock_shortfall)->toBeTrue()
        ->and(Order::where('stock_shortfall', true)->count())->toBe(1);
});

it('★ ödeme BAŞARISIZ olunca işaret konmuyor', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('acik-f.test');

    $this->travel(70)->minutes();
    app(StockService::class)->suresiDolanlariDusur();

    bildirimGonder('acik-f.test', $s->order_number, $ref, $tutar, basarili: false)->assertOk();

    /*
    | ⚠️ Para hiç çekilmedi — ortada çözülecek bir sorun yok. İşaret
    | konsaydı marka olmayan bir sorunu kovalardı.
    */
    expect($s->refresh()->stock_shortfall)->toBeFalse()
        ->and($s->payment_status)->toBe(PaymentStatus::Failed);
});
