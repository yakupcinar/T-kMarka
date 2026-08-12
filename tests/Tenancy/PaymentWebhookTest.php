<?php

use App\Domain\Payment\FakePaymentProvider;
use App\Domain\Payment\PaymentService;
use App\Domain\Settings\StorePublication;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockReservation;

/*
| Ödeme bildirimi — webhook (1E.4).
|
| ★ Bu blok 1E'nin kalbi: siparişin durumunu ve stoğu değiştiren TEK yer.
|
| ⚠️ Üç kapı, üç ayrı sessiz arıza:
|   imza     yoksa herkes bedava sipariş üretir
|   eşleşme  yoksa bilinmeyen referans sessizce yutulur
|   tekrar   yoksa aynı bildirim stoğu üç kez düşürür
*/

/**
 * Ödemesi BAŞLATILMIŞ sipariş üretir ve sağlayıcı referansını döndürür.
 *
 * @return array{siparis: Order, varyant: ProductVariant, referans: string, tutar: string}
 */
function bildirimeHazirSiparis(string $alanAdi): array
{
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi($alanAdi);
    app(StorePublication::class)->yayinla();

    $sonuc = app(PaymentService::class)->baslat($siparis, "http://{$alanAdi}/odeme/donus");

    return [
        'siparis' => $siparis,
        'varyant' => $varyant,
        'referans' => $sonuc->saglayiciReferansi,
        'tutar' => (string) $siparis->grand_total,
    ];
}

it('★ BAŞARILI bildirim: sipariş ödendi, STOK GERÇEKTEN düştü', function () {
    ['siparis' => $s, 'varyant' => $v, 'referans' => $ref, 'tutar' => $tutar] =
        bildirimeHazirSiparis('hook-a.test');

    bildirimGonder('hook-a.test', $s->order_number, $ref, $tutar)
        ->assertOk()
        ->assertJsonPath('result', 'paid');

    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($v->refresh()->stock)->toBe(3)
        ->and($v->committed)->toBe(0)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Committed)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentAttemptStatus::Captured);
});

it('★ AYNI BİLDİRİM ÜÇ KEZ: stok BİR KEZ düşüyor', function () {
    ['siparis' => $s, 'varyant' => $v, 'referans' => $ref, 'tutar' => $tutar] =
        bildirimeHazirSiparis('hook-b.test');

    /*
    | ⚠️ Tekrar teslim ARIZA DEĞİL TASARIM: iyzico 2xx alamazsa 15 dakika
    | arayla 3 kez daha yolluyor. Bu kapı olmasaydı stok her bildirimde
    | bir kez daha düşerdi — üç bildirim, üç kat düşüm, sıfır hata.
    */
    bildirimGonder('hook-b.test', $s->order_number, $ref, $tutar)->assertJsonPath('result', 'paid');
    bildirimGonder('hook-b.test', $s->order_number, $ref, $tutar)->assertJsonPath('result', 'already_processed');
    bildirimGonder('hook-b.test', $s->order_number, $ref, $tutar)->assertJsonPath('result', 'already_processed');

    // ⚠️ Tekrar 200 dönüyor — hata değil. Hata dönseydi sağlayıcı sonsuza
    // kadar denerdi.
    expect($v->refresh()->stock)->toBe(3)
        ->and(Payment::count())->toBe(1);
});

it('★ İMZASIZ bildirim 401 — hiçbir kayıt değişmiyor', function () {
    ['siparis' => $s, 'varyant' => $v, 'referans' => $ref, 'tutar' => $tutar] =
        bildirimeHazirSiparis('hook-c.test');

    $this->postJson('http://hook-c.test/webhooks/payment', [
        'order_number' => $s->order_number,
        'reference' => $ref,
        'status' => 'success',
        'amount' => $tutar,
    ])->assertStatus(401);

    /*
    | ⚠️ Bu kapı olmasaydı herkes bu isteği atıp bedava sipariş üretirdi.
    | Uçta kimlik doğrulaması YOK ve olamaz — sağlayıcı token bilmiyor.
    */
    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($v->refresh()->stock)->toBe(5)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentAttemptStatus::Pending);
});

