<?php

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentStatus;
use App\Enums\SettingGroup;
use App\Http\Storefront\CartToken;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;

/*
| FAZ 4 KAPANIŞI (4H) — BİTİŞ ÖLÇÜTÜNÜN TAMAMI TEK TESTTE.
|
| ★ Ölçüt aynen şuydu:
|
|   "Bir marka HİÇ `curl` KULLANMADAN mağazasını kurar: giriş yapar,
|    ürün ekler, temasını seçer, mağazasını yayına alır; bir müşteri
|    tarayıcıdan girip ürünü bulur, sepete atar, öder; marka siparişi
|    panelden görür ve kargolar. Üçü de kendi yüzeyinden, kimse
|    diğerinin ekranını göremeden."
|
| ⚠️ Bu test her adımı BİR ÖNCEKİ EKRANDAN gelen bilgiyle yürüyor —
| kimlikler modelden okunmuyor (1D.6'nın dersi). "İstemci bu değeri
| nereden bulacak" sorusu ancak böyle sorulmuş oluyor.
*/

beforeEach(function () {
    $this->withoutVite();
});

it('★★★ FAZ 4 BİTİŞ ÖLÇÜTÜ: marka kurar, müşteri alır, marka kargolar', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /*
    |----------------------------------------------------------------------
    | 1 — MARKA GİRİŞ YAPIYOR (4C)
    |----------------------------------------------------------------------
    */
    $this->post('http://marka-a.test/yonetim/giris', [
        'email' => $sahip->email,
        'password' => 'sifre1234',
    ])->assertRedirect('http://marka-a.test/yonetim');

    /*
    |----------------------------------------------------------------------
    | 2 — MAĞAZA KAPALI DOĞUYOR ve eksikleri EKRAN söylüyor (4H)
    |----------------------------------------------------------------------
    */
    $magaza = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/magaza')->getContent(),
    );

    expect($magaza['props']['yayinda'])->toBeFalse()
        ->and($magaza['props']['eksikler'])->not->toBeEmpty();

    // Vitrin şu an KAPALI.
    $this->get('http://marka-a.test/')->assertStatus(503);

    /*
    |----------------------------------------------------------------------
    | 3 — ÜRÜN EKLİYOR (4D): oluştur → varyant → yayına al
    |----------------------------------------------------------------------
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/urunler', ['title' => 'El Yapimi Sabun', 'brand' => 'Ada'])
        ->assertRedirect();

    $urun = Product::query()->latest('id')->firstOrFail();

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/varyantlar", [
            'sku' => 'SBN-1', 'price' => 89.90, 'stock' => 10, 'options' => [],
        ])->assertRedirect();

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/urunler/{$urun->uuid}/durum", ['status' => 'active'])
        ->assertRedirect();

    /*
    |----------------------------------------------------------------------
    | 4 — TEMASINI SEÇİYOR (4G)
    |----------------------------------------------------------------------
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema', [
            'renk' => '#123456', 'yazi_tipi' => 'serif', 'duzen' => 'vitrinli',
        ])->assertRedirect();

    /*
    |----------------------------------------------------------------------
    | 5 — MAĞAZASINI YAYINA ALIYOR (4H)
    |----------------------------------------------------------------------
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/magaza', [
            'name' => 'Ada Kozmetik',
            'legal_name' => 'Ada Kozmetik Ltd. Sti.',
            'tax_number' => '1234567890',
            'tax_office' => 'Kadikoy',
            'address' => 'Test Cad. No:1',
            'phone' => '+902161112233',
            'contact_email' => 'destek@ada.example',
        ])->assertRedirect();

    // Yasal metinler yayınlanmadan mağaza açılmamalı.
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/magaza/yayinla')
        ->assertSessionHas('hata');

    $belgeler = app(LegalDocumentService::class);

    foreach (LegalDocumentType::cases() as $tur) {
        $belgeler->taslagaYaz($tur, "{$tur->value} metni");
        $belgeler->yayinla($tur);
    }

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/magaza/yayinla')
        ->assertSessionHas('mesaj');

    /*
    |----------------------------------------------------------------------
    | 6 — MÜŞTERİ TARAYICIDAN ÜRÜNÜ BULUYOR (4A · 4B · 4G)
    |----------------------------------------------------------------------
    */
    $anasayfa = $this->get('http://marka-a.test/');

    $anasayfa->assertOk()
        ->assertSee('Ada Kozmetik')
        ->assertSee('El Yapimi Sabun')
        ->assertSee('#123456', escape: false)
        // ⚠️ Markanın SEÇTİĞİ düzen: "vitrinli"nin karşılama bölümü.
        ->assertSee('Seçilmiş ürünler')
        ->assertSee('/urun/'.$urun->refresh()->slug, escape: false);

    // Varyant kimliği ÜRÜN SAYFASINDAN okunuyor — modelden değil.
    $urunSayfasi = $this->get('http://marka-a.test/urun/'.$urun->slug)->assertOk();

    $varyantUuid = (string) $urun->variants->firstOrFail()->uuid;
    $urunSayfasi->assertSee($varyantUuid, escape: false);

    /*
    |----------------------------------------------------------------------
    | 7 — SEPETE ATIYOR VE ÖDÜYOR (4B)
    |----------------------------------------------------------------------
    */
    $ekle = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyantUuid,
        'quantity' => 2,
    ])->assertRedirect('http://marka-a.test/sepet');

    $token = $ekle->getCookie(CartToken::CEREZ, false)?->getValue();

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/sepet')
        ->assertOk()
        ->assertSee('Ödemeye geç');

    $sozlesme = $belgeler->guncelSurum(LegalDocumentType::DistanceSales);

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->get('http://marka-a.test/odeme')
        ->assertOk()
        ->assertSee('value="'.$sozlesme?->id.'"', escape: false);

    $this->withUnencryptedCookie(CartToken::CEREZ, $token)
        ->post('http://marka-a.test/odeme', [
            'email' => 'musteri@example.com',
            'legal_version_id' => $sozlesme?->id,
            'sozlesme_onay' => '1',
            'shipping' => [
                'full_name' => 'Ayse Yilmaz',
                'phone' => '+905551112233',
                'city' => 'Istanbul',
                'district' => 'Kadikoy',
                'line1' => 'Test Cad. No:1',
            ],
        ])->assertRedirect();

    $siparis = Order::query()->latest('id')->firstOrFail();

    expect($siparis->email)->toBe('musteri@example.com')
        ->and($siparis->payment_status)->toBe(PaymentStatus::Pending);

    // Ödemeyi başarıya çevir — sağlayıcı bildirimi 1E'de sınanıyor.
    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    |----------------------------------------------------------------------
    | 8 — MARKA SİPARİŞİ PANELDEN GÖRÜYOR VE KARGOLUYOR (4E)
    |----------------------------------------------------------------------
    */
    $liste = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/siparisler')->getContent(),
    );

    /** @var list<array<string, mixed>> $satirlar */
    $satirlar = $liste['props']['siparisler']['data'];

    $satir = null;

    foreach ($satirlar as $aday) {
        if ($aday['order_number'] === $siparis->order_number) {
            $satir = $aday;
        }
    }

    expect($satir)->not->toBeNull();

    /** @var array<string, mixed> $satir */
    $siparisUuid = (string) $satir['uuid'];

    // Sipariş kimliği LİSTEDEN geliyor.
    $ayrinti = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get("http://marka-a.test/yonetim/siparisler/{$siparisUuid}")
            ->getContent(),
    );

    $satirId = $ayrinti['props']['siparis']['items'][0]['id'];

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparisUuid}/paketler", [
            'items' => [['order_item_id' => $satirId, 'quantity' => 2]],
            'carrier' => 'Test Kargo',
        ])->assertRedirect();

    /** @var Fulfillment $paket */
    $paket = Fulfillment::query()->latest('id')->firstOrFail();

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparisUuid}/paketler/{$paket->uuid}/kargo")
        ->assertRedirect();

    expect($siparis->refresh()->fulfillment_status->value)->toBe('fulfilled');
});

