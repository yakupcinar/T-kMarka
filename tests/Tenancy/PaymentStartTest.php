<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Payment\OrderNotPayableException;
use App\Domain\Payment\PaymentService;
use App\Domain\Settings\StorePublication;
use App\Enums\PaymentAttemptStatus;
use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StockReservation;

/*
| Ödeme başlatma (1E.3).
|
| ★ Üç iddia:
|   1  tutar SUNUCUDA üretiliyor — istemci karışamıyor
|   2  çift tıklama İKİNCİ ÇEKİM açmıyor
|   3  yönlendirmeden önce rezervasyon ömrü 60 dakikaya çıkıyor
|
| ⚠️ Bu uç PARA TAHSİL ETMİYOR. Sonuç webhook'la gelecek (1E.4).
*/

it('ödeme başlatınca yönlendirme adresi ve deneme kaydı doğuyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-a.test');

    $sonuc = app(PaymentService::class)->baslat($siparis, 'http://bas-a.test/odeme/donus');

    $deneme = Payment::firstOrFail();

    expect($sonuc->yonlendirmeAdresi)->toContain($sonuc->saglayiciReferansi)
        ->and($deneme->provider)->toBe('fake')
        ->and($deneme->provider_ref)->toBe($sonuc->saglayiciReferansi)
        ->and($deneme->status)->toBe(PaymentAttemptStatus::Pending)

        // ★ Anahtar = sipariş numarası (1E-K4).
        ->and($deneme->idempotency_key)->toBe($siparis->order_number);
});

it('★ TUTAR SUNUCUDAN geliyor — siparişin kendi toplamı', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-b.test');

    app(PaymentService::class)->baslat($siparis, 'http://bas-b.test/odeme/donus');

    /*
    | ⚠️ Deneme tutarı `orders.grand_total`'dan kopyalanıyor. İstemciden
    | alınsaydı müşteri kendi fiyatını belirlerdi — ödeme sistemlerinde
    | en klasik açık.
    */
    expect(Payment::firstOrFail()->amount)->toBe($siparis->grand_total);
});

it('★ ödeme başlayınca rezervasyon PAYING oluyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-c.test');

    expect(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Held);

    app(PaymentService::class)->baslat($siparis, 'http://bas-c.test/odeme/donus');

    /*
    | ⚠️ Bu adım atlanırsa müşteri bankadayken 15 dakika dolar,
    | rezervasyon düşer, stok kapılır ve ödeme başarılı geldiğinde
    | "para var, mal yok" olur (1E.2).
    */
    expect(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Paying);
});

it('★ ÇİFT TIKLAMA ikinci çekim açmıyor — aynı adres dönüyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-d.test');

    $ilk = app(PaymentService::class)->baslat($siparis, 'http://bas-d.test/odeme/donus');
    $ikinci = app(PaymentService::class)->baslat($siparis, 'http://bas-d.test/odeme/donus');

    /*
    | ⚠️ Sağlayıcıya ikinci kez gidilseydi yeni bir referans üretilir ve
    | elimizde aynı sipariş için İKİ referans olurdu — hangisinin
    | webhook'u geleceği belirsiz. Gerçek sağlayıcıda sonuç daha ağır:
    | müşteriden iki kez para çekilir.
    */
    expect(Payment::count())->toBe(1)
        ->and($ikinci->saglayiciReferansi)->toBe($ilk->saglayiciReferansi)
        ->and($ikinci->yonlendirmeAdresi)->toBe($ilk->yonlendirmeAdresi);
});

it('★ ÖDENMİŞ siparişe ikinci ödeme başlatılamıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-e.test');

    app(PaymentService::class)->baslat($siparis, 'http://bas-e.test/odeme/donus');
    app(CheckoutService::class)->odemeBasarili($siparis);

    expect(fn () => app(PaymentService::class)->baslat($siparis->refresh(), 'http://bas-e.test/odeme/donus'))
        ->toThrow(OrderNotPayableException::class);
});

it('★ UÇTAN: misafir kendi siparişinin ödemesini başlatabiliyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-f.test');

    // ⚠️ Uç `magaza-acik` kapısının arkasında — kapalı mağazada 503.
    app(StorePublication::class)->yayinla();

    $cevap = $this->postJson("http://bas-f.test/api/orders/{$siparis->uuid}/pay")->assertOk();

    expect($cevap->json('redirect_url'))->toBeString()

        /*
        | ⚠️ Dönüş adresi SUNUCUDA üretiliyor: markanın kendi alan adı.
        | İstekten alınsaydı saldırgan kendi sitesini yazar, müşteri
        | sahte bir "başarılı" ekranı görürdü (açık yönlendirme).
        */
        ->and($cevap->json('redirect_url'))->toContain('bas-f.test/odeme/donus');
});

it('★ UÇTAN: dönüş adresi İSTEKTEN alınmıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-g.test');

    // ⚠️ Uç `magaza-acik` kapısının arkasında — kapalı mağazada 503.
    app(StorePublication::class)->yayinla();

    $cevap = $this->postJson("http://bas-g.test/api/orders/{$siparis->uuid}/pay", [
        'return_url' => 'https://saldirgan.test/basarili',
    ])->assertOk();

    expect($cevap->json('redirect_url'))->not->toContain('saldirgan.test');
});

it('★ UÇTAN: BAŞKASININ siparişi 404 — 403 değil', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-h.test');

    // ⚠️ Uç `magaza-acik` kapısının arkasında — kapalı mağazada 503.
    app(StorePublication::class)->yayinla();

    // Sipariş bir MÜŞTERİYE bağlandı; artık misafirin değil.
    $musteri = Customer::factory()->create();
    $siparis->customer()->associate($musteri);
    $siparis->save();

    /*
    | ⚠️ 404, 403 DEĞİL: "böyle bir sipariş var ama senin değil" bilgisi
    | de sızıntıdır (1A.5). Misafir uuid'yi ele geçirse bile hiçbir şey
    | öğrenemiyor.
    */
    $this->postJson("http://bas-h.test/api/orders/{$siparis->uuid}/pay")->assertNotFound();
});

it('★ UÇTAN: ödenmiş siparişte 409 + durum bilgisi', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('bas-i.test');

    // ⚠️ Uç `magaza-acik` kapısının arkasında — kapalı mağazada 503.
    app(StorePublication::class)->yayinla();

    app(CheckoutService::class)->odemeBasarili($siparis);

    $this->postJson("http://bas-i.test/api/orders/{$siparis->uuid}/pay")
        ->assertStatus(409)
        ->assertJsonPath('payment_status', 'paid');
});

it('iki markanın ödeme denemeleri karışmıyor', function () {
    ['siparis' => $a] = odemeAsamasiSiparisi('bas-j.test');
    app(PaymentService::class)->baslat($a, 'http://bas-j.test/odeme/donus');

    tenancy()->end();
    ['siparis' => $b] = odemeAsamasiSiparisi('bas-k.test');
    app(PaymentService::class)->baslat($b, 'http://bas-k.test/odeme/donus');

    expect(Payment::count())->toBe(1)
        ->and(Payment::firstOrFail()->order_id)->toBe($b->id)
        ->and(Order::count())->toBe(1);
});