it('★ TUTARI DEĞİŞTİRİLMİŞ bildirim reddediliyor', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('hook-d.test');

    ['yuk' => $yuk, 'imza' => $imza] = app(FakePaymentProvider::class)
        ->bildirim($s->order_number, $ref, $tutar);

    // Tutar düşürüldü ama ESKİ imza kullanılıyor.
    $yuk['amount'] = '1.00';

    $this->withHeader('X-Fake-Signature', $imza)
        ->postJson('http://hook-d.test/webhooks/payment', $yuk)
        ->assertStatus(401);

    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('★ İMZASI GEÇERLİ ama TUTARI FARKLI bildirim ödeme saymıyor', function () {
    ['siparis' => $s, 'referans' => $ref] = bildirimeHazirSiparis('hook-e.test');

    /*
    | ⚠️ İMZAYA RAĞMEN ikinci savunma. Sağlayıcı tarafındaki karışıklık ya
    | da yanlış eşleşen referans, 549,70'lik siparişe 1,00'lik ödemeyi
    | bağlayabilir. İmza yükü korur, tutarın DOĞRU SİPARİŞE ait olduğunu
    | garanti etmez.
    */
    bildirimGonder('hook-e.test', $s->order_number, $ref, '1.00')->assertStatus(422);

    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentAttemptStatus::Pending);
});

it('BAŞARISIZ bildirim: stok geri veriliyor, sipariş SİLİNMİYOR', function () {
    ['siparis' => $s, 'varyant' => $v, 'referans' => $ref, 'tutar' => $tutar] =
        bildirimeHazirSiparis('hook-f.test');

    bildirimGonder('hook-f.test', $s->order_number, $ref, $tutar, basarili: false)
        ->assertOk()
        ->assertJsonPath('result', 'failed');

    /*
    | ⚠️ Sipariş SİLİNMİYOR: "neden ödeme alınamadı" sorusunun cevabı
    | kayıtta kalmalı.
    */
    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($v->refresh()->stock)->toBe(5)
        ->and($v->committed)->toBe(0)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Released)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentAttemptStatus::Failed);
});

it('★ BİLİNMEYEN referans 404 — sağlayıcı TEKRAR DENESİN diye', function () {
    bildirimeHazirSiparis('hook-g.test');

    /*
    | ⚠️ 200 dönseydi sağlayıcı "işlendi" sanıp bir daha aramazdı; gerçekte
    | hiçbir şey olmamış olurdu ve ödeme sonsuza kadar kayıp giderdi.
    */
    bildirimGonder('hook-g.test', 'TM-2026-000999', 'FAKE-YOK', '100.00')->assertStatus(404);
});

it('★ MAĞAZA KAPALIYKEN bildirim yine işleniyor', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('hook-h.test');

    app(StorePublication::class)->kapat();

    /*
    | ⚠️ Uç `magaza-acik` kapısının arkasında olsaydı 503 alırdı: para
    | çekilmiş, sipariş sonsuza kadar `pending` kalırdı. Mağazayı kapatmak
    | "yeni sipariş alma" demek; "başlamış ödemeyi görmezden gel" demek değil.
    */
    bildirimGonder('hook-h.test', $s->order_number, $ref, $tutar)
        ->assertOk()
        ->assertJsonPath('result', 'paid');

    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('★ BİR MARKANIN bildirimi DİĞERİNDE işlenmiyor', function () {
    ['siparis' => $a, 'referans' => $refA, 'tutar' => $tutarA] = bildirimeHazirSiparis('hook-i.test');

    /*
    | ⚠️ Bildirim A'NIN BAĞLAMINDA imzalanıyor — testi ilk yazışımda
    | B'nin bağlamında imzalamıştım ve test kırmızı verdi. Kod doğruydu,
    | test yanlıştı: B'nin anahtarıyla imzalanan bildirim elbette B'de
    | geçerli. Kiracılık testinde "hangi bağlamdayım" sorusu, iddianın
    | kendisi kadar önemli.
    */
    ['yuk' => $yuk, 'imza' => $imza] = app(FakePaymentProvider::class)
        ->bildirim($a->order_number, $refA, $tutarA);

    tenancy()->end();
    bildirimeHazirSiparis('hook-j.test');
    tenancy()->end();

    /*
    | ⚠️ İmza anahtarı marka başına rastgele — A'nın bildirimi B'de imzayı
    | geçemiyor. Geçseydi bile referans B'nin şemasında yok.
    |
    | ⚠️ Yanlış şemaya yazılan tahsilat HATA VERMEZ: A'nın parası B'nin
    | defterinde görünürdü (0.5, kiracılık tuzağı).
    */
    $this->withHeader('X-Fake-Signature', $imza)
        ->postJson('http://hook-j.test/webhooks/payment', $yuk)
        ->assertStatus(401);
});