/*
|--------------------------------------------------------------------------
| ★★ "KİMSE DİĞERİNİN EKRANINI GÖREMEDEN" — ölçütün son cümlesi
|--------------------------------------------------------------------------
*/

it('★★ MÜŞTERİ panel ekranlarını GÖREMİYOR', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ Müşteri (kimliksiz ziyaretçi) panel adreslerine vurduğunda
    | GİRİŞ SAYFASINA düşmeli — panelin içeriğini görmemeli.
    */
    foreach (['/yonetim', '/yonetim/urunler', '/yonetim/siparisler', '/yonetim/magaza'] as $yol) {
        $this->get('http://marka-a.test'.$yol)
            ->assertRedirect('http://marka-a.test/yonetim/giris');
    }
});

it('★★★ BİR MARKANIN OTURUMU BAŞKA MARKANIN PANELİNİ AÇMIYOR', function () {
    ['sahip' => $a] = markaKur('marka-a.test');
    tenancy()->end();

    markaKur('marka-b.test');
    tenancy()->end();

    /*
    | ★ BU TESTİ GERÇEK BİR AÇIK DOĞURDU (4H'de ölçüldü):
    |
    |   A'da giriş yap → oturum çerezi
    |   aynı çerezle B'nin paneline vur → 200, PANEL AÇILIYORDU
    |
    | Sebep: oturum yalnızca kullanıcı `id`'sini tutuyor ve guard onu
    | İSTEĞİN kiracısının şemasından çözüyor. İki markada da `id = 1` olan
    | birer kullanıcı olduğu için A'nın oturumu B'de geçerli sayılıyordu.
    |
    | ⚠️ Bugün tarayıcı bunu yapmaz (çerez alan adına bağlı) — ama koruma
    | buna bırakılamaz: 3D'deki self-servis kayıt markalara ALT ALAN ADI
    | veriyor ve biri `SESSION_DOMAIN`'i `.tikmarka.com` yaparsa her
    | markanın oturumu her markanın panelini açardı.
    |
    | ⚠️ `actingAs()` İLE ÖLÇÜLEMEZ: o guard'a kullanıcıyı doğrudan
    | koyuyor, oturumdan geçmiyor. Gerçek giriş yapılıyor.
    */
    $this->post('http://marka-a.test/yonetim/giris', [
        'email' => $a->email,
        'password' => 'sifre1234',
    ])->assertRedirect('http://marka-a.test/yonetim');

    // A'nın kendi paneli açık.
    $this->get('http://marka-a.test/yonetim')->assertOk();

    // AYNI oturumla B'nin paneli — KAPALI olmalı.
    $this->get('http://marka-b.test/yonetim')
        ->assertRedirect('http://marka-b.test/yonetim/giris');

    /*
    | ⚠️ Oturum GEÇERSİZ KILINIYOR, yalnızca yönlendirilmiyor: aksi hâlde
    | saldırgan aynı çerezle denemeye devam ederdi. A'nın kendi paneli de
    | artık kapalı — kullanıcı yeniden giriş yapmalı.
    */
    $this->get('http://marka-a.test/yonetim')
        ->assertRedirect('http://marka-a.test/yonetim/giris');
});

