<?php

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;

/*
| SEPET SAYACI VE BEKLEYEN SİPARİŞ (4.5J) — gerçek kullanımda bulundu.
|
| ★ Şikâyet: *"Sağ üstteki sayaç 2 gösteriyor ama içine girince boş…
| işlemi tekrarlayınca siparişler arttı, sayı 2'de sabit kaldı."*
|
| ⚠️ Kök sebep: rozet ile sepet sayfası AYRI YOLLARDAN okuyordu. Rozet
| `misafirSepetiBul()` çağırıyor, sayfa [CartResolver] kullanıyordu —
| giriş yapmışsa müşteri sepetini çözen tek yol o.
|
| İki yön de bozuk: bayat misafir çerezi varken rozet dolu / sepet boş;
| giriş yapmış müşterinin dolu sepetinde ise rozet HİÇ çıkmıyordu.
*/

function sayacMusterisi(): Customer
{
    return Customer::create([
        'email' => 'sayac@ornek.test', 'password' => bcrypt('sifre1234'), 'name' => 'Ayşe Yılmaz',
    ]);
}

/** Başlıktaki rozet sayısı — yoksa 0. */
function rozetSayisi(string $html): int
{
    return preg_match('#Sepet\s*<span>(\d+)</span>#', $html, $m) === 1 ? (int) $m[1] : 0;
}

it('★★★ GIRIS YAPMIS musterinin sepeti ROZETTE gorunuyor', function () {
    $varyant = sayacMagazasi();
    $musteri = sayacMusterisi();

    /*
    | ⚠️ `actingAs` DEĞİL — sayfa katmanında kimlik oturumda (4.5I).
    */
    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 2]);

    $html = $this->get('http://marka-a.test/')->getContent() ?: '';

    expect(rozetSayisi($html))->toBe(2);
});

it('★★★ ROZET ve SEPET SAYFASI ayni sepeti gosteriyor', function () {
    $varyant = sayacMagazasi();
    $musteri = sayacMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 3]);

    $rozet = rozetSayisi($this->get('http://marka-a.test/')->getContent() ?: '');

    /*
    | ⚠️ Asıl iddia BU: iki yüzey aynı sayıyı göstermeli. Yalnızca rozete
    | bakan bir test, iki yolun ayrışmasını yine kaçırırdı.
    */
    $sepet = $this->get('http://marka-a.test/sepet')->getContent() ?: '';

    expect($rozet)->toBe(3)
        ->and($sepet)->toContain('3');
});

it('★★★ ODEME BEKLEYEN siparise IPTAL ve ODEMEYI TAMAMLA sunuluyor', function () {
    $varyant = sayacMagazasi();
    $musteri = sayacMusterisi();

    bekleyenSiparis($varyant, $musteri);

    /*
    | ⚠️ Müşteri ödeme adımından geri çıkınca sipariş `pending` kalıyor ve
    | listede birikiyordu — yapabileceği HİÇBİR ŞEY yoktu.
    */
    $this->get('http://marka-a.test/hesabim')
        ->assertOk()
        ->assertSee('Ödemeyi tamamla')
        ->assertSee('iptal et');
});

it('★★★ MUSTERI bekleyen siparisini IPTAL edebiliyor — stok serbest kaliyor', function () {
    $varyant = sayacMagazasi();
    $musteri = sayacMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 2]);

    $sozlesme = app(LegalDocumentService::class)
        ->guncelSurum(LegalDocumentType::DistanceSales);

    $this->post('http://marka-a.test/odeme', [
        'email' => $musteri->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'shipping' => ornekAdres(),
    ])->assertRedirect();

    $siparis = Order::orderByDesc('id')->firstOrFail();

    expect($varyant->refresh()->committed)->toBe(2);

    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iptal")
        ->assertRedirect();

    /*
    | ⚠️ ASIL KAZANÇ BU: bağlı stok HEMEN serbest kalıyor. İptal olmasaydı
    | 60 dakika kimseye satılamazdı (StockService::ODEME_DAKIKA).
    */
    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Cancelled)
        ->and($varyant->refresh()->committed)->toBe(0);
});

it('★★★ ODENMIS siparis MUSTERI tarafindan iptal EDILEMIYOR', function () {
    $varyant = sayacMagazasi();
    $musteri = sayacMusterisi();

    $siparis = bekleyenSiparis($varyant, $musteri);

    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    | ⚠️ İzin verilseydi müşteri parasını geri almadan siparişini
    | "iptal" eder, marka da göndermeyeceği bir siparişi tahsil etmiş
    | olurdu. Ödenmiş siparişin yolu İADE (2B).
    */
    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iptal")
        ->assertSessionHas('hata');

    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('★★ BASKASININ siparisi iptal edilemiyor', function () {
    $varyant = sayacMagazasi();
    $kurban = sayacMusterisi();

    $siparis = bekleyenSiparis($varyant, $kurban);

    $saldirgan = Customer::create([
        'email' => 'saldirgan@ornek.test', 'password' => bcrypt('sifre1234'), 'name' => 'S',
    ]);

    $this->post('http://marka-a.test/giris', ['email' => $saldirgan->email, 'password' => 'sifre1234']);

    // ⚠️ 404, 403 DEĞİL (1A.5).
    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iptal")
        ->assertNotFound();

    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});
