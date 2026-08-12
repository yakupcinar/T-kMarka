<?php

use App\Domain\Settings\StorePublication;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\StockReservation;

/*
| Ödeme dönüşü — callback (1E.5).
|
| ★ Bu bloğun tek iddiası: BU UÇ HİÇBİR ŞEY YAZMIYOR.
|
| ⚠️ Tarayıcı dönüşü ödeme kanıtı değil. Müşteri o ekrana hiç
| ulaşmayabilir, ya da adres çubuğuna kendisi `?status=success` yazabilir.
| Yazan tek yer webhook (1E.4).
*/

it('★ webhook GELMEDEN önce "işleniyor" gösteriyor — "başarısız" DEĞİL', function () {
    ['siparis' => $s, 'referans' => $ref] = bildirimeHazirSiparis('donus-a.test');

    $cevap = $this->getJson("http://donus-a.test/odeme/donus?ref={$ref}")->assertOk();

    /*
    | ⚠️ Bu ayrım kritik. iyzico ilk bildirimi 10-15 saniye sonra atıyor;
    | müşteri bu ekrana 3 saniyede varabilir. Ara durum "başarısız"
    | gösterilseydi müşteri paniğe kapılır, ikinci kez ödemeye çalışır ya
    | da bankasını arardı — oysa ödemesi yolda.
    */
    expect($cevap->json('state'))->toBe('processing')
        ->and($cevap->json('payment_status'))->toBe('pending')
        ->and($cevap->json('order_number'))->toBe($s->order_number);
});

it('★ DÖNÜŞ HİÇBİR ŞEY YAZMIYOR — sipariş, stok, ödeme aynı kalıyor', function () {
    ['siparis' => $s, 'varyant' => $v, 'referans' => $ref] = bildirimeHazirSiparis('donus-b.test');

    /*
    | ⚠️ Müşteri adres çubuğuna kendi "başarılı"sını yazıyor. Uç istekteki
    | alanlara baksaydı burada sipariş ödenmiş sayılırdı: bedava sipariş,
    | tek satırlık bir istekle.
    */
    $this->getJson("http://donus-b.test/odeme/donus?ref={$ref}&status=success&amount=1.00")
        ->assertOk()
        ->assertJsonPath('state', 'processing');

    expect($s->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($v->refresh()->stock)->toBe(5)
        ->and($v->committed)->toBe(2)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentAttemptStatus::Pending);
});

it('webhook geldikten SONRA "başarılı" gösteriyor', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('donus-c.test');

    bildirimGonder('donus-c.test', $s->order_number, $ref, $tutar)->assertOk();

    $this->getJson("http://donus-c.test/odeme/donus?ref={$ref}")
        ->assertOk()
        ->assertJsonPath('state', 'success')
        ->assertJsonPath('payment_status', 'paid');
});

it('başarısız ödemede "başarısız" gösteriyor', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('donus-d.test');

    bildirimGonder('donus-d.test', $s->order_number, $ref, $tutar, basarili: false)->assertOk();

    $this->getJson("http://donus-d.test/odeme/donus?ref={$ref}")
        ->assertOk()
        ->assertJsonPath('state', 'failed');

    expect(StockReservation::firstOrFail()->status->value)->toBe('released');
});

it('POST ile dönüş de çalışıyor', function () {
    ['referans' => $ref] = bildirimeHazirSiparis('donus-e.test');

    /*
    | ⚠️ Sağlayıcılar dönüşü GET ya da POST ile yapıyor (iyzico POST eder).
    | Tek yöntem tanımlansaydı gerçek sağlayıcı takıldığı gün müşteri
    | ödeme sonrası 405 ekranı görürdü.
    */
    $this->postJson("http://donus-e.test/odeme/donus?ref={$ref}")
        ->assertOk()
        ->assertJsonPath('state', 'processing');
});

it('BİLİNMEYEN referans 404', function () {
    bildirimeHazirSiparis('donus-f.test');

    $this->getJson('http://donus-f.test/odeme/donus?ref=FAKE-YOK')->assertNotFound();
    $this->getJson('http://donus-f.test/odeme/donus')->assertNotFound();
});

it('★ MAĞAZA KAPALIYKEN de dönüş ekranı çalışıyor', function () {
    ['referans' => $ref] = bildirimeHazirSiparis('donus-g.test');

    app(StorePublication::class)->kapat();

    /*
    | ⚠️ `magaza-acik` kapısının arkasında olsaydı bankadan dönen müşteri
    | 503 görürdü: parası gitmiş, ne olduğunu öğrenemiyor.
    */
    $this->getJson("http://donus-g.test/odeme/donus?ref={$ref}")
        ->assertOk()
        ->assertJsonPath('state', 'processing');
});

it('★ BİR MARKANIN referansı DİĞERİNDE görünmüyor', function () {
    ['referans' => $refA] = bildirimeHazirSiparis('donus-h.test');

    tenancy()->end();
    bildirimeHazirSiparis('donus-i.test');

    $this->getJson("http://donus-i.test/odeme/donus?ref={$refA}")->assertNotFound();
});
