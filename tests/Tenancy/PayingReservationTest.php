<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Stock\StockService;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\StockReservation;

/*
| Ödeme aşamasındaki rezervasyon (1E.2 · 1D-K3 güncellemesi).
|
| ★ Bu bloğun tek iddiası: müşteri BANKADAYKEN stoğu kimse kapamaz.
|
| ⚠️ Kapatılan senaryo şu: müşteri 3D Secure ekranında SMS kodunu
| girerken 15 dakika doluyor, temizlik görevi rezervasyonu düşürüyor,
| başkası son ürünü alıyor, sonra "ödeme başarılı" bildirimi geliyor.
| Para çekilmiş, mal yok — ve hiçbir yerde hata görünmüyor.
*/

it('ödeme başlayınca rezervasyon paying oluyor ve süre 60 dakikaya çıkıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('paying-a.test');

    $rezervasyon = StockReservation::firstOrFail();
    $ilkSure = $rezervasyon->expires_at;

    expect($rezervasyon->status)->toBe(ReservationStatus::Held);

    app(CheckoutService::class)->odemeBaslatildi($siparis);

    $rezervasyon->refresh();

    $yeniSure = $rezervasyon->expires_at;

    expect($rezervasyon->status)->toBe(ReservationStatus::Paying)
        ->and($ilkSure)->not->toBeNull()
        ->and($yeniSure)->not->toBeNull();

    // 15 → 60: en az 40 dakika kazanılmış olmalı.
    expect($yeniSure?->greaterThan($ilkSure ?? now()))->toBeTrue()
        ->and(now()->diffInMinutes($yeniSure))->toBeGreaterThan(40);
});

it('★ ödeme SÜRERKEN temizlik görevi rezervasyona DOKUNMUYOR', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('paying-b.test');

    app(CheckoutService::class)->odemeBaslatildi($siparis);

    /*
    | Müşteri bankada. 15 dakikalık pencere çoktan doldu — eski kuralla
    | rezervasyon burada düşerdi.
    */
    $this->travel(20)->minutes();

    expect(app(StockService::class)->suresiDolanlariDusur())->toBe(0)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Paying)
        ->and($varyant->refresh()->committed)->toBe(2);
});

it('★ ÖDEMEYE HİÇ BAŞLAMAYAN sepet 15 dakikada düşüyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('paying-c.test');

    /*
    | ⚠️ Simetrik sınav. Süreyi TOPLUCA 60'a çıkarmak da bir seçenekti ve
    | bu testi geçemezdi: ödemeye hiç başlamamış terk edilmiş sepet stoğu
    | bir saat rehin tutardı.
    */
    $this->travel(20)->minutes();

    expect(app(StockService::class)->suresiDolanlariDusur())->toBe(1)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Released)
        ->and($varyant->refresh()->committed)->toBe(0);
});

it('ödemesi yarıda kalan rezervasyon 60 dakikada düşüyor', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('paying-d.test');

    app(CheckoutService::class)->odemeBaslatildi($siparis);

    /*
    | ⚠️ `Paying` süresiz YAŞAMIYOR. Temizlik `Held` ile sınırlansaydı
    | ödemesi yarıda kalan her rezervasyon sonsuza kadar kalır, o stok
    | bir daha hiç satılamazdı.
    */
    $this->travel(70)->minutes();

    expect(app(StockService::class)->suresiDolanlariDusur())->toBe(1)
        ->and($varyant->refresh()->committed)->toBe(0);
});

it('★ ödeme aşamasındayken BAŞARILI olunca stok GERÇEKTEN düşüyor', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('paying-e.test');

    app(CheckoutService::class)->odemeBaslatildi($siparis);

    /*
    | ⚠️ ASIL TUZAK BURADA. `kesinlestir()` yalnızca `Held` kabul etseydi
    | bu çağrı sessizce hiçbir şey yapmaz, sipariş `paid` olur ve stok
    | hiç düşmezdi. Ne istisna, ne uyarı — yalnızca yanlış envanter.
    */
    app(CheckoutService::class)->odemeBasarili($siparis);

    expect($varyant->refresh()->stock)->toBe(3)
        ->and($varyant->committed)->toBe(0)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Committed)
        ->and($siparis->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('ödeme aşamasındayken BAŞARISIZ olunca stok geri veriliyor', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('paying-f.test');

    app(CheckoutService::class)->odemeBaslatildi($siparis);
    app(CheckoutService::class)->odemeBasarisiz($siparis);

    expect($varyant->refresh()->stock)->toBe(5)
        ->and($varyant->committed)->toBe(0)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Released);
});

it('★ ödeme SÜRERKEN sayaç denetimi YALANCI ALARM vermiyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('paying-g.test');

    app(CheckoutService::class)->odemeBaslatildi($siparis);

    /*
    | ⚠️ Denetim yalnızca `Held` sayardı: ödeme süren her sipariş
    | "committed 2, rezervasyon 0" diye raporlanır, gece görevi her sabah
    | yalancı alarm verirdi. Gerçek alarmın fark edilmemesi de böyle başlar.
    */
    expect(app(StockService::class)->tutarsizliklar())->toBe([]);
});

it('ödeme başlatma sipariş DURUMUNU değiştirmiyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('paying-h.test');

    app(CheckoutService::class)->odemeBaslatildi($siparis);

    /*
    | ⚠️ "Ödemeye başladı" bir ödeme durumu DEĞİL — para hâlâ çekilmedi
    | ve hiç çekilmeyebilir. Durum değişseydi ödeme sayfasını açıp
    | vazgeçen her müşteri için sipariş yanlış durumda kalırdı.
    */
    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('iki kez ödemeye alınması süreyi ikinci kez uzatmıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('paying-i.test');

    app(CheckoutService::class)->odemeBaslatildi($siparis);
    $sure = StockReservation::firstOrFail()->expires_at;

    $this->travel(5)->minutes();
    app(CheckoutService::class)->odemeBaslatildi($siparis);

    /*
    | ⚠️ Süre her çağrıda uzatılsaydı, "öde"ye defalarca basan müşteri
    | stoğu sınırsız süre rehin tutabilirdi.
    */
    expect(StockReservation::firstOrFail()->expires_at?->equalTo($sure ?? now()))->toBeTrue();
});
