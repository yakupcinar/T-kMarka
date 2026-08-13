<?php

use App\Domain\Order\AbandonedOrderService;
use App\Domain\Order\CheckoutService;
use App\Enums\PaymentStatus;
use App\Mail\AbandonedOrderMail;
use App\Mail\PaymentFailedMail;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
| Terk edilmiş ödeme (2F).
|
| ★ DÖRT İDDİA:
|   1  ödemesi yarım kalan siparişe hatırlatma GİDİYOR
|   2  ödenmiş / başarısız / çok yeni / çok eski siparişe GİTMİYOR
|   3  hatırlatma BİR KEZ gidiyor — eşzamanlı koşuda bile
|   4  iki markada karışmıyor
*/

/** `pending` kalmış bir sipariş üretir ve yaşını ayarlar. */
function terkEdilmisSiparis(string $alanAdi, int $dakikaOnce = 90): Order
{
    // ⚠️ `odemeAsamasiSiparisi` markayı KENDİSİ kuruyor.
    $hazir = odemeAsamasiSiparisi($alanAdi);
    $siparis = $hazir['siparis'];

    /*
    | ⚠️ `created_at` DOĞRUDAN yazılıyor: sipariş "90 dakika önce verilmiş"
    | gibi davranmalı. `sleep` ile beklemek testi kullanılamaz hâle
    | getirirdi.
    */
    DB::table('orders')->where('id', $siparis->id)
        ->update(['created_at' => now()->subMinutes($dakikaOnce)]);

    return $siparis->refresh();
}

it('★ ÖDEMESİ YARIM KALAN siparişe hatırlatma gidiyor', function () {
    Mail::fake();

    $siparis = terkEdilmisSiparis('terk-a.test');

    expect(app(AbandonedOrderService::class)->hatirlat())->toBe(1);

    Mail::assertQueued(AbandonedOrderMail::class, fn ($posta) => $posta->siparis->id === $siparis->id);

    // İşaretlendi mi?
    expect($siparis->refresh()->abandoned_reminded_at)->not->toBeNull();
});

it('★ HATIRLATMA BİR KEZ gidiyor — ikinci koşuda sessiz', function () {
    Mail::fake();

    terkEdilmisSiparis('terk-b.test');

    $servis = app(AbandonedOrderService::class);

    expect($servis->hatirlat())->toBe(1);

    /*
    | ⚠️ İşaretlenmeseydi bu görev SAATTE BİR aynı müşteriye mail atardı —
    | hata vermeden. 2F-K3'ün tamamı bu satırda.
    */
    expect($servis->hatirlat())->toBe(0);

    Mail::assertQueued(AbandonedOrderMail::class, 1);
});

it('★ ÇOK YENİ siparişe gitmiyor — müşteri hâlâ ödeme ekranında olabilir', function () {
    Mail::fake();

    /*
    | ⚠️ 30 dakika: rezervasyon (60 dk) hâlâ ayakta, müşteri 3DS ekranında
    | olabilir. Bu eşik olmasaydı "ödemenizi tamamlayın" maili tam ödeme
    | yaparken düşerdi.
    */
    terkEdilmisSiparis('terk-c.test', dakikaOnce: 30);

    expect(app(AbandonedOrderService::class)->hatirlat())->toBe(0);

    Mail::assertNothingQueued();
});

it('★ ÇOK ESKİ siparişe gitmiyor — GEÇMİŞE MAİL BOMBARDIMANI engeli', function () {
    Mail::fake();

    /*
    | ★ BU TESTİN SEBEBİ GERÇEK BİR TEHLİKE.
    |
    | `abandoned_reminded_at` kolonu SONRADAN eklendi; eklendiği an
    | geçmişteki BÜTÜN `pending` siparişler "hatırlatılmamış" görünüyor.
    | Üst sınır olmasaydı görevin İLK KOŞUSU aylar öncesine kadar herkese
    | mail atardı — hata vermeden, tek seferde, geri alınamaz biçimde.
    |
    | ⚠️ 2C'de aynı sınıf hata yaşandı (sonradan eklenen kolon geçmiş
    | satırlarda boş kalıyor). Orada sonuç sessiz bir eksiklikti; burada
    | sessiz bir SALDIRI olurdu.
    */
    terkEdilmisSiparis('terk-d.test', dakikaOnce: 60 * 24 * 5);

    expect(app(AbandonedOrderService::class)->hatirlat())->toBe(0);

    Mail::assertNothingQueued();
});

it('★ ÖDENMİŞ siparişe gitmiyor', function () {
    Mail::fake();

    $siparis = terkEdilmisSiparis('terk-e.test');

    app(CheckoutService::class)->odemeBasarili($siparis);

    expect(app(AbandonedOrderService::class)->hatirlat())->toBe(0);

    Mail::assertNotQueued(AbandonedOrderMail::class);
});

