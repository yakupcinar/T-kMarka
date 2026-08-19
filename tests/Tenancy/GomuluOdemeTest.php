<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Payment\FakePaymentProvider;
use App\Domain\Payment\PaymentInitiation;
use App\Domain\Payment\PaymentProviderException;
use App\Domain\Payment\PaymentService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\ProductStatus;
use App\Http\Storefront\CartToken;
use App\Models\Customer;
use App\Models\LegalDocumentVersion;
use App\Models\Order;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Component\HttpFoundation\Response;

/*
| GÖMÜLÜ ÖDEME — kart formu IFRAME içinde. (4.5-K1)
|
| ★ KARAR: müşteri siteden AYRILMIYOR ama kart verisi bize HİÇ UĞRAMIYOR.
| Formun içeriği tamamen sağlayıcının kökeninde; bizim sayfamız yalnızca
| çerçeveyi çiziyor.
|
| ⚠️ Sağlayıcının hazır BETİĞİ (`checkoutFormContent`) kullanılmıyor: o
| betik sağlayıcının JavaScript'ini BİZİM kökenimizde çalıştırırdı.
*/

/**
 * Ödemeye hazır sepet + sözleşme sürümü.
 *
 * @return array{token: string, sozlesme: LegalDocumentVersion}
 */
function odemeyeHazirSepet(PHPUnitTestCase $test, string $alanAdi = 'marka-a.test'): array
{
    markaKur($alanAdi);
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Sabun']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'S-1', 'price' => 50, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $ekle = $test->post("http://{$alanAdi}/sepet/ekle", ['variant_uuid' => $varyant->uuid]);

    $token = $ekle->getCookie(CartToken::CEREZ, false)?->getValue();

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    /*
     | ⚠️ İkisi de `null` olamaz: kurulum bozulmuşsa test SUSMAMALI.
     */
    expect($token)->toBeString()->and($sozlesme)->toBeInstanceOf(LegalDocumentVersion::class);

    /** @var string $token */
    /** @var LegalDocumentVersion $sozlesme */
    return ['token' => $token, 'sozlesme' => $sozlesme];
}

/**
 * ⚠️ Test örneği AÇIKÇA geçiriliyor, `test()` çağrılmıyor.
 *
 * `phpstan.neon`'daki Pest istisnası BİLEREK yalnızca `tests/Pest.php`'yi
 * kapsıyor ("gerçek yazım hataları diğer test dosyalarında yakalanmaya
 * devam ediyor"). İstisnayı genişletmek yerine bağımlılık görünür kılındı.
 *
 * @return TestResponse<Response>
 */
function siparisVer(PHPUnitTestCase $test, string $token, int $sozlesmeId, string $alanAdi = 'marka-a.test'): TestResponse
{
    return $test->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post("http://{$alanAdi}/odeme", [
            'email' => 'musteri@example.com',
            'legal_version_id' => $sozlesmeId,
            'sozlesme_onay' => '1',
            'shipping' => [
                'full_name' => 'Ayse Yilmaz', 'phone' => '+905551112233',
                'city' => 'Istanbul', 'district' => 'Kadikoy', 'line1' => 'Test Cad. No:1',
            ],
        ]);
}

it('★★ SIPARIS SONRASI siteden AYRILMIYORUZ — gomulu odeme adimina gidiliyor', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    $cevap = siparisVer($this, $token, $sozlesme->id);

    $siparis = Order::query()->latest('id')->firstOrFail();

    /*
    | ★ ESKİ DAVRANIŞ: `redirect()->away($saglayiciAdresi)` — müşteri
    | siteden çıkıyordu. Artık kendi ödeme adımımıza gidiyoruz.
    |
    | ⚠️ Eski testler bunu YAKALAYAMAZDI: `assertRedirect()` hedefsiz
    | çağrılıyordu, yani "bir yere yönlendirildi" ölçülüyordu — NEREYE
    | değil. 4.5A'daki dersin aynısı.
    */
    $cevap->assertRedirect("http://marka-a.test/odeme/ode/{$siparis->uuid}");
});

it('★★ ODEME ADIMI kart formunu IFRAME icinde gosteriyor', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    siparisVer($this, $token, $sozlesme->id);
    $siparis = Order::query()->latest('id')->firstOrFail();

    $sayfa = $this->get("http://marka-a.test/odeme/ode/{$siparis->uuid}");

    $sayfa->assertOk()
        ->assertSee('<iframe', escape: false)
        ->assertSee('iframe=true', escape: false)
        ->assertSee($siparis->order_number);

    /*
    | ⚠️ Müşteriye çerçevenin KİME ait olduğu yazılıyor: bilmediği bir
    | forma kart girmemesi doğru davranış.
    */
    $sayfa->assertSee('sunucularına gönderilmez');
});

