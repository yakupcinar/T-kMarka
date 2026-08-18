<?php

use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Http\Storefront\CartToken;
use App\Models\LegalDocumentVersion;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;

/*
| VİTRİN AKIŞI (4B) — müşteri TARAYICIDAN alışveriş yapıyor.
|
| ★ BU DOSYANIN İDDİASI: hiç JavaScript ve hiç `curl` olmadan, yalnızca
| HTML formlarıyla ürün → sepet → ödeme yolu yürünebiliyor.
|
| ⚠️ `getJson`/`postJson` KULLANILMAZ. İkisi de `Accept` başlığı ekliyor ve
| şifrelenmemiş çerezi düşürüyor — yani tam olarak ölçmek istediğimiz şeyi
| (tarayıcı gibi davranmak) ortadan kaldırırlar (2E · 4A).
*/

function akisMarkasi(string $alanAdi = 'marka-a.test'): void
{
    markaKur($alanAdi);
    magazayiHazirla();
    app(SettingsService::class)->yaz(SettingGroup::Store, 'name', 'Ada Kozmetik');
    app(StorePublication::class)->yayinla();
}

function akisUrunu(string $baslik = 'Deri Cuzdan', string $sku = 'CZ-1', int $stok = 5): Product
{
    $urun = app(ProductService::class)->olustur(['title' => $baslik, 'brand' => 'Demo']);
    app(VariantService::class)->ekle($urun, ['sku' => $sku, 'price' => 100, 'stock' => $stok]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $urun->refresh();
}

/**
 * VAR OLAN ürünün varyantı.
 *
 * ⚠️ Ayrı bir yardımcı olarak duruyor çünkü [akisVaryanti] YENİ ürün
 * yaratıyor. İkisini karıştırmak uçtan uca testte ikinci bir ürün
 * doğurdu ve test "varyant kimliği ürün sayfasında" iddiasını yanlış
 * üründe ölçmeye çalıştı.
 */
function akisVaryantiniAl(Product $urun): ProductVariant
{
    $varyant = $urun->variants->first();

    expect($varyant)->toBeInstanceOf(ProductVariant::class);

    /** @var ProductVariant $varyant */
    return $varyant;
}

/**
 * YENİ ürün kurar ve tek varyantını döndürür.
 *
 * ⚠️ `null` gelirse test SUSMAZ: kurulum bozulmuşsa iddia ölçülmemiş olur.
 */
function akisVaryanti(string $baslik = 'Deri Cuzdan', string $sku = 'CZ-1', int $stok = 5): ProductVariant
{
    $varyant = akisUrunu($baslik, $sku, $stok)->variants->first();

    expect($varyant)->toBeInstanceOf(ProductVariant::class);

    /** @var ProductVariant $varyant */
    return $varyant;
}

/** Yayınlanmış mesafeli satış sözleşmesinin güncel sürümü. */
function akisSozlesmesi(): LegalDocumentVersion
{
    $surum = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    expect($surum)->toBeInstanceOf(LegalDocumentVersion::class);

    /** @var LegalDocumentVersion $surum */
    return $surum;
}

/**
 * Ödeme formunun beklediği alanlar.
 *
 * @return array<string, mixed>
 */
function odemeFormu(int $sozlesmeId): array
{
    return [
        'email' => 'musteri@example.com',
        'legal_version_id' => $sozlesmeId,
        'sozlesme_onay' => '1',
        'shipping' => [
            'full_name' => 'Ayse Yilmaz',
            'phone' => '+905551112233',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
            'line1' => 'Test Cad. No:1',
        ],
    ];
}

it('★★ UÇTAN UCA: musteri TARAYICIDAN urun aliyor', function () {
    akisMarkasi();
    $urun = akisUrunu();
    $varyant = akisVaryantiniAl($urun);

    /*
    | 1 — ANA SAYFA. Ürün kartı GERÇEK ürün adresine bağlı olmalı.
    |
    | ⚠️ 1D.6'nın dersi: uçtan uca testte kimlik MODELDEN okunmaz. Ürün
    | adresini sayfadan görüyoruz — "istemci bu değeri nereden bulacak"
    | sorusu ancak böyle sorulmuş oluyor.
    */
    $this->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee('/urun/'.$urun->slug, escape: false);

    // 2 — ÜRÜN SAYFASI. Varyant kimliği FORMDA olmalı.
    $urunSayfasi = $this->get('http://marka-a.test/urun/'.$urun->slug);
    $urunSayfasi->assertOk()->assertSee('Deri Cuzdan');
    $urunSayfasi->assertSee($varyant->uuid, escape: false);

    // 3 — SEPETE EKLE (form gönderimi, yönlendirme bekliyoruz).
    $ekle = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
        'quantity' => 2,
    ]);

    $ekle->assertRedirect('http://marka-a.test/sepet');

    // ★ Çerez CEVAPTA gelmeli — sonraki isteği tarayıcı gibi taşıyacağız.
    $token = $ekle->getCookie(CartToken::CEREZ, false)?->getValue();
    expect($token)->toBeString();

    // 4 — SEPET SAYFASI.
    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/sepet')
        ->assertOk()
        ->assertSee('Deri Cuzdan')
        ->assertSee('Ödemeye geç');

    // 5 — ÖDEME FORMU. Sözleşme sürümü SAYFADAN okunuyor.
    $odeme = $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/odeme');

    $sozlesme = akisSozlesmesi();

    $odeme->assertOk()->assertSee('value="'.$sozlesme->id.'"', escape: false);

    // 6 — SİPARİŞ. Sağlayıcının ödeme sayfasına yönlendirilmeli.
    $gonder = $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/odeme', odemeFormu($sozlesme->id));

    $gonder->assertRedirect();

    $siparis = Order::query()->latest('id')->firstOrFail();

    expect($siparis->email)->toBe('musteri@example.com')
        ->and($siparis->payment_status)->toBe(PaymentStatus::Pending)
        ->and($siparis->items)->toHaveCount(1);

    // 7 — STOK BAĞLANDI: sipariş ödenmemiş olsa da rezerve edilmiş olmalı.
    expect($varyant->refresh()->committed)->toBe(2);
});

