<?php

use App\Domain\Privacy\Anonymizer;
use App\Domain\Privacy\DataRequestService;
use App\Domain\Privacy\InvalidDataRequestException;
use App\Domain\Privacy\UnknownDataSubjectException;
use App\Domain\Settings\StorePublication;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Mail\PrivacyVerificationMail;
use App\Models\Customer;
use App\Models\DataRequest;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

/*
| KVKK — anonimleştirme ve veri indirme (2G).
|
| ★ İKİ ASIL İDDİA:
|   1  ASIL İŞ `orders`'ta — sipariş bir fotoğraf, adres kopyası orada
|   2  doğrulanmamış talep HİÇBİR ŞEYİ silmiyor
|
| ⚠️ Birincisi sessiz arıza sınıfı: yalnızca `customers` temizlenseydi
| kişisel veri siparişlerde olduğu gibi kalır ve kimse fark etmezdi.
*/

it('★ ASIL İŞ ORDERS\'TA — sipariş adresi de tanınmaz oluyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-a.test');

    // Ödeme öncesi de olsa kişisel veri siparişte duruyor.
    expect($siparis->shipping_full_name)->toBe('Ayşe Yılmaz')
        ->and($siparis->email)->toBe('ayse@ornek.test');

    app(Anonymizer::class)->siparisiAnonimlestir($siparis);

    $siparis->refresh();

    /*
    | ⚠️ Yalnızca `customers` temizlenseydi bu alanlar OLDUĞU GİBİ kalırdı
    | — sipariş adresi müşteri defterinden okunmuyor, kendi kopyasından
    | okunuyor (1D).
    */
    expect($siparis->shipping_full_name)->toBe(Anonymizer::SILINDI)
        ->and($siparis->shipping_phone)->toBe(Anonymizer::SILINDI)
        ->and($siparis->shipping_line1)->toBe(Anonymizer::SILINDI)
        ->and($siparis->email)->not->toContain('ayse@ornek.test');
});

it('★ TUTAR ve SATIRLAR duruyor — silme değil anonimleştirme', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-b.test');

    $tutar = $siparis->grand_total;
    $satirSayisi = $siparis->items->count();

    app(Anonymizer::class)->siparisiAnonimlestir($siparis);

    /*
    | ⚠️ `DELETE` yazılsaydı ya yasal kayıt kaybolur ya yabancı anahtarlar
    | kırılırdı. Muhasebe için sipariş yerinde kalmak zorunda.
    */
    expect($siparis->refresh()->grand_total)->toBe($tutar)
        ->and($siparis->items()->count())->toBe($satirSayisi)
        ->and(Order::count())->toBe(1);
});

it('★ ŞEHİR ve İLÇE KALIYOR — satış coğrafyası bozulmasın', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-c.test');

    app(Anonymizer::class)->siparisiAnonimlestir($siparis);

    /*
    | ⚠️ Kişiyi tanımlamıyorlar ama markanın satış dağılımı raporu onlara
    | dayanıyor. Silinseydi geçmiş rapor bozulur, KVKK açısından hiçbir
    | kazanç olmazdı.
    */
    expect($siparis->refresh()->shipping_city)->toBe('İstanbul')
        ->and($siparis->shipping_district)->toBe('Kadıköy');
});

it('★ sipariş MİSAFİR SİPARİŞİNE dönüşüyor', function () {
    markaKur('kvkk-d.test');
    magazayiHazirla();

    $musteri = Customer::factory()->create(['email' => 'ali@ornek.test', 'name' => 'Ali Veli']);
    ['siparis' => $siparis] = odemeAsamasiSiparisiMusteriyle('kvkk-d.test', $musteri);

    expect($siparis->customer_id)->toBe($musteri->id);

    app(Anonymizer::class)->musteriyiAnonimlestir($musteri);

    // ⚠️ 2G-K2: yapı zaten hazırdı — misafir siparişi Faz 1'den beri var.
    expect($siparis->refresh()->customer_id)->toBeNull()
        ->and($musteri->refresh()->name)->toBe(Anonymizer::SILINDI)
        ->and($musteri->email)->not->toContain('ali@ornek.test');
});

it('★ DOĞRULANMAMIŞ talep HİÇBİR ŞEYİ silmiyor', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-e.test');

    app(DataRequestService::class)->talepAc(
        DataRequestType::Anonymize,
        $siparis->email,
        $siparis->order_number,
        'http://kvkk-e.test/gizlilik/onay',
    );

    /*
    | ⚠️ 2G-K3'ün kalbi. Tek aşamalı olsaydı sipariş numarası tahmin eden
    | biri (numaralar ardışık, 1D-K4) başkasının verisini sildirebilirdi —
    | ve silme GERİ ALINAMAZ.
    */
    expect($siparis->refresh()->shipping_full_name)->toBe('Ayşe Yılmaz');

    Mail::assertQueued(PrivacyVerificationMail::class);
});

it('doğrulanınca siliniyor ve talep kaydı KİŞİSEL VERİ TAŞIMIYOR', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-f.test');

    $talep = app(DataRequestService::class)->talepAc(
        DataRequestType::Anonymize,
        $siparis->email,
        $siparis->order_number,
        'http://kvkk-f.test/gizlilik/onay',
    );

    app(DataRequestService::class)->dogrulaVeUygula($talep->token);

    expect($siparis->refresh()->shipping_full_name)->toBe(Anonymizer::SILINDI);

    /*
    | ★ 2G-K4. Talep kaydı duruyor ("sildim mi silmedim mi" cevaplanabilsin)
    | ama e-posta TEMİZLENDİ — yoksa silme kaydı, silinen e-postanın
    | kopyasını saklardı.
    */
    $talep->refresh();

    expect($talep->status)->toBe(DataRequestStatus::Completed)
        ->and($talep->email)->toBeNull()
        ->and($talep->customer_id)->toBeNull()
        ->and($talep->email_hash)->not->toBeEmpty()
        ->and($talep->email_hash)->not->toContain('@');
});

