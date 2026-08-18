<?php

use App\Enums\TenantStatus;
use App\Models\Customer;
use App\Platform\Models\PlatformUser;
use App\Platform\TenantDataExport;
use Illuminate\Support\Facades\Auth;

/*
| KONTROL DÜZLEMİ ARAYÜZÜ (4F) — TıkMarka'yı işletenin ekranı.
|
| ★ İKİ İDDİA:
|   1  İKİ PANEL BİRBİRİNE KARIŞMIYOR — ayrı guard, ayrı kök görünüm,
|      ayrı JS paketi. Marka sahibi kontrol düzlemine giremiyor.
|   2  MARKA VERİSİ DIŞA AKTARILABİLİYOR (Faz 3'ten devredilen borç).
*/

beforeEach(function () {
    $this->withoutVite();
});

function merkezKullanici(string $eposta = 'yonetici@tikmarka.test'): PlatformUser
{
    /*
    | ⚠️ `create()` DEĞİL `updateOrCreate()`. `tests/Tenancy` klasöründe
    | RefreshDatabase yok (şema oluşturmayı bozuyor) ve merkez tablolar
    | testler arasında kalıyor; `create()` ikinci testte "duplicate key"
    | ile patlıyor. 3F'de aynı ders çıkmıştı.
    */
    $kullanici = PlatformUser::updateOrCreate(
        ['email' => $eposta],
        ['name' => 'Yonetici', 'password' => 'sifre1234'],
    );

    // ⚠️ Önceki testte kapatılmış olabilir — her test temiz başlamalı.
    $kullanici->is_active = true;
    $kullanici->save();

    return $kullanici->refresh();
}

it('★ giris sayfasi Inertia sayfasi donuyor', function () {
    $cevap = $this->get('http://localhost/yonetim/giris')->assertOk();

    expect(inertiaVerisi($cevap->getContent())['component'])->toBe('Giris');
});

it('★★ KIMLIKSIZ pano GIRIS SAYFASINA yonlendiriyor', function () {
    $this->get('http://localhost/yonetim')
        ->assertRedirect('http://localhost/yonetim/giris');
});

it('★ OTURUMLA giris yapiliyor ve pano aciliyor', function () {
    $kullanici = merkezKullanici();

    $this->post('http://localhost/yonetim/giris', [
        'email' => $kullanici->email,
        'password' => 'sifre1234',
    ])->assertRedirect('http://localhost/yonetim');

    expect(Auth::guard('platform-web')->check())->toBeTrue();

    $sayfa = inertiaVerisi($this->get('http://localhost/yonetim')->getContent());

    expect($sayfa['component'])->toBe('Pano');
});