it('★★ SAGLAYICI BETIGI sayfaya GOMULMUYOR — yalnizca ADRES', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    siparisVer($this, $token, $sozlesme->id);
    $siparis = Order::query()->latest('id')->firstOrFail();

    $icerik = $this->get("http://marka-a.test/odeme/ode/{$siparis->uuid}")->getContent();

    /*
    | ★ 4.5-K1'İN ÇEKİRDEĞİ. iyzico `checkoutFormContent` adında hazır bir
    | `<script>` bloğu da veriyor ve onu sayfaya yapıştırmak daha kolaydı.
    | Seçilmedi: o betik sağlayıcının JavaScript'ini BİZİM kökenimizde
    | çalıştırır, yani kart alanları bizim sayfamızın DOM'unda olurdu.
    | Adres gömülünce her şey onların kökeninde kalıyor.
    */
    expect($icerik)->not->toContain('iyzipay-checkout-form')
        ->and($icerik)->not->toContain('checkoutFormContent');
});

it('★★ BASKASININ siparisinin odeme ekrani ACILMIYOR', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    siparisVer($this, $token, $sozlesme->id);
    $siparis = Order::query()->latest('id')->firstOrFail();

    // Müşteri olarak giriş yapmış biri, MİSAFİR siparişini açamamalı.
    $musteri = Customer::create([
        'name' => 'Baskasi', 'email' => 'baskasi@example.com', 'password' => 'sifre1234',
    ]);

    /*
    | ⚠️ 404, 403 DEĞİL: "böyle bir sipariş var ama senin değil" bilgisi
    | de sızıntıdır (1A.5). Kural 1E'de kurulmuştu, burada tekrarlanmıyor.
    */
    $this->actingAs($musteri, 'customer')
        ->get("http://marka-a.test/odeme/ode/{$siparis->uuid}")
        ->assertNotFound();
});

it('★★ ODENMIS siparise odeme ekrani ACILMIYOR', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    siparisVer($this, $token, $sozlesme->id);
    $siparis = Order::query()->latest('id')->firstOrFail();

    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    | ⚠️ Müşteri geri düğmesine bastığında İKİNCİ KEZ ödemeye
    | çalışabilirdi.
    */
    $this->get("http://marka-a.test/odeme/ode/{$siparis->refresh()->uuid}")
        ->assertRedirect('http://marka-a.test');
});

it('★★ DONUS SAYFASI iframe den CIKIYOR', function () {
    ['referans' => $referans] = bildirimeHazirSiparis('marka-a.test');

    /*
    | ★ Bu betik olmasaydı müşteri "Siparişiniz alındı" ekranını ödeme
    | formunun yerinde, KÜÇÜK BİR ÇERÇEVENİN İÇİNDE görürdü — üst bar ve
    | menü hâlâ ödeme sayfasına ait olurdu.
    */
    $cevap = $this->get('http://marka-a.test/odeme/donus?ref='.$referans);

    /*
    | ⚠️ İDDİA ASIL SATIRA BAĞLI. Önce yalnızca `window.top` aranıyordu ve
    | KIRMA DENEMESİ testin yalanını gösterdi: yönlendirme satırı silinse
    | bile `if (window.top !== window.self)` koşulu metinde kalıyor ve
    | test yeşil geçiyordu.
    */
    $cevap->assertOk()
        ->assertSee('window.top.location.href', escape: false)
        // ⚠️ `window.parent` iç içe çerçevede yalnızca BİR seviye çıkardı.
        ->assertDontSee('window.parent', escape: false)
        // Betik çalışmazsa elle çıkış için.
        ->assertSee('target="_top"', escape: false);
});

it('★★ GOMMEYI DESTEKLEMEYEN saglayicida YONLENDIRME calisiyor', function () {
    /*
    | ⚠️ Tek yol dayatılsaydı, iframe vermeyen bir sağlayıcıya geçildiği
    | gün ödeme TAMAMEN kırılırdı. Değer nesnesi bu yüzden "gömülebilir mi"
    | sorusunu ayrıca cevaplıyor.
    */
    $gomusuz = new PaymentInitiation(
        yonlendirmeAdresi: 'https://saglayici.example/ode',
        saglayiciReferansi: 'REF-1',
    );

    $gomulu = new PaymentInitiation(
        yonlendirmeAdresi: 'https://saglayici.example/ode',
        saglayiciReferansi: 'REF-2',
        gomuluAdres: 'https://saglayici.example/ode?iframe=true',
    );

    expect($gomusuz->gomulebilirMi())->toBeFalse()
        ->and($gomulu->gomulebilirMi())->toBeTrue();
});