it('★ AYNI BAĞLANTI İKİ KEZ kullanılamıyor', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-g.test');

    $talep = app(DataRequestService::class)->talepAc(
        DataRequestType::Export,
        $siparis->email,
        $siparis->order_number,
        'http://kvkk-g.test/gizlilik/onay',
    );

    app(DataRequestService::class)->dogrulaVeUygula($talep->token);

    /*
    | ⚠️ Dışa aktarmada bu kritik: bağlantıyı ele geçiren biri veriyi
    | tekrar tekrar indirebilirdi.
    */
    expect(fn () => app(DataRequestService::class)->dogrulaVeUygula($talep->token))
        ->toThrow(InvalidDataRequestException::class);
});

it('SÜRESİ DOLMUŞ bağlantı çalışmıyor', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-h.test');

    $talep = app(DataRequestService::class)->talepAc(
        DataRequestType::Anonymize,
        $siparis->email,
        $siparis->order_number,
        'http://kvkk-h.test/gizlilik/onay',
    );

    $this->travel(DataRequestService::GECERLILIK_SAAT + 1)->hours();

    expect(fn () => app(DataRequestService::class)->dogrulaVeUygula($talep->token))
        ->toThrow(InvalidDataRequestException::class)
        ->and($siparis->refresh()->shipping_full_name)->toBe('Ayşe Yılmaz');

    expect(DataRequest::firstOrFail()->refresh()->status)->toBe(DataRequestStatus::Expired);
});

it('★ TANINMAYAN kişi için talep AÇILMIYOR', function () {
    Mail::fake();

    odemeAsamasiSiparisi('kvkk-i.test');

    /*
    | ⚠️ Yalnızca e-posta yeterli sayılsaydı herhangi biri bir adres yazıp
    | o adrese posta gönderttirebilirdi.
    */
    expect(fn () => app(DataRequestService::class)->talepAc(
        DataRequestType::Anonymize,
        'yabanci@ornek.test',
        'TM-2026-999999',
        'http://kvkk-i.test/gizlilik/onay',
    ))->toThrow(UnknownDataSubjectException::class);

    Mail::assertNothingQueued();
});

it('veri dökümü siparişleri ve adresleri içeriyor', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-j.test');

    $talep = app(DataRequestService::class)->talepAc(
        DataRequestType::Export,
        $siparis->email,
        $siparis->order_number,
        'http://kvkk-j.test/gizlilik/onay',
    );

    $dokum = app(DataRequestService::class)->dogrulaVeUygula($talep->token);

    // Misafir siparişi — kayıtlı hesap yok, ama akış çalışıyor.
    expect($dokum)->toBeArray()
        ->and($dokum)->toHaveKey('siparisler');
});

it('★ UÇTAN: talep jetonu CEVAPTA DÖNMÜYOR', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-k.test');
    app(StorePublication::class)->yayinla();

    $cevap = $this->postJson('http://kvkk-k.test/api/privacy/requests', [
        'type' => 'anonymize',
        'email' => $siparis->email,
        'order_number' => $siparis->order_number,
    ])->assertStatus(202);

    /*
    | ⚠️ Jeton dönseydi doğrulama postasının anlamı kalmazdı — talebi açan
    | onu doğrudan kullanırdı.
    */
    $jeton = DataRequest::firstOrFail()->token;

    expect(json_encode($cevap->json()))->not->toContain($jeton);
});

it('★ UÇTAN: onay bağlantısı MAĞAZA KAPALIYKEN de çalışıyor', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('kvkk-l.test');

    $talep = app(DataRequestService::class)->talepAc(
        DataRequestType::Anonymize,
        $siparis->email,
        $siparis->order_number,
        'http://kvkk-l.test/gizlilik/onay',
    );

    /*
    | ⚠️ Yasal bir hak, mağazanın açık olmasına bağlanamaz.
    */
    $this->getJson("http://kvkk-l.test/gizlilik/onay/{$talep->token}")->assertOk();

    expect($siparis->refresh()->shipping_full_name)->toBe(Anonymizer::SILINDI);
});

it('★ UÇTAN: geçersiz bağlantı 410 ve AYRINTI VERMİYOR', function () {
    odemeAsamasiSiparisi('kvkk-m.test');

    $cevap = $this->getJson('http://kvkk-m.test/gizlilik/onay/uydurma-jeton')
        ->assertStatus(410);

    /*
    | ⚠️ "Bu jeton vardı ama süresi doldu" gibi bir ayrım, jeton tahmin
    | edene geri bildirim olurdu.
    */
    expect($cevap->json('message'))->toBe('Bağlantı geçersiz veya süresi dolmuş.');
});

it('★ İKİ MARKANIN talepleri karışmıyor', function () {
    Mail::fake();

    ['siparis' => $a] = odemeAsamasiSiparisi('kvkk-n.test');
    app(DataRequestService::class)->talepAc(
        DataRequestType::Anonymize, $a->email, $a->order_number, 'http://kvkk-n.test/gizlilik/onay',
    );

    tenancy()->end();
    odemeAsamasiSiparisi('kvkk-o.test');

    expect(DataRequest::count())->toBe(0);
});
