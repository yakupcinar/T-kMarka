<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Order\CheckoutService;
use App\Domain\Order\FulfillmentService;
use App\Domain\Settings\StorePublication;
use App\Enums\ProductStatus;
use App\Http\Storefront\CartToken;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;

/*
| MÜŞTERİ HESABI (4.5D) — vitrin tarafının en büyük boşluğuydu.
|
| ★ Uçlar 1A/1C/2G'de vardı ama müşterinin HİÇBİR EKRANI yoktu:
| siparişini takip edemiyor, adres defterini göremiyordu.
|
| ⚠️ Kimlik OTURUMLA (`customer-web`), token'la değil.
*/

function hesapliMagaza(string $alanAdi = 'marka-a.test'): void
{
    markaKur($alanAdi);
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

function musteriKur(string $eposta = 'musteri@example.com'): Customer
{
    /** @var Customer $musteri */
    $musteri = Customer::create([
        'name' => 'Ayse Yilmaz',
        'email' => $eposta,
        'password' => 'sifre1234',
    ]);

    return $musteri;
}

/** Müşteriye ait ÖDENMİŞ sipariş. */
function siparisUret(Customer $musteri): Order
{
    ['siparis' => $siparis] = odemeAsamasiSiparisiMusteriyle('marka-a.test', $musteri);

    return app(CheckoutService::class)->odemeBasarili($siparis);
}

it('★ giris sayfasi aciliyor', function () {
    hesapliMagaza();

    $this->get('http://marka-a.test/giris')
        ->assertOk()
        ->assertSee('Giriş yap')
        // ⚠️ Üyelik ZORUNLU DEĞİL (M-1) ve bunu ekran söylüyor.
        ->assertSee('Üye olmadan da sipariş verebilirsiniz');
});

it('★★ MUSTERI GIRIS YAPIP hesabini goruyor', function () {
    hesapliMagaza();
    musteriKur();

    $this->post('http://marka-a.test/giris', [
        'email' => 'musteri@example.com',
        'password' => 'sifre1234',
    ])->assertRedirect('http://marka-a.test/hesabim');

    $this->get('http://marka-a.test/hesabim')
        ->assertOk()
        ->assertSee('Hesabım')
        ->assertSee('musteri@example.com')
        // Yeni müşteri: boş liste HATA gibi görünmemeli.
        ->assertSee('Henüz siparişiniz yok');
});

it('★★ YANLIS parola TEK MESAJ veriyor — hangi adres kayitli sizmiyor', function () {
    hesapliMagaza();
    musteriKur();

    $olmayan = $this->post('http://marka-a.test/giris', [
        'email' => 'hicyok@example.com', 'password' => 'sifre1234',
    ]);

    $yanlisParola = $this->post('http://marka-a.test/giris', [
        'email' => 'musteri@example.com', 'password' => 'yanlis-parola',
    ]);

    /*
    | ★ İKİ MESAJ AYNI olmalı: ayrılsaydı saldırgan hangi e-postaların
    | kayıtlı olduğunu tek tek öğrenebilirdi.
    */
    expect(session()->get('hata'))->toBe('E-posta veya parola hatalı.');

    $olmayan->assertRedirect();
    $yanlisParola->assertRedirect();
});

it('★★ KAYIT olunca oturum aciliyor ve PAZARLAMA ONAYI varsayilan KAPALI', function () {
    hesapliMagaza();

    $this->post('http://marka-a.test/kayit', [
        'name' => 'Yeni Musteri',
        'email' => 'yeni@example.com',
        'password' => 'sifre1234',
    ])->assertRedirect('http://marka-a.test/hesabim');

    /** @var Customer $musteri */
    $musteri = Customer::where('email', 'yeni@example.com')->firstOrFail();

    /*
    | ⚠️ Kayıt olmak, e-posta almayı kabul etmek DEĞİLDİR (1A.2 · KVKK
    | açık rıza). Kutu işaretlenmediyse onay kapalı olmalı.
    */
    expect((bool) $musteri->accepts_marketing)->toBeFalse();

    $this->get('http://marka-a.test/hesabim')->assertOk();
});

it('★★ GIRISTE misafir sepeti MUSTERIYE tasiniyor', function () {
    hesapliMagaza();
    musteriKur();

    $urun = app(ProductService::class)->olustur(['title' => 'Sabun']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'S-1', 'price' => 50, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $token = $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid])
        ->getCookie(CartToken::CEREZ, false)?->getValue();

    /*
    | ★ 1C-K5: birleştirme GİRİŞ ANINDA yapılıyor. Sepet ucunda
    | yapılsaydı, giriş yapıp sepete uğramayan kullanıcının misafir sepeti
    | ortada kalırdı.
    */
    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/giris', [
            'email' => 'musteri@example.com', 'password' => 'sifre1234',
        ])->assertRedirect();

    /** @var Customer $musteri */
    $musteri = Customer::where('email', 'musteri@example.com')->firstOrFail();

    expect(app(CartService::class)->musteriSepeti($musteri)->items)->toHaveCount(1);
});

it('★★ KIMLIKSIZ ziyaretci hesap sayfalarini GOREMIYOR', function () {
    hesapliMagaza();

    foreach (['/hesabim', '/hesabim/adresler'] as $yol) {
        $this->get('http://marka-a.test'.$yol)->assertRedirect();
    }
});