it('★ SAHTE saglayici da gomulebilir — gelistirmede iframe yolu olculuyor', function () {
    markaKur('marka-a.test');

    /*
    | ⚠️ Olmasaydı geliştirme ve testler yalnızca yönlendirme yolunu
    | ölçer, iframe yolu ancak CANLIDA ilk kez denenirdi.
    */
    expect(app(FakePaymentProvider::class))->toBeInstanceOf(FakePaymentProvider::class);

    ['referans' => $referans] = bildirimeHazirSiparis('marka-b.test');

    expect($referans)->toStartWith('FAKE-');
});

/*
|--------------------------------------------------------------------------
| ★★ 4.5G — GERÇEK KULLANIMDA BULUNAN İKİ HATA
|--------------------------------------------------------------------------
*/

it('★★ DOGRULAMAMIZ SAGLAYICIDAN GEVSEK OLAMAZ — a@a reddediliyor', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    /*
    | ★ GERÇEK KULLANIMDA BULUNDU. Laravel'in `email` kuralı `a@a`'yı
    | kabul ediyor, iyzico reddediyor:
    |
    |     [iyzico] email hatalı format ile gönderilmiştir
    |
    | ⚠️ Bedeli sadece çirkin bir hata değil, ZAMANLAMASI: doğrulama
    | geçtiği için SİPARİŞ OLUŞUYOR, stok bağlanıyor ve ödeme ondan sonra
    | patlıyordu — bağlı stok 60 dakika kimseye satılamıyordu.
    */
    foreach (['a@a', 'a@aa', 'musteri@localhost'] as $gecersiz) {
        $cevap = $this->withUnencryptedCookie(CartToken::CEREZ, $token)
            ->post('http://marka-a.test/odeme', [
                'email' => $gecersiz,
                'legal_version_id' => $sozlesme->id,
                'sozlesme_onay' => '1',
                'shipping' => [
                    'full_name' => 'Ayse Yilmaz', 'phone' => '+905551112233',
                    'city' => 'Istanbul', 'district' => 'Kadikoy', 'line1' => 'Test Cad. No:1',
                ],
            ]);

        $cevap->assertSessionHasErrors('email');
    }

    // ⚠️ ASIL ÖLÇÜM: hiçbir sipariş OLUŞMAMALI.
    expect(Order::count())->toBe(0);
});

it('★ GECERLI alan adi kabul ediliyor', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    siparisVer($this, $token, $sozlesme->id)->assertRedirect();

    expect(Order::count())->toBe(1);
});

it('★★ ODEME HATASI tarayiciya HTML donuyor, JSON degil', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    siparisVer($this, $token, $sozlesme->id);
    $siparis = Order::query()->latest('id')->firstOrFail();

    /*
    | Sağlayıcı reddediyormuş gibi davranalım.
    |
    | ⚠️ ÜÇÜNCÜ KEZ AYNI HATA: 4A'da kapalı mağaza, 4B'de ödeme dönüşü
    | için düzeltilmişti; bu uç gözden kaçmıştı. Müşteri ödeme sayfasında
    | HAM JSON görüyordu.
    */
    $this->mock(PaymentService::class, function ($sahte) {
        $sahte->shouldReceive('baslat')
            ->andThrow(new PaymentProviderException('fake', 'sağlayıcı reddetti', []));
    });

    $cevap = $this->get("http://marka-a.test/odeme/ode/{$siparis->uuid}");

    $cevap->assertRedirect();
    $cevap->assertSessionHas('hata');

    // ⚠️ Sağlayıcının kendi mesajı MÜŞTERİYE GİTMİYOR (yapılandırma sızar).
    expect(session('hata'))->not->toContain('sağlayıcı reddetti');
});

it('★ ODEME HATASI API istemcisine hala JSON donuyor', function () {
    ['token' => $token, 'sozlesme' => $sozlesme] = odemeyeHazirSepet($this);

    siparisVer($this, $token, $sozlesme->id);
    $siparis = Order::query()->latest('id')->firstOrFail();

    $this->mock(PaymentService::class, function ($sahte) {
        $sahte->shouldReceive('baslat')
            ->andThrow(new PaymentProviderException('fake', 'sağlayıcı reddetti', []));
    });

    $this->postJson("http://marka-a.test/api/orders/{$siparis->uuid}/pay")
        ->assertStatus(502)
        ->assertJsonStructure(['message']);
});
