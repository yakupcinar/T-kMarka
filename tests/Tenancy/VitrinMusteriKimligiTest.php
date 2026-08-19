<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;

/*
| MÜŞTERİ KİMLİĞİ VİTRİN SAYFALARINDA (4.5I) — gerçek kullanımda bulundu.
|
| ★ Müşteri "siparişlerim"de hiçbir siparişini göremiyordu. Sayfa doğru
| yazılmıştı; sorun VERİDEYDİ: geliştirme markasında 24 siparişin HEPSİ,
| ödenmişler dâhil, `customer_id = null` idi.
|
| ⚠️ Sebep tek satır: sayfa katmanı `$istek->user()` çağırıyordu ve
| VARSAYILAN guard `customer` (sanctum, TOKEN). Oturumla giren müşteri
| sanctum'a görünmüyor; her sayfa onu MİSAFİR sanıyordu.
|
| ⚠️ BU TEST `actingAs` KULLANMIYOR ve kullanamaz: `actingAs` varsayılan
| guard'ı da değiştiriyor, yani `user()` çağrısının gerçekte ne
| döndürdüğünü GİZLİYOR. Ölçüldü — hatalı kodla `actingAs`'li test
| YEŞİL geçti, gerçek `/giris` POST'uyla düştü.
*/

function vitrinMusterisi(string $eposta = 'musteri@ornek.com'): Customer
{
    return Customer::create([
        'email' => $eposta,
        'password' => bcrypt('sifre1234'),
        'name' => 'Ayşe Yılmaz',
    ]);
}

function kimlikTestiVaryanti(): ProductVariant
{
    $urun = app(ProductService::class)->olustur(['title' => 'Deri Cüzdan', 'brand' => 'Demo']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'CZ-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $varyant;
}

function vitrinMagazasi(): void
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

it('★★★ GIRIS YAPMIS musterinin sepeti KENDISINE baglaniyor', function () {
    vitrinMagazasi();
    $varyant = kimlikTestiVaryanti();
    $musteri = vitrinMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234'])
        ->assertRedirect();

    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);

    $sepet = Cart::orderByDesc('id')->first();

    expect($sepet?->customer_id)->toBe($musteri->id);
});

it('★★★ GIRIS YAPMIS musterinin SIPARISI kendisine baglaniyor', function () {
    vitrinMagazasi();
    $varyant = kimlikTestiVaryanti();
    $musteri = vitrinMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    $this->post('http://marka-a.test/odeme', [
        'email' => $musteri->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'shipping' => ornekAdres(),
    ])->assertRedirect();

    $siparis = Order::orderByDesc('id')->firstOrFail();

    expect($siparis->customer_id)->toBe($musteri->id);

    /*
    | ⚠️ Sipariş listesi de ölçülüyor: bağlanma tek başına yetmez,
    | müşterinin onu GÖRMESİ gerekiyor — şikâyet buydu.
    */
    $this->get('http://marka-a.test/hesabim')
        ->assertOk()
        ->assertSee($siparis->order_number);
});

it('★★★ KAYITLI ADRES odeme sayfasinda SECILEBILIYOR', function () {
    vitrinMagazasi();
    $varyant = kimlikTestiVaryanti();
    $musteri = vitrinMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);

    $this->post('http://marka-a.test/hesabim/adresler', ornekAdres(['title' => 'Ev']))
        ->assertRedirect();

    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);

    /*
    | ⚠️ Ekranda GÖRÜNMESİ ölçülüyor. Şikâyet "adres kaydettim ama ödemede
    | yine soruyor"du — yani eksik olan sunucu değil EKRANDI (4.5G'nin
    | adres formu dersinin aynısı).
    */
    $this->get('http://marka-a.test/odeme')
        ->assertOk()
        ->assertSee('Ev')
        ->assertSee('Başka adrese gönder')
        ->assertSee('name="adres_uuid"', escape: false);

    $adres = $musteri->addresses()->firstOrFail();
    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    /*
    | ⚠️ `shipping` GÖNDERİLMİYOR — kayıtlı adres seçildiğinde form
    | alanları zorunlu olmamalı. Zorunlu kalsaydı müşteri seçtiği adresi
    | bir de elle yazmak zorunda kalırdı.
    */
    $this->post('http://marka-a.test/odeme', [
        'email' => $musteri->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'adres_uuid' => $adres->uuid,
    ])->assertRedirect();

    $siparis = Order::orderByDesc('id')->firstOrFail();

    expect($siparis->shipping_city)->toBe($adres->city)
        ->and($siparis->shipping_line1)->toBe($adres->line1);
});