it('★ ÖDEMESİ BAŞARISIZ olana gitmiyor — o maili zaten aldı', function () {
    Mail::fake();

    $siparis = terkEdilmisSiparis('terk-f.test');

    /*
    | ⚠️ İki farklı hikâye: `failed`'da müşteri denedi ve reddedildi
    | (PaymentFailedMail gitti), `pending`'de hiç denemedi. İkisine de
    | gönderilseydi müşteri aynı sipariş için çelişkili iki mail alırdı.
    */
    DB::table('orders')->where('id', $siparis->id)
        ->update(['payment_status' => PaymentStatus::Failed->value]);

    expect(app(AbandonedOrderService::class)->hatirlat())->toBe(0);

    Mail::assertNotQueued(AbandonedOrderMail::class);
    Mail::assertNotQueued(PaymentFailedMail::class);
});

it('★ E-POSTASI BOŞ siparişe gitmiyor — null zaten imkânsız, ölçüldü', function () {
    Mail::fake();

    $siparis = terkEdilmisSiparis('terk-g.test');

    /*
    | ⚠️ ÖNCE `null` denendi ve VERİTABANI REDDETTİ:
    |   SQLSTATE[23502] null value in column "email" … violates not-null
    | Yani `whereNotNull` savunması ÖLÜ KODMUŞ; kaldırıldı.
    |
    | Boş metin ise geçiyor ve tehlikeli: gönderim sessizce düşer, sipariş
    | yine de "hatırlatıldı" işaretlenirdi — müşteri hiçbir zaman mail
    | almaz, kayıt aldığını söylerdi.
    */
    DB::table('orders')->where('id', $siparis->id)->update(['email' => '']);

    expect(app(AbandonedOrderService::class)->hatirlat())->toBe(0);

    expect($siparis->refresh()->abandoned_reminded_at)->toBeNull();
});

it('★ AYNI SİPARİŞ İKİ KOŞUCUDA — yalnızca biri gönderiyor', function () {
    Mail::fake();

    $siparis = terkEdilmisSiparis('terk-h.test');

    $servis = app(AbandonedOrderService::class);

    /*
    | ★ 1D-K5'in tekrarı: "acaba gönderilmiş mi" kontrolü yarışı ÇÖZMEZ.
    |
    | ⚠️ BU TEST BİR KIRMA DENEMESİNDEN DOĞDU. Önce `hatirlat()` üzerinden
    | yazılmıştı ve işaretleme gönderimden SONRAYA alındığında bile YEŞİL
    | kalıyordu — çünkü `bekleyenler()` zaten işaretlileri eliyor, yani
    | test koşullu güncellemeyi hiç ölçmüyordu.
    |
    | Şimdi iki koşucu doğrudan taklit ediliyor: ikisi de aynı siparişi
    | elinde tutuyor (sorgu ikisinde de onu döndürmüştü). Koşullu
    | güncelleme olmasaydı ikisi de mail atardı.
    |
    | ⚠️ Birden çok `scheduler` konteyneri olduğunda gerçekten olur —
    | `withoutOverlapping` yalnızca KENDİ süreci için geçerli (0.5'te
    | ölçülmüştü).
    */
    expect($servis->hatirlatBir($siparis))->toBeTrue()
        ->and($servis->hatirlatBir($siparis))->toBeFalse();

    Mail::assertQueued(AbandonedOrderMail::class, 1);
});

it('★ MAİL STOK SÖZÜ VERMİYOR', function () {
    $siparis = terkEdilmisSiparis('terk-i.test');

    $govde = (new AbandonedOrderMail($siparis))->render();

    /*
    | ⚠️ Rezervasyon 60 dakikada düşüyor (1D-K3) ve bu mail o süre
    | dolduktan SONRA gidiyor. "Ürünleriniz ayrıldı" demek tutulamayacak
    | bir söz olurdu — ödeme kabul edilse bile stok açığı çıkabilir
    | (1E-K5).
    */
    expect($govde)->not->toContain('ayrıldı')
        ->and($govde)->not->toContain('ayrılmış')
        ->and($govde)->toContain('stok durumu o anda kontrol edilecek')
        ->and($govde)->toContain($siparis->order_number);
});

it('★ KOMUT marka bağlamı OLMADAN çalışmıyor', function () {
    markaKur('terk-j.test');
    tenancy()->end();

    /*
    | ⚠️ 0.5'in beşinci tuzağı. Kontrol olmasaydı komut merkez bağlamda
    | "başarılı" döner, hiçbir markanın siparişini görmez ve kimse fark
    | etmezdi.
    */
    $this->artisan('siparis:terk-hatirlat')->assertExitCode(1);
});

it('★ UÇTAN: komut tenants:run ile gerçekten gönderiyor', function () {
    Mail::fake();

    terkEdilmisSiparis('terk-k.test');

    $this->artisan('siparis:terk-hatirlat')->assertExitCode(0);

    Mail::assertQueued(AbandonedOrderMail::class, 1);
});

it('iki markanın hatırlatmaları karışmıyor', function () {
    Mail::fake();

    terkEdilmisSiparis('terk-l.test');

    tenancy()->end();
    markaKur('terk-m.test');
    magazayiHazirla();

    /*
    | ⚠️ B markasında terk edilmiş sipariş YOK; A'nınki görünseydi B'nin
    | müşterisi olmayan birine B'nin adına mail giderdi.
    */
    expect(app(AbandonedOrderService::class)->bekleyenler()->count())->toBe(0)
        ->and(Order::count())->toBe(0);
});
