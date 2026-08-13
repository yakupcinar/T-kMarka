<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Order\FulfillmentService;
use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use App\Mail\OrderPaidMail;
use App\Mail\PaymentFailedMail;
use App\Mail\ShipmentMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

/*
| Bildirim altyapısı (2H).
|
| ★ ÜÇ İDDİA:
|   1  posta KUYRUKTA gider — istek beklemiyor
|   2  posta düşerse İŞ BOZULMUYOR
|   3  gönderen MARKANIN kendi adresi — platformun değil
|
| ⚠️ Üçüncüsü kiracılık tuzağı: posta işçi sürecinde üretiliyor ve
| gönderen adresi oradaki `settings`'ten okunuyor. Kimlik taşınmasaydı
| A markasının siparişi B'nin adıyla giderdi — hata vermeden.
*/

it('★ sipariş onayı ÖDEME BAŞARILI olunca gidiyor — sipariş oluşunca DEĞİL', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('posta-a.test');

    /*
    | ⚠️ Sipariş oluştu ama ödenmedi. Burada mail gitseydi, ödeme
    | sayfasını açıp vazgeçen her müşteri "siparişiniz alındı" maili alır
    | ve gelmeyecek bir kargoyu beklerdi.
    */
    Mail::assertNothingQueued();

    app(CheckoutService::class)->odemeBasarili($siparis);

    Mail::assertQueued(OrderPaidMail::class, fn ($p) => $p->hasTo($siparis->email));
});

it('ödeme başarısızda bilgilendirme gidiyor', function () {
    Mail::fake();

    ['siparis' => $siparis] = odemeAsamasiSiparisi('posta-b.test');

    app(CheckoutService::class)->odemeBasarisiz($siparis);

    // ⚠️ 1E.7.3'te müşteri neden reddedildiğini hiç öğrenemiyordu.
    Mail::assertQueued(PaymentFailedMail::class);
});

it('kargo ve teslim bildirimi PAKET bazında gidiyor', function () {
    Mail::fake();

    $siparis = sevkiyatlikSiparis('posta-c.test');
    $servis = app(FulfillmentService::class);
    [$tisort] = $siparis->items->all();

    $paket = $servis->olustur($siparis, [$tisort->id => 1]);

    // ⚠️ Paket OLUŞUNCA mail YOK — henüz kargoya verilmedi.
    Mail::assertNotQueued(ShipmentMail::class);

    $servis->kargoyaVer($paket, 'Yurtiçi', 'YK-1');
    Mail::assertQueued(ShipmentMail::class, 1);

    $servis->teslimEdildi($paket->refresh());
    Mail::assertQueued(ShipmentMail::class, 2);
});

it('★ POSTA GERÇEKTEN KUYRUĞA giriyor — istek beklemiyor', function () {
    config(['queue.default' => 'redis']);
    Redis::connection()->del('queues:default');

    ['siparis' => $siparis] = odemeAsamasiSiparisi('posta-d.test');

    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    | ⚠️ `Mail::fake()` KULLANILMIYOR: sahte posta kuyruğa hiç uğramıyor,
    | yani "kuyrukta gidiyor" iddiası sınanmamış olurdu. 1F'de aynı tuzağa
    | düşülmüştü — sahte altyapı, ölçülmek istenen mekanizmayı atlıyor.
    |
    | Kuyrukta hem olay hem posta var; ikisi de istek dışında koşuyor.
    */
    expect(Redis::connection()->llen('queues:default'))->toBeGreaterThan(0);

    $govde = json_decode((string) Redis::connection()->lpop('queues:default'), true);

    // ⚠️ Ve iş KİRACI KİMLİĞİNİ taşıyor (M-2.4).
    expect($govde['tenant_id'] ?? null)->not->toBeNull();
});

it('★ GÖNDEREN markanın kendi adresi', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('posta-e.test');

    app(SettingsService::class)->yaz(SettingGroup::Store, 'contact_email', 'destek@markam.test');
    app(SettingsService::class)->yaz(SettingGroup::Store, 'name', 'Markam');

    $zarf = (new OrderPaidMail($siparis))->envelope();

    /** @var Address $gonderen */
    $gonderen = $zarf->from;

    /*
    | ⚠️ Müşteri "TıkMarka"dan değil, alışveriş yaptığı MARKADAN mail
    | almalı. Platformun adresi kullanılsaydı marka kendi müşterisiyle
    | arasına bizi sokmuş olurdu.
    */
    expect($gonderen->address)->toBe('destek@markam.test')
        ->and($gonderen->name)->toBe('Markam')
        ->and($zarf->subject)->toContain($siparis->order_number);
});

it('★ POSTA DÜŞERSE ödeme BOZULMUYOR', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('posta-f.test');

    /*
    | ⚠️ 2H-K2 · 1F-K3'ün tekrarı. Mailin gitmemesi kötü; ödemenin
    | işlenememesi felaket — para çekilmiş olurdu.
    */
    Mail::shouldReceive('to')->andThrow(new RuntimeException('posta yok'));

    app(CheckoutService::class)->odemeBasarili($siparis);

    expect($siparis->refresh()->payment_status->value)->toBe('paid');
});

it('gövde sipariş bilgisini taşıyor, KDV toplama eklenmiyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('posta-g.test');

    $govde = (new OrderPaidMail($siparis))->render();

    expect($govde)->toContain($siparis->order_number)
        ->and($govde)->toContain((string) $siparis->grand_total)

        // ⚠️ KDV gösteriliyor ama "dâhil" diye — toplama eklenmiş gibi
        // görünseydi müşteri fazladan ödediğini sanardı (§8.2).
        ->and($govde)->toContain('dâhil');
});

it('★ İKİ MARKANIN postası birbirinin adıyla gitmiyor', function () {
    ['siparis' => $a] = odemeAsamasiSiparisi('posta-h.test');
    app(SettingsService::class)->yaz(SettingGroup::Store, 'name', 'A Markası');
    app(SettingsService::class)->yaz(SettingGroup::Store, 'contact_email', 'a@a.test');

    /** @var Address $gonderenA */
    $gonderenA = (new OrderPaidMail($a))->envelope()->from;

    tenancy()->end();

    ['siparis' => $b] = odemeAsamasiSiparisi('posta-i.test');
    app(SettingsService::class)->yaz(SettingGroup::Store, 'name', 'B Markası');
    app(SettingsService::class)->yaz(SettingGroup::Store, 'contact_email', 'b@b.test');

    /** @var Address $gonderenB */
    $gonderenB = (new OrderPaidMail($b))->envelope()->from;

    expect($gonderenA->address)->toBe('a@a.test')
        ->and($gonderenB->address)->toBe('b@b.test');
});