it('★★★ BASKASININ adresi secilemiyor', function () {
    vitrinMagazasi();
    $varyant = kimlikTestiVaryanti();
    $kurban = vitrinMusterisi('kurban@ornek.com');
    $saldirgan = vitrinMusterisi('saldirgan@ornek.com');

    $kurbanAdresi = $kurban->addresses()->create(ornekAdres(['title' => 'Ev']));

    $this->post('http://marka-a.test/giris', ['email' => $saldirgan->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    /*
    | ⚠️ Ekranda gizlemek doğrulama değildir: uuid elle gönderilebilir.
    | Sahiplik kontrolü olmasaydı saldırgan kurbanın adresine sipariş
    | çıkarabilirdi.
    */
    $this->post('http://marka-a.test/odeme', [
        'email' => $saldirgan->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'adres_uuid' => $kurbanAdresi->uuid,
    ])->assertSessionHas('hata');

    expect(Order::count())->toBe(0);
});

it('★★ ADRESI OLMAYAN musteriye eski form gosteriliyor', function () {
    vitrinMagazasi();
    $varyant = kimlikTestiVaryanti();
    $musteri = vitrinMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);

    $this->get('http://marka-a.test/odeme')
        ->assertOk()
        ->assertDontSee('Başka adrese gönder')
        ->assertSee('name="shipping[line1]"', escape: false);
});

it('★★★ TARAYICININ gonderdigi gibi: adres secili + BOS shipping alanlari', function () {
    vitrinMagazasi();
    $varyant = kimlikTestiVaryanti();
    $musteri = vitrinMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/hesabim/adresler', ornekAdres());
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);

    $adres = $musteri->addresses()->firstOrFail();
    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    /*
    | ★ FARK BURADA: `shipping` anahtarları GÖNDERİLİYOR ama BOŞ.
    |
    | ⚠️ Tarayıcı gizli formdaki alanları da gönderiyor (gizlemek
    | göndermemek değildir). `ConvertEmptyStringsToNull` boş metni
    | **null**'a çeviriyor ve `string` kuralı null'da düşüyor — müşteri
    | "shipping.full_name metin olmalıdır" uyarısı alıp ödemeye
    | gidemiyordu.
    |
    | ⚠️ Anahtarları HİÇ göndermeyen test bunu GÖREMEZ: middleware'in
    | dönüştüreceği bir değer olmuyor. Gerçek `curl` koşusu ortaya
    | çıkardı; bu test o koşunun gövdesini birebir taşıyor.
    */
    $this->post('http://marka-a.test/odeme', [
        'email' => $musteri->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'adres_uuid' => $adres->uuid,
        'shipping' => [
            'full_name' => '',
            'phone' => '',
            'city' => '',
            'district' => '',
            'neighborhood' => '',
            'line1' => '',
            'line2' => '',
            'postal_code' => '',
        ],
    ])->assertSessionHasNoErrors();

    $siparis = Order::orderByDesc('id')->firstOrFail();

    expect($siparis->shipping_city)->toBe($adres->city);
});

it('★★ ADRES SECILMEDIYSE alanlar HALA zorunlu — `nullable` gevsetmiyor', function () {
    vitrinMagazasi();
    $varyant = kimlikTestiVaryanti();
    $musteri = vitrinMusterisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 1]);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    /*
    | ⚠️ `nullable` eklerken korkulan şey: zorunluluğun sessizce
    | kalkması. `required_*` kuralları ÖRTÜK olduğu için değer null olsa
    | da koşuyor — ama bu iddia ÖLÇÜLMELİ, yoksa boş adresli sipariş
    | oluşur ve kargo çıkamaz.
    */
    $this->post('http://marka-a.test/odeme', [
        'email' => $musteri->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'shipping' => ['full_name' => '', 'phone' => '', 'city' => '', 'district' => '', 'line1' => ''],
    ])->assertSessionHasErrors(['shipping.full_name', 'shipping.city', 'shipping.line1']);

    expect(Order::count())->toBe(0);
});
