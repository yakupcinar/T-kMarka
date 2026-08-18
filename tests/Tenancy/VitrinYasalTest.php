<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Http\Storefront\CartToken;

/*
| VİTRİN YASAL METİN SAYFALARI (4.5A)
|
| ★ BUGÜNKÜ BİR HATAYI KAPATIYOR: ödeme sayfasındaki "Mesafeli satış
| sözleşmesini okudum" bağlantısı `/api/legal/...` uçuna gidiyordu ve
| müşteri HAM JSON görüyordu. Mesafeli satışta müşterinin sözleşmeyi
| OKUYABİLMESİ yasal bir zorunluluk.
|
| ⚠️ 4B'de gözden kaçtı çünkü test `assertSee('Mesafeli satış
| sözleşmesini')` diyordu — BAĞLANTININ VARLIĞINI ölçüyordu, NEREYE
| GİTTİĞİNİ değil.
*/

function yasalliMagaza(string $alanAdi = 'marka-a.test'): void
{
    markaKur($alanAdi);
    magazayiHazirla();
    app(StorePublication::class)->yayinla();
}

it('★★ SÖZLEŞME SAYFASI HTML donuyor, JSON degil', function () {
    yasalliMagaza();

    $cevap = $this->get('http://marka-a.test/yasal/distance_sales');

    $cevap->assertOk();
    expect($cevap->headers->get('content-type'))->toContain('text/html');

    $cevap->assertSee('Mesafeli Satış Sözleşmesi')
        ->assertSee('distance_sales metni')
        // ⚠️ JSON kalıntısı OLMAMALI.
        ->assertDontSee('version_id')
        ->assertDontSee('"document"', escape: false);
});

it('★★★ ODEME SAYFASINDAKI baglanti OKUNABILIR sayfaya gidiyor', function () {
    yasalliMagaza();

    $urun = app(ProductService::class)->olustur(['title' => 'Sabun']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'S-1', 'price' => 50, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $token = $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid])
        ->getCookie(CartToken::CEREZ, false)?->getValue();

    $odeme = $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/odeme');

    /*
    | ★ BU TEST 4B'DE OLMALIYDI. Orada yalnızca bağlantı METNİ aranıyordu;
    | nereye gittiği ölçülmüyordu ve `/api/legal/...` (ham JSON) olarak
    | kalmıştı.
    */
    $odeme->assertOk()
        ->assertSee('/yasal/distance_sales', escape: false)
        ->assertDontSee('/api/legal/', escape: false);
});

it('★ YAYINLANMAMIS metnin sayfasi 404 donuyor', function () {
    yasalliMagaza();

    /*
    | ⚠️ Boş sayfa gösterilseydi müşteri "sözleşme buymuş" sanır, marka da
    | eksiği fark etmezdi.
    */
    $this->get('http://marka-a.test/yasal/hicboylebirsey')->assertNotFound();
});

it('★ LISTE yalnizca YAYINLANMIS metinleri gosteriyor', function () {
    markaKur('marka-a.test');
    sirketBilgileriniDoldur();

    $belgeler = app(LegalDocumentService::class);

    // Yalnızca birini yayınla.
    $belgeler->taslagaYaz(LegalDocumentType::DistanceSales, 'satis metni');
    $belgeler->yayinla(LegalDocumentType::DistanceSales);

    // Diğeri yalnızca TASLAK.
    $belgeler->taslagaYaz(LegalDocumentType::Privacy, 'kvkk taslak');

    app(StorePublication::class);

    $cevap = $this->get('http://marka-a.test/yasal');

    /*
    | ⚠️ Yayınlanmamış metin listede GÖRÜNMEMELİ: tıklayınca 404 alınırdı
    | ve "var ama yok" hâli, hiç olmamasından kötü.
    */
    $cevap->assertSee('Mesafeli Satış Sözleşmesi')
        ->assertDontSee('KVKK Aydınlatma Metni');
});

it('★★ MARKA metne HTML gomemiyor', function () {
    yasalliMagaza();

    $belgeler = app(LegalDocumentService::class);
    $belgeler->taslagaYaz(LegalDocumentType::Returns, '<script>alert(1)</script> iade kosullari');
    $belgeler->yayinla(LegalDocumentType::Returns);

    /*
    | ★ 4-K5'in aynısı: metni MARKA yazıyor. Ham HTML olarak basılsaydı
    | marka kendi vitrinine betik gömebilirdi.
    */
    $this->get('http://marka-a.test/yasal/returns')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertSee('iade kosullari');
});

it('★ SURUM ve TARIH sayfada gorunuyor', function () {
    yasalliMagaza();

    /*
    | ⚠️ Müşteri hangi metni okuduğunu, marka hangi metnin yürürlükte
    | olduğunu tartışmasız bilmeli (1A.4 · 1D-K2).
    */
    $this->get('http://marka-a.test/yasal/distance_sales')
        ->assertOk()
        ->assertSee('Sürüm 1');
});

it('★ her sayfadan yasal metinlere baglanti var', function () {
    yasalliMagaza();

    $this->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee('Yasal metinler')
        ->assertSee('/yasal', escape: false);
});

it('★★ MAGAZA KAPALIYKEN de yasal metin OKUNABILIYOR', function () {
    markaKur('marka-a.test');
    magazayiHazirla();

    // Mağaza YAYINLANMADI — vitrin kapalı.
    $this->get('http://marka-a.test/')->assertStatus(503);

    /*
    | ★ Emsal 2G'de kuruldu: KVKK doğrulama bağlantısı da `magaza-acik`
    | kapısının dışında ve gerekçesi aynen "yasal bir hak, mağazanın açık
    | olmasına bağlanamaz".
    |
    | ⚠️ İlk hâli kapının içindeydi ve bu test ortaya çıkardı: yasal
    | metinlerini henüz tamamlamamış bir marka, yayınladığı metni bile
    | gösteremiyordu.
    */
    $this->get('http://marka-a.test/yasal/distance_sales')
        ->assertOk()
        ->assertSee('Mesafeli Satış Sözleşmesi');
});