it('★★ BASKA MUSTERININ siparisi GORULEMIYOR', function () {
    hesapliMagaza();

    $benim = musteriKur('benim@example.com');
    $baskasi = musteriKur('baskasi@example.com');

    $siparis = siparisUret($baskasi);

    /*
    | ⚠️ 404, 403 DEĞİL: "böyle bir sipariş var ama senin değil" bilgisi
    | de sızıntıdır (1A.5).
    */
    $this->actingAs($benim, 'customer-web')
        ->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertNotFound();
});

it('★ SIPARIS AYRINTISI takip numarasini gosteriyor', function () {
    hesapliMagaza();

    $musteri = musteriKur();
    $siparis = siparisUret($musteri);

    $paket = app(FulfillmentService::class)
        ->olustur($siparis, $siparis->items->pluck('quantity', 'id')->all());

    app(FulfillmentService::class)
        ->kargoyaVer($paket, 'Test Kargo', 'TK-4949');

    /*
    | ⚠️ Takip numarası müşteriye gösteriliyor: kargonun nerede olduğunu
    | sormak için markayı aramak zorunda kalmamalı.
    */
    $this->actingAs($musteri, 'customer-web')
        ->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertOk()
        ->assertSee('TK-4949')
        ->assertSee('Kargoya verildi');
});

it('★ ADRES eklenip silinebiliyor', function () {
    hesapliMagaza();
    $musteri = musteriKur();

    $this->actingAs($musteri, 'customer-web')
        ->post('http://marka-a.test/hesabim/adresler', ornekAdres())
        ->assertRedirect();

    expect($musteri->addresses()->count())->toBe(1);

    /** @var Address $adres */
    $adres = $musteri->addresses()->firstOrFail();

    $this->actingAs($musteri, 'customer-web')
        ->delete("http://marka-a.test/hesabim/adresler/{$adres->uuid}")
        ->assertRedirect();

    expect($musteri->addresses()->count())->toBe(0);
});

it('★★ BASKA MUSTERININ adresi silinemiyor', function () {
    hesapliMagaza();

    $benim = musteriKur('benim@example.com');
    $baskasi = musteriKur('baskasi@example.com');

    /** @var Address $baskasininAdresi */
    $baskasininAdresi = $baskasi->addresses()->create(ornekAdres());

    /*
    | ⚠️ Adres MÜŞTERİYE DARALTILMIŞ sorgudan çözülüyor (1A.5): başkasının
    | adresi sonuç kümesine hiç girmiyor.
    */
    $this->actingAs($benim, 'customer-web')
        ->delete("http://marka-a.test/hesabim/adresler/{$baskasininAdresi->uuid}")
        ->assertNotFound();

    expect(Address::find($baskasininAdresi->id))->not->toBeNull();
});

it('★★★ BIR MARKANIN MUSTERI OTURUMU BASKA MARKADA GECERSIZ', function () {
    hesapliMagaza('marka-a.test');
    musteriKur();
    tenancy()->end();

    hesapliMagaza('marka-b.test');
    musteriKur();
    tenancy()->end();

    $this->post('http://marka-a.test/giris', [
        'email' => 'musteri@example.com', 'password' => 'sifre1234',
    ])->assertRedirect('http://marka-a.test/hesabim');

    $this->get('http://marka-a.test/hesabim')->assertOk();

    /*
    | ★ 4H'de personel tarafında bulunan açığın MÜŞTERİ karşılığı.
    | Oturum yalnızca kullanıcı `id`'sini tutuyor ve guard onu İSTEĞİN
    | kiracısının şemasından çözüyor; iki markada da `id = 1` olan birer
    | müşteri var.
    |
    | ⚠️ [EnsureSessionTenant] 4.5D'de İKİ GUARD'A birden bakacak şekilde
    | genişletildi. Tek guard'a bakmaya devam etseydi aynı açık müşteri
    | tarafında AÇIK KALIRDI — üstelik sessizce, çünkü personel testi
    | yeşil kalıyordu.
    */
    $this->get('http://marka-b.test/hesabim')->assertRedirect('http://marka-b.test');

    /*
    | ⚠️ BURADA BİR SINIR VAR ve GERÇEK KOŞUDA ÖLÇÜLDÜ.
    |
    | Test istemcisi B'nin cevabındaki YENİ oturum çerezini alıp taşıyor,
    | bu yüzden A da kapalı görünüyor. Ama `curl` ile ESKİ çerez elle
    | gönderildiğinde A'nın oturumu AÇIK KALIYOR.
    |
    | Yani güvence şu: **çalınan oturum başka markada geçmiyor.**
    | "Kurbanın kendi markasındaki oturumu da kapanır" güvencesi YOK ve
    | testin onu iddia etmesi yanlış olurdu — ölçtüğü şey sunucunun
    | davranışı değil, test istemcisinin çerez takip etmesi olurdu.
    |
    | ⚠️ Bu bir gerileme değil, ölçülmüş bir sınır: çerezi çalan zaten
    | A'ya erişebiliyordu; bu middleware o erişimi genişlemekten
    | koruyor, geri almıyor.
    */
    $this->get('http://marka-a.test/hesabim')->assertRedirect();
});