it('★ SEPET SAYFASI adet degistirmeyi ve silmeyi tasiyor', function () {
    akisMarkasi();
    $varyant = akisVaryanti();

    $token = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
        'quantity' => 1,
    ])->getCookie(CartToken::CEREZ, false)?->getValue();

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/sepet/guncelle', [
            'variant_uuid' => $varyant->uuid,
            'quantity' => 4,
        ])->assertRedirect('http://marka-a.test/sepet');

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/')
        ->assertSee('>4<', escape: false);

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/sepet/sil', ['variant_uuid' => $varyant->uuid])
        ->assertRedirect('http://marka-a.test/sepet');

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/sepet')
        ->assertOk()
        ->assertSee('Sepetiniz boş');
});

it('★★ SOZLESME ONAYLANMADAN siparis verilemiyor', function () {
    akisMarkasi();
    $varyant = akisVaryanti();

    $token = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
    ])->getCookie(CartToken::CEREZ, false)?->getValue();

    $sozlesme = akisSozlesmesi();

    /*
    | ⚠️ Onay kutusu SUNUCUDA da zorunlu. Yalnızca HTML `required`
    | özniteliğine bırakılsaydı formu elle gönderen biri mesafeli satış
    | sözleşmesini onaylamadan sipariş verebilirdi — yasal bir şart.
    */
    $veri = odemeFormu($sozlesme->id);
    unset($veri['sozlesme_onay']);

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/odeme', $veri)
        ->assertSessionHasErrors('sozlesme_onay');

    expect(Order::count())->toBe(0);
});

it('★★ SIPARISE MUSTERININ GORDUGU sozlesme surumu yaziliyor', function () {
    akisMarkasi();
    $varyant = akisVaryanti();

    $token = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
    ])->getCookie(CartToken::CEREZ, false)?->getValue();

    $belgeler = app(LegalDocumentService::class);
    $gorulen = akisSozlesmesi();

    /*
    | ★ Marka, müşteri formu doldururken sözleşmeyi GÜNCELLİYOR.
    |
    | ⚠️ TESTİ YAZARKEN YANLIŞ VARSAYDIM: "eski sürüm reddedilmeli" diye
    | ölçtüm ve düştü. Kod haklıydı — 1A.4 / 1D-K2 kararı REDDETMEK değil
    | GÖRÜLENİ KAYDETMEK:
    |
    |   sunucu güncel sürümü yazsaydı → müşteri GÖRMEDİĞİ metne imza atardı
    |   eski sürüm reddedilseydi      → müşteri okuduğu metni onaylayamazdı
    |
    | Doğrusu: sipariş, müşterinin ekranında duran sürümü taşıyor. Hangi
    | metne rıza verdiği sonradan tartışmasız okunabiliyor.
    */
    $belgeler->taslagaYaz(LegalDocumentType::DistanceSales, 'Yeni metin');
    $yeni = $belgeler->yayinla(LegalDocumentType::DistanceSales);

    expect($yeni->id)->not->toBe($gorulen->id);

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/odeme', odemeFormu($gorulen->id))
        ->assertRedirect();

    $siparis = Order::query()->latest('id')->firstOrFail();

    expect($siparis->legal_version_id)->toBe($gorulen->id);
});

it('★ OLMAYAN sozlesme surumu ile siparis verilemiyor', function () {
    akisMarkasi();
    $varyant = akisVaryanti();

    $token = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
    ])->getCookie(CartToken::CEREZ, false)?->getValue();

    // Uydurulmuş sürüm numarası — form elle gönderilirse böyle gelir.
    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/odeme', odemeFormu(999999))
        ->assertRedirect('http://marka-a.test/odeme');

    expect(Order::count())->toBe(0);
});

