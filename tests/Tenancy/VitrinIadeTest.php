<?php

use App\Domain\Returns\ReturnService;
use App\Domain\Settings\StorePublication;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;

/*
| VİTRİNDE İADE EKRANI (4.5K) — gerçek kullanımda bulundu.
|
| ★ İade uçları 2B'de vardı (`api/orders/{siparis}/returns`) ama vitrinde
| EKRANI YOKTU; panelde de talep AÇILAMIYORDU (4.5L'de eklendi). Yani
| iade, kodu tamamen yazılmış olmasına rağmen PRATİKTE ULAŞILAMAZ bir
| özellikti — "uç var ≠ kullanılabilir"in en net örneği.
|
| ⚠️ Vitrin sunucuda render ediliyor (4-K1), yani METİN ARAMAK doğru
| yöntem. Panelde bunun TERSİ geçerli (Inertia: prop'lara bakılır).
*/

/**
 * Ödenmiş, teslim edilmiş ve MÜŞTERİYE BAĞLI sipariş.
 *
 * ⚠️ Ad çakışması kontrol edildi (`grep -rn "function iadelikSiparis" tests/`).
 *
 * @return array{siparis: Order, musteri: Customer}
 */
function iadelikSiparis(): array
{
    ['siparis' => $siparis, 'musteri' => $musteri] = yorumaHazir('marka-a.test');

    /*
    | ⚠️ Mağaza YAYINLANMALI: kapalı mağazada vitrin 503 dönüyor ve test
    | "iade formu yok" değil "sayfa açılmıyor" ölçerdi.
    */
    app(StorePublication::class)->yayinla();

    return ['siparis' => $siparis->refresh(), 'musteri' => $musteri];
}

it('★★★ SIPARIS SAYFASINDA iade formu GORUNUYOR', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = iadelikSiparis();

    iadeciGirisi($musteri);

    $this->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertOk()
        ->assertSee('İade talebi oluştur')
        ->assertSee('Cayma hakkı')
        ->assertSee('name="adetler[', escape: false);
});

it('★★★ MUSTERI iade talebi ACABILIYOR', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = iadelikSiparis();

    iadeciGirisi($musteri);

    $satir = $siparis->items()->firstOrFail();

    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iade", [
        'adetler' => [$satir->id => 1],
        'tur' => 'cayma',
        'sebep' => 'Beden olmadı',
    ])->assertRedirect();

    $talep = OrderReturn::where('order_id', $siparis->id)->firstOrFail();

    /*
    | ⚠️ Müşteri yalnızca TALEP açıyor (2B-K1): durum `requested`, para
    | iadesi YOK. `refunded` doğsaydı müşteri parasını beklemeye başlar,
    | marka ürünü hiç görmeden ödeme yapmış olurdu.
    */
    expect($talep->status->value)->toBe('requested')
        ->and((bool) $talep->is_withdrawal)->toBeTrue()
        ->and($talep->items->sum('quantity'))->toBe(1);
});

it('★★★ BASKASININ siparisine iade acilamiyor', function () {
    ['siparis' => $siparis] = iadelikSiparis();

    $yabanci = Customer::create([
        'name' => 'Yabanci', 'email' => 'yabanci@ornek.test', 'password' => bcrypt('sifre1234'),
    ]);

    iadeciGirisi($yabanci);

    $satir = $siparis->items()->firstOrFail();

    /*
    | ⚠️ 404, 403 DEĞİL: "böyle bir sipariş var ama senin değil" bilgisi
    | de sızıntıdır (1A.5).
    */
    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iade", [
        'adetler' => [$satir->id => 1],
        'tur' => 'cayma',
    ])->assertNotFound();

    expect(OrderReturn::count())->toBe(0);
});

it('★★★ SIPARIS ADEDINDEN FAZLASI iade edilemiyor', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = iadelikSiparis();

    iadeciGirisi($musteri);

    $satir = $siparis->items()->firstOrFail();

    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iade", [
        'adetler' => [$satir->id => $satir->quantity + 5],
        'tur' => 'cayma',
    ])->assertSessionHas('hata');

    expect(OrderReturn::count())->toBe(0);
});

it('★★★ ACIK TALEP kalan adedi DUSURUYOR — ekran da gosteriyor', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = iadelikSiparis();

    /*
    | ⚠️ HER SATIR iade ediliyor — yalnızca ilki alınsaydı test "kalan
    | yok" ekranını değil, kalanı olan bir ekranı ölçerdi.
    */
    $hepsi = $siparis->items->pluck('quantity', 'id')->all();

    app(ReturnService::class)->talepAc($siparis, $hepsi);

    iadeciGirisi($musteri);

    /*
    | ⚠️ "Kaç adet iade edebilirim" SERVİSTEN geliyor. Ekran kendi
    | hesabını yapsaydı iki formül olur ve biri güncellenmeden kalırdı:
    | müşteri form gönderir, sunucu reddeder, sebebini anlamazdı.
    */
    $this->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertOk()
        ->assertSee('tüm ürünler için iade talebi açılmış')
        ->assertDontSee('İade talebi oluştur');
});

it('★★ BOS FORM anlasilir mesajla reddediliyor', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = iadelikSiparis();

    iadeciGirisi($musteri);

    $satir = $siparis->items()->firstOrFail();

    /*
    | ⚠️ Tarayıcı her satırı gönderiyor — seçilmeyenler `0` olarak.
    | Sunucu bunu "boş talep" diye anlamazsa `OverReturnException`
    | fırlar ve müşteri teknik bir mesaj görür.
    |
    | ⚠️ MESAJIN KENDİSİ ölçülüyor, yalnızca `hata` anahtarının varlığı
    | değil. Kırma denemesi bunu gösterdi: erken kontrol kaldırılınca
    | servis yine istisna atıyor ve `hata` yine doluyordu — test
    | GEÇİYORDU ama ölçtüğü şey "bir hata var"dı, "anlaşılır bir hata
    | var" değil.
    */
    $this->post("http://marka-a.test/hesabim/siparis/{$siparis->uuid}/iade", [
        'adetler' => [$satir->id => 0],
        'tur' => 'cayma',
    ])->assertSessionHas('hata', 'İade etmek istediğiniz ürünlerin adedini girin.');

    expect(OrderReturn::count())->toBe(0);
});

it('★★ ACILAN TALEP siparis sayfasinda DURUMUYLA gorunuyor', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = iadelikSiparis();

    $satir = $siparis->items()->firstOrFail();
    app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]);

    iadeciGirisi($musteri);

    /*
    | ⚠️ Talebin DURUMU gösterilmezse müşteri "gönderdim mi, ne oldu"
    | sorusunun cevabını hiçbir yerden alamaz ve markayı arar.
    */
    $this->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertOk()
        ->assertSee('Talep alındı, marka değerlendiriyor');
});
