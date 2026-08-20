<?php

use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StoreTimezone;
use App\Enums\SettingGroup;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
| GÖSTERİM SAATİ (4.5M) — gerçek kullanımda bildirildi.
|
| ★ Şikâyet: *"Vitrinde ödendi hazırlanıyor yazıyor, panele baktım oraya
| ya düşmemiş ya da saati yanlış düşmüş."*
|
| ⚠️ Ölçüldü ve iddia YARI DOĞRUYDU — ama ters yönde:
|
|   depolama → timestamptz, +00                  ✅ doğru
|   panel    → new Date(iso).toLocaleString()    ✅ 11:34 (tarayıcı çevirdi)
|   vitrin   → format(), app.timezone = UTC      ❌ 08:34 (ÜÇ SAAT GERİDE)
|
| Yani sipariş panele DÜŞMÜŞTÜ ve panelin saati DOĞRUYDU; yanlış olan
| vitrindi.
|
| ⚠️ Çözüm `config/app.php`'de `timezone` değiştirmek DEĞİL: Laravel
| `now()`'ı sorguya ofissiz metin bağlıyor ve PostgreSQL onu oturumun
| `TimeZone`'una göre yorumluyor — 15 dakikalık rezervasyonlar üç saat
| kayardı (CLAUDE.md · WooCommerce #43593). Depolama UTC kalıyor,
| DEĞİŞEN yalnızca gösterim.
*/

beforeEach(function () {
    /*
    | ⚠️ PANEL TESTLERİ `withoutVite()` İSTER. Yerelde `public/build`
    | duruyor, CI'da yok: manifest bulunamayınca sayfa Inertia yerine
    | istisna basıyor ve `inertiaVerisi()` "0 is identical to 1" ile
    | düşüyor — hata mesajı sebebi hiç göstermiyor.
    |
    | ⚠️ "Yerel yeşil ≠ CI yeşil, otorite CI" kuralının bir örneği daha:
    | burada 777 test yeşildi, CI'da iki panel testi kırmızıydı.
    */
    $this->withoutVite();
});

/** @return array{siparis: Order, musteri: Customer} */
function saatTestiSiparisi(): array
{
    $varyant = sayacMagazasi();

    $musteri = Customer::create([
        'email' => 'saat@ornek.test', 'password' => bcrypt('sifre1234'), 'name' => 'Ayşe Yılmaz',
    ]);

    $siparis = bekleyenSiparis($varyant, $musteri);

    /*
    | ⚠️ Damga ELLE yazılıyor: testin ölçtüğü şey "şu an kaç" değil,
    | "UTC damga hangi saatle GÖSTERİLİYOR". Gerçek zamana bağlı test
    | gece yarısı kırılırdı.
    */
    $siparis->placed_at = CarbonImmutable::parse('2026-08-20 08:34:00', 'UTC');
    $siparis->save();

    return ['siparis' => $siparis->refresh(), 'musteri' => $musteri];
}

it('★★★ VITRIN siparis saatini MAGAZANIN saat diliminde gosteriyor', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = saatTestiSiparisi();

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);

    /*
    | 08:34 UTC → Europe/Istanbul (+03) → 11:34.
    | Düzeltmeden önce ekranda 08:34 yazıyordu.
    */
    $this->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertOk()
        ->assertSee('20.08.2026 11:34')
        ->assertDontSee('20.08.2026 08:34');
});

it('★★★ DEPOLAMA UTC kaliyor — gosterim degisti, veri DEGISMEDI', function () {
    ['siparis' => $siparis] = saatTestiSiparisi();

    /*
    | ⚠️ Bu testin asıl işi: birinin `config/app.php`'de `timezone`'u
    | değiştirerek "düzeltmesini" engellemek. Öyle yapılsaydı gösterim
    | doğrulur ama `now()` ofissiz metin olarak bağlandığı için
    | rezervasyon süreleri üç saat kayardı.
    */
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('database.connections.tenant.timezone'))->toBe('UTC')
        ->and($siparis->getRawOriginal('placed_at'))->toContain('08:34');
});

it('★★★ MARKA saat dilimini DEGISTIREBILIYOR', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = saatTestiSiparisi();

    app(SettingsService::class)->yaz(SettingGroup::Store, StoreTimezone::ANAHTAR, 'Europe/London');

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);

    // 08:34 UTC → Europe/London (yaz saati, +01) → 09:34.
    $this->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertOk()
        ->assertSee('20.08.2026 09:34');
});

it('★★★ GECERSIZ saat dilimi VARSAYILANA dusuyor — sayfa cokmuyor', function () {
    ['siparis' => $siparis, 'musteri' => $musteri] = saatTestiSiparisi();

    /*
    | ⚠️ Ayar veritabanına tohumlayıcı, artisan komutu ya da elle SQL ile
    | de girebiliyor. Doğrulanmasaydı `setTimezone()` istisna fırlatır ve
    | müşteri kendi sipariş sayfasında 500 görürdü. [ThemeSettings] ile
    | aynı beyaz liste gerekçesi.
    */
    app(SettingsService::class)->yaz(SettingGroup::Store, StoreTimezone::ANAHTAR, 'Mars/Olympus');

    expect(app(StoreTimezone::class)->oku())->toBe(StoreTimezone::VARSAYILAN);

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);

    $this->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertOk()
        ->assertSee('20.08.2026 11:34');
});

it('★★ PANEL ayni saat dilimini prop olarak ALIYOR', function () {
    saatTestiSiparisi();

    $sahip = User::where('is_owner', true)->firstOrFail();

    $veri = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/siparisler')->getContent() ?: '',
    );

    /*
    | ⚠️ Panel tarihleri tarayıcıda basıyor; sunucu cevabı METNİ
    | İÇERMİYOR (4D). Bu yüzden prop ölçülüyor: dilim gitmezse ekran
    | personelin kendi saat dilimine düşer ve iki yüzey yine ayrışır.
    */
    expect($veri['props']['marka']['saat_dilimi'])->toBe('Europe/Istanbul');
});

it('★★ AYNI KAYNAK: vitrin ve panel ayni ayardan okuyor', function () {
    ['musteri' => $musteri] = saatTestiSiparisi();

    app(SettingsService::class)->yaz(SettingGroup::Store, StoreTimezone::ANAHTAR, 'UTC');

    $sahip = User::where('is_owner', true)->firstOrFail();

    $panel = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')
            ->get('http://marka-a.test/yonetim/siparisler')->getContent() ?: '',
    );

    expect($panel['props']['marka']['saat_dilimi'])->toBe('UTC');

    /*
    | ⚠️ İki yüzey AYNI ayarı okumazsa aslen düzeltilen şey geri gelir:
    | biri 11:34 öteki 08:34 gösterir ve marka "sipariş panele yanlış
    | saatle düşmüş" der.
    */
    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);

    $siparis = Order::orderByDesc('id')->firstOrFail();

    $this->get("http://marka-a.test/hesabim/siparis/{$siparis->uuid}")
        ->assertSee('20.08.2026 08:34');
});