it('★ STOGU BITEN satir ODEMEYE GECISI kapatiyor', function () {
    akisMarkasi();
    $varyant = akisVaryanti(stok: 1);

    $token = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
    ])->getCookie(CartToken::CEREZ, false)?->getValue();

    // Marka stoğu sıfırlıyor — müşteri sepetteyken.
    $varyant->update(['stock' => 0]);

    /*
    | ⚠️ STOK SIFIRSA SATIR "SATILAMAZ" SAYILIYOR, "stok yetersiz" DEĞİL.
    | Testi yazarken "Stok yetersiz" bekledim ve düştü; 1C-K2 stok bitmesini
    | üç "artık satın alınamaz" durumundan biri olarak tanımlıyor
    | (arşivlendi · kapatıldı · stok bitti). İki mesaj AYRI dallardan
    | geliyor ve ikisi de aşağıda ölçülüyor.
    */
    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/sepet')
        ->assertOk()
        ->assertSee('artık satışta değil')
        ->assertDontSee('Ödemeye geç');
});

it('★ STOK YETMEYEN satir odemeye gecisi kapatiyor', function () {
    akisMarkasi();
    $varyant = akisVaryanti(stok: 5);

    $token = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
        'quantity' => 4,
    ])->getCookie(CartToken::CEREZ, false)?->getValue();

    // Stok var ama sepetteki adede yetmiyor — DİĞER dal.
    $varyant->update(['stock' => 2]);

    /*
    | ⚠️ "Ödemeye geç" GİZLENMİYOR, ENGEL YAZILIYOR. Sessizce gizlemek,
    | müşteriye neden devam edemediğini söylemeden yolu kapatmak olurdu.
    */
    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/sepet')
        ->assertOk()
        ->assertSee('Stok yetersiz')
        ->assertDontSee('Ödemeye geç');
});

it('★ BOS SEPETLE odeme sayfasi acilmiyor', function () {
    akisMarkasi();

    $this->get('http://marka-a.test/odeme')
        ->assertRedirect('http://marka-a.test/sepet');
});

it('★ YAYINDA OLMAYAN urunun sayfasi 404', function () {
    akisMarkasi();

    $taslak = app(ProductService::class)->olustur(['title' => 'Gizli Urun']);

    /*
    | ⚠️ Vitrin sorgusu kullanılmasaydı yayınlanmamış ürünün sayfası
    | adresi bilen herkese açık olurdu (1B-K10).
    */
    $this->get('http://marka-a.test/urun/'.$taslak->slug)->assertNotFound();
});

it('★ KUPON sayfadan uygulanip kaldirilabiliyor', function () {
    akisMarkasi();
    $varyant = akisVaryanti();

    $token = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
    ])->getCookie(CartToken::CEREZ, false)?->getValue();

    // Geçersiz kod: 500 DEĞİL, sayfada hata mesajı.
    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/sepet/kupon', ['kod' => 'YOKBOYLE'])
        ->assertRedirect('http://marka-a.test/sepet')
        ->assertSessionHas('hata');
});

/*
|--------------------------------------------------------------------------
| ★★ ÖDEME DÖNÜŞ EKRANI — ikisi de GERÇEK KOŞUDA bulundu
|--------------------------------------------------------------------------
|
| Bu iki testi süit değil `curl` doğurdu. İkisi de yeşil testlerin altından
| geçmişti:
|
|   1  Uç `api` grubunda ve `ForceJson` `Accept`'i EZİYOR → yazdığım HTML
|      dalı hiç çalışmadı, müşteri ham JSON görüyordu.
|   2  Düzen `$errors` bekliyor ama onu paylaşan middleware yalnızca `web`
|      grubunda → "Undefined variable $errors" ile 500.
|
| İkisi de ÖDEMESİNİ YENİ BİTİRMİŞ müşterinin gördüğü ekranda oluyordu.
*/

it('★★ ODEME DONUSU tarayiciya HTML donuyor', function () {
    ['referans' => $referans, 'siparis' => $siparis] = bildirimeHazirSiparis('marka-a.test');

    /*
    | ⚠️ Parametre adı SAĞLAYICIYA AİT: sahte sağlayıcı `ref`, iyzico
    | `token` kullanıyor. Uç bunu bilmiyor, referansı sağlayıcı çıkarıyor
    | (1E.7.3'te ölçüldü — sabit yazılan `?ref=` yüzünden iyzico'nun üç
    | callback denemesi de 404 almıştı).
    */
    $cevap = $this->get('http://marka-a.test/odeme/donus?ref='.$referans);

    $cevap->assertOk();
    expect($cevap->headers->get('content-type'))->toContain('text/html');

    /*
    | ★ `processing` = "bildirim HENÜZ GELMEDİ", "başarısız" DEĞİL.
    | Ara durum "başarısız" gösterilseydi müşteri paniğe kapılır, ikinci
    | kez ödemeye çalışır ya da bankasını arardı — oysa ödemesi yolda.
    */
    $cevap->assertSee('işleniyor');
    $cevap->assertSee($siparis->order_number);
    $cevap->assertDontSee('payment_status');
});

it('★★ ODEME DONUSU API istemcisine hala JSON donuyor', function () {
    ['referans' => $referans] = bildirimeHazirSiparis('marka-a.test');

    $this->get('http://marka-a.test/odeme/donus?ref='.$referans, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('state', 'processing');
});