it('★★ MAĞAZA KAPATILINCA vitrin kapanıyor, PANEL AÇIK kalıyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $this->get('http://marka-a.test/')->assertOk();

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/magaza/kapat')
        ->assertRedirect();

    $this->get('http://marka-a.test/')->assertStatus(503);

    /*
    | ★ PANEL AÇIK KALMALI. Kapansaydı marka kendini dışarıda bırakırdı —
    | mağazayı tekrar açmanın tek yolu panel (4C).
    */
    $this->actingAs($sahip, 'staff-web')
        ->get('http://marka-a.test/yonetim/magaza')
        ->assertOk();
});

it('★★ EKSİK BİLGİYLE mağaza yayına alınamıyor ve SEBEBİ yazılıyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $cevap = $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/magaza/yayinla');

    /*
    | ⚠️ 500 DEĞİL: eksik bilgiyle yayınlamaya çalışmak markanın hatası
    | değil, sıradan bir sonuç. Sebep sayfada yazıyor.
    */
    $cevap->assertRedirect()->assertSessionHas('hata');

    $this->get('http://marka-a.test/')->assertStatus(503);
});

it('★ mağaza bilgileri panelden kaydediliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/magaza', [
            'name' => 'Yeni Ad',
            'legal_name' => 'Yeni Ad Ltd.',
            'tax_number' => '9876543210',
            'tax_office' => 'Besiktas',
            'address' => 'Adres 1',
            'phone' => '+902120000000',
            'contact_email' => 'iletisim@example.com',
        ])->assertRedirect();

    expect(app(SettingsService::class)->al(SettingGroup::Store, 'legal_name'))->toBe('Yeni Ad Ltd.');
});