it('★ KAPALI hesap giris yapamiyor ve AYNI mesaji aliyor', function () {
    $kullanici = merkezKullanici();
    $kullanici->is_active = false;
    $kullanici->save();

    /*
    | ⚠️ "Hesabınız kapalı" demek, o e-postanın bir zamanlar yönetici
    | olduğunu doğrulardı (1A.2). Üç durum tek mesaj.
    */
    $this->post('http://localhost/yonetim/giris', [
        'email' => $kullanici->email,
        'password' => 'sifre1234',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('platform-web')->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ★★ İKİ PANEL BİRBİRİNE KARIŞMIYOR
|--------------------------------------------------------------------------
*/

it('★★ MARKA SAHIBI kontrol duzlemine GIREMIYOR', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    tenancy()->end();

    /*
    | ★ FAZIN EN ÖNEMLİ TESTİ. `staff-web` ile giriş yapmış bir marka
    | sahibi merkez `/yonetim`'e vurduğunda içeri ALINMAMALI.
    |
    | ⚠️ Tek guard kullanılsaydı bir markanın sahibi BÜTÜN MARKALARA
    | uzanan yetkiyi ele geçirirdi (3C'deki gerekçe).
    */
    $this->actingAs($sahip, 'staff-web')
        ->get('http://localhost/yonetim')
        ->assertRedirect('http://localhost/yonetim/giris');
});

it('★★ MERKEZ YONETICISI marka paneline GIREMIYOR', function () {
    markaKur('marka-a.test');
    tenancy()->end();

    $yonetici = merkezKullanici();

    // Ters yön de kapalı olmalı.
    $this->actingAs($yonetici, 'platform-web')
        ->get('http://marka-a.test/yonetim')
        ->assertRedirect('http://marka-a.test/yonetim/giris');
});

it('★★ IKI YUZEY AYRI KOK GORUNUM ve AYRI PAKET kullaniyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    $yonetici = merkezKullanici();
    tenancy()->end();

    $merkez = $this->actingAs($yonetici, 'platform-web')->get('http://localhost/yonetim')->getContent();
    $panel = $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim')->getContent();

    /*
    | ⚠️ 4C'de Inertia middleware'i BÜTÜN `web` grubuna ekliyordu. 4F'de
    | ikinci yüzey gelince ikisi çakışırdı: kök görünümü sonuncusu
    | belirlerdi ve marka paneli merkez kabuğuyla render edilebilirdi.
    | Her yüzey artık kendi middleware'ini kendi rota grubunda takıyor.
    */
    /*
    | ⚠️ PAKET ADI ARANMIYOR: `withoutVite()` `@vite` yönergesini boşaltıyor,
    | yani derlenmiş dosya adları cevapta hiç yok. İddia KÖK GÖRÜNÜM
    | üzerinden kuruluyor — iki şablonun başlıkları farklı.
    */
    expect($merkez)->toContain('TıkMarka Yönetim')
        ->and($merkez)->toContain('noindex')
        ->and($panel)->not->toContain('TıkMarka Yönetim');
});

/*
|--------------------------------------------------------------------------
| ★★ MARKA VERİSİ DIŞA AKTARMA — Faz 3'ten devredilen borç
|--------------------------------------------------------------------------
*/

it('★★ MARKA VERISI JSON olarak indirilebiliyor', function () {
    ['tenant' => $marka] = markaKur('marka-a.test');
    magazayiHazirla();
    tenancy()->end();

    $yonetici = merkezKullanici();

    $cevap = $this->actingAs($yonetici, 'platform-web')
        ->get("http://localhost/yonetim/markalar/{$marka->id}/disa-aktar");

    $cevap->assertOk();

    /*
    | ⚠️ `attachment` — tarayıcı dosyayı GÖSTERMEK yerine indiriyor.
    | Olmasaydı bütün marka verisi ekranda açılır, tarayıcı geçmişinde
    | ve önbelleğinde kalırdı.
    */
    expect($cevap->headers->get('content-disposition'))->toContain('attachment');

    $veri = $cevap->json();

    expect($veri['tenant']['id'])->toBe((string) $marka->id)
        ->and($veri)->toHaveKey('exported_at')
        ->and($veri['tables'])->toHaveKey('settings')
        ->and($veri['tables']['settings'])->not->toBeEmpty();
});

it('★★ DISA AKTARIMDA OTURUM JETONLARI YOK', function () {
    ['tenant' => $marka, 'sahip' => $sahip] = markaKur('marka-a.test');

    // Gerçek bir jeton üret — dosyaya sızmamalı.
    $sahip->createToken('panel');
    tenancy()->end();

    $yonetici = merkezKullanici();

    $veri = $this->actingAs($yonetici, 'platform-web')
        ->get("http://localhost/yonetim/markalar/{$marka->id}/disa-aktar")
        ->json();

    /*
    | ⚠️ Aktif oturum jetonları dosyaya yazılsaydı, dosyayı gören herkes
    | markanın API'sine girebilirdi. Tablo listesi AÇIK YAZILI olduğu
    | için bu güvence otomatik taramaya bırakılmıyor.
    */
    expect($veri['tables'])->not->toHaveKey('personal_access_tokens')
        ->and(TenantDataExport::TABLOLAR)->not->toContain('personal_access_tokens');
});

it('★★ DISA AKTARIMDA PAROLA HASHI YOK', function () {
    ['tenant' => $marka] = markaKur('marka-a.test');
    magazayiHazirla();

    /*
    | ★ BU TESTİ GERÇEK KOŞU DOĞURDU. Döküm alındıktan sonra dosyanın
    | içine bakınca `customers.password` kolonunda bcrypt hash'leri
    | göründü — müşteriler hesap açabiliyor (M-1) ve parolaları o
    | tabloda duruyor.
    |
    | ⚠️ Tablo listesini daraltmak YETMİYORDU: sorun tablonun kendisi
    | değil, İÇİNDEKİ KOLONDU. Marka müşteri listesini almalı, müşteri
    | parolalarını almamalı.
    */
    Customer::create([
        'name' => 'Test Musteri',
        'email' => 'musteri@example.com',
        'password' => 'gizli-parola-1234',
    ]);

    tenancy()->end();

    $yonetici = merkezKullanici();

    $veri = $this->actingAs($yonetici, 'platform-web')
        ->get("http://localhost/yonetim/markalar/{$marka->id}/disa-aktar")
        ->json();

    expect($veri['tables']['customers'])->not->toBeEmpty()
        ->and($veri['tables']['customers'][0])->not->toHaveKey('password');

    // Dosyanın HİÇBİR YERİNDE bcrypt izi olmamalı.
    expect(json_encode($veri))->not->toMatch('/\$2y\$[0-9]{2}\$/');
});

it('★★ SIFRELI AYAR DEGERLERI dosyaya girmiyor', function () {
    ['tenant' => $marka] = markaKur('marka-a.test');

    /*
    | ⚠️ Ödeme sağlayıcısının gizli anahtarı `settings`'te ŞİFRELİ duruyor
    | (1E.1). Şifreli olması dosyaya konabileceği anlamına gelmiyor —
    | dosya uygulama anahtarıyla birlikte sızarsa çözülebilir.
    */
    tenancy()->end();

    $yonetici = merkezKullanici();

    $veri = $this->actingAs($yonetici, 'platform-web')
        ->get("http://localhost/yonetim/markalar/{$marka->id}/disa-aktar")
        ->json();

    $sifreliler = array_filter(
        $veri['tables']['settings'],
        fn (array $s) => ($s['is_encrypted'] ?? false) == true,
    );

    expect($sifreliler)->not->toBeEmpty();

    foreach ($sifreliler as $ayar) {
        expect($ayar)->not->toHaveKey('value');
    }
});

it('★★ KIMLIKSIZ kisi marka verisini INDIREMIYOR', function () {
    ['tenant' => $marka] = markaKur('marka-a.test');
    tenancy()->end();

    $this->get("http://localhost/yonetim/markalar/{$marka->id}/disa-aktar")
        ->assertRedirect('http://localhost/yonetim/giris');
});

it('★★ DISA AKTARIM SONRASI kiraci baglami KAPANIYOR', function () {
    ['tenant' => $marka] = markaKur('marka-a.test');
    tenancy()->end();

    $yonetici = merkezKullanici();

    $this->actingAs($yonetici, 'platform-web')
        ->get("http://localhost/yonetim/markalar/{$marka->id}/disa-aktar")
        ->assertOk();

    /*
    | ⚠️ Bağlam açık bırakılsaydı SONRAKİ istek yanlış şemada koşardı —
    | ve bu hata vermezdi, yalnızca yanlış veri gösterirdi.
    */
    expect(tenancy()->initialized)->toBeFalse();
});

it('★ marka listesi ve ayrinti aciliyor', function () {
    ['tenant' => $marka] = markaKur('marka-a.test');
    tenancy()->end();

    $yonetici = merkezKullanici();

    $liste = inertiaVerisi(
        $this->actingAs($yonetici, 'platform-web')->get('http://localhost/yonetim/markalar')->getContent(),
    );

    expect($liste['component'])->toBe('Markalar/Liste')
        ->and($liste['props']['markalar']['data'])->not->toBeEmpty();

    $ayrinti = inertiaVerisi(
        $this->actingAs($yonetici, 'platform-web')->get("http://localhost/yonetim/markalar/{$marka->id}")->getContent(),
    );

    expect($ayrinti['component'])->toBe('Markalar/Ayrinti')
        ->and($ayrinti['props']['marka']['id'])->toBe((string) $marka->id);
});

it('★★ GECERSIZ durum gecisi sunucuda REDDEDILIYOR', function () {
    ['tenant' => $marka] = markaKur('marka-a.test');
    tenancy()->end();

    $marka->status = TenantStatus::Closed;
    $marka->save();

    $yonetici = merkezKullanici();

    /*
    | ⚠️ Kapatılmış marka yalnızca `Active`'e dönebiliyor (3C). Arayüzde
    | bütün durumlar seçilebilir görünüyor — koruma SUNUCUDA.
    */
    $this->actingAs($yonetici, 'platform-web')
        ->post("http://localhost/yonetim/markalar/{$marka->id}/durum", ['status' => 'suspended'])
        ->assertStatus(409);   // ⚠️ 422 beklemiştim; geçersiz DURUM GEÇİŞİ bir çakışma (3C).

    expect($marka->refresh()->status)->toBe(TenantStatus::Closed);
});
