<?php

use App\Domain\Catalog\UnsupportedImageTypeException;
use App\Domain\Identity\RoleService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Domain\Settings\ThemeLogoService;
use App\Domain\Settings\ThemeSettings;
use App\Enums\Permission;
use App\Enums\SettingGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| TEMA EKRANI (4G) — 4-K5'in arayüzü: marka AYAR seçer, ŞABLON YAZMAZ.
|
| ★ İKİ İDDİA:
|   1  Panel yalnızca SEÇİM sunuyor; serbest metin yok, geçersiz değer
|      sunucuda reddediliyor.
|   2  Logo yükleme, ürün görselleriyle AYNI güvenlik seviyesinde.
*/

beforeEach(function () {
    $this->withoutVite();
});

function temaYetkilisi(): User
{
    $rol = app(RoleService::class)->olustur('Tema-'.uniqid(), [Permission::SettingsWrite->value]);

    $personel = User::factory()->create(['email' => 'tema@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    return $personel->refresh();
}

it('★ tema sayfasi seceneklerle aciliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/tema')->getContent(),
    );

    expect($sayfa['component'])->toBe('Tema')
        ->and($sayfa['props']['secenekler']['duzenler'])->toBe(ThemeSettings::DUZENLER)
        ->and($sayfa['props']['secenekler']['yazi_tipleri'])->toBe(array_keys(ThemeSettings::YAZI_TIPLERI));
});

it('★★ IZINSIZ personel tema sayfasina GIREMIYOR — 403', function () {
    markaKur('marka-a.test');

    $rol = app(RoleService::class)->olustur('Depocu', [Permission::OrderView->value]);
    $personel = User::factory()->create(['email' => 'depo@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    $this->actingAs($personel->refresh(), 'staff-web')
        ->get('http://marka-a.test/yonetim/tema')
        ->assertForbidden();
});

it('★ tema kaydediliyor ve VITRINE yansiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema', [
            'renk' => '#123456',
            'yazi_tipi' => 'serif',
            'duzen' => 'sade',
        ])->assertRedirect();

    $this->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee('#123456', escape: false)
        ->assertSee(ThemeSettings::YAZI_TIPLERI['serif'], escape: false);
});

it('★★ GECERSIZ renk PANELDE de reddediliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    /*
    | ⚠️ Doğrulama İKİ YERDE: panelde (markaya anlaşılır hata) ve okuma
    | yolunda (güvenlik, 4A-K1). Panelinki tek savunma DEĞİL — ayar
    | tohumlayıcı/artisan/elle SQL ile de girebiliyor.
    */
    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema', [
            'renk' => 'red; } body { background: url(https://kotu.example/x) ',
            'yazi_tipi' => 'sistem',
            'duzen' => 'sade',
        ])->assertSessionHasErrors('renk');

    expect(app(SettingsService::class)->al(SettingGroup::Theme, 'primary_color'))
        ->not->toContain('kotu.example');
});

it('★★ LISTEDE OLMAYAN duzen reddediliyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema', [
            'renk' => '#123456',
            'yazi_tipi' => 'sistem',
            'duzen' => '../../gizli',
        ])->assertSessionHasErrors('duzen');
});

/*
|--------------------------------------------------------------------------
| ★★ İKİNCİ DÜZEN — 4A'da liste tek elemanlıydı
|--------------------------------------------------------------------------
*/

it('★★ VITRINLI duzeni SECILINCE vitrin degisiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    // Önce sade: karşılama bölümü YOK.
    $this->get('http://marka-a.test/')->assertOk()->assertDontSee('Seçilmiş ürünler');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema', [
            'renk' => ThemeSettings::VARSAYILAN_RENK,
            'yazi_tipi' => 'sistem',
            'duzen' => 'vitrinli',
        ])->assertRedirect();

    /*
    | ⚠️ 4A'da `DUZENLER` tek elemanlıydı ve gerekçesi "sonradan eklemek,
    | kavramı sonradan icat etmekten kolay" diye yazılmıştı. Bu test o
    | iddianın karşılığı.
    */
    $this->get('http://marka-a.test/')->assertOk()->assertSee('Seçilmiş ürünler');
});

/*
|--------------------------------------------------------------------------
| ★★ LOGO YÜKLEME — ürün görselleriyle AYNI güvenlik seviyesi
|--------------------------------------------------------------------------
*/

it('★ logo yukleniyor ve VITRINDE gorunuyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    Storage::fake('public');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
        ])->assertRedirect();

    $yol = app(SettingsService::class)->al(SettingGroup::Theme, 'logo_path');

    expect($yol)->toStartWith('theme/');
    Storage::disk('public')->assertExists($yol);

    /*
    | ⚠️ Vitrin logoyu `tenant_asset()` ile adresliyor ve bu adres HTTP
    | katmanında kuruluyor — `app/Domain/` kiracılığı bilemez (M-2.7).
    | 4A'da yol doğrudan `src`'ye basılıyordu; yükleme gelince o hâliyle
    | KIRIK GÖRSEL çıkardı.
    */
    $this->get('http://marka-a.test/')
        ->assertOk()
        ->assertSee('/tenancy/assets/'.$yol, escape: false);
});

it('★★ PHP dosyasi PNG adiyla yuklenemiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    Storage::fake('public');

    /*
    | ★ Ürün görselleriyle AYNI kural: tür DOSYANIN İÇERİĞİNDEN okunuyor.
    | İstemcinin söylediğine güvenilseydi `zararli.php` "image/png"
    | etiketiyle diske yazılabilirdi.
    |
    | ⚠️ `UploadedFile::fake()->createWithContent()` KULLANILAMAZ ve bunu
    | ölçtüm: sahte dosya MIME türünü de UYDURUYOR (uzantıdan türetiyor),
    | yani doğrulama "image/png" görüyor ve test hiçbir şey ölçmüyor —
    | yeşil kalıyordu. Gerçek bir dosya yazılıp gerçek türüyle
    | gönderiliyor.
    */
    $gecici = tempnam(sys_get_temp_dir(), 'tema').'.png';
    file_put_contents($gecici, '<?php echo "zararli"; ?>');

    $dosya = new UploadedFile($gecici, 'logo.png', null, null, test: true);

    expect($dosya->getMimeType())->not->toBe('image/png');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema/logo', ['logo' => $dosya])
        ->assertSessionHasErrors('logo');

    expect(app(SettingsService::class)->al(SettingGroup::Theme, 'logo_path'))->toBeNull();

    @unlink($gecici);
});

it('★★ SVG kabul edilmiyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    Storage::fake('public');

    /*
    | ⚠️ SVG en cazip biçim (vektör, küçük) ama bir XML belgesidir ve
    | `<script>` taşıyabilir. Marka kendi vitrininde betik
    | çalıştırabilseydi 4-K5'in kapattığı kapı yeniden açılırdı.
    */
    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema/logo', ['logo' => $svg])
        ->assertSessionHasErrors('logo');

    expect(ThemeLogoService::IZINLI_TURLER)->not->toContain('image/svg+xml');
});

it('★ ESKI LOGO yenisi yuklenince siliniyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    Storage::fake('public');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema/logo', ['logo' => UploadedFile::fake()->image('bir.png')]);

    $ilk = app(SettingsService::class)->al(SettingGroup::Theme, 'logo_path');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema/logo', ['logo' => UploadedFile::fake()->image('iki.png')]);

    $ikinci = app(SettingsService::class)->al(SettingGroup::Theme, 'logo_path');

    /*
    | ⚠️ Silinmeseydi marka her denemede diske bir dosya bırakır, yıllar
    | sonra kimsenin bakmadığı bir yığın oluşurdu (3G'deki öksüz klasör
    | derdinin küçüğü).
    */
    expect($ikinci)->not->toBe($ilk);
    Storage::disk('public')->assertMissing($ilk);
    Storage::disk('public')->assertExists($ikinci);
});

it('★ logo KALDIRILINCA dosya da siliniyor', function () {
    ['sahip' => $sahip] = markaKur('marka-a.test');

    Storage::fake('public');

    $this->actingAs($sahip, 'staff-web')
        ->post('http://marka-a.test/yonetim/tema/logo', ['logo' => UploadedFile::fake()->image('logo.png')]);

    $yol = app(SettingsService::class)->al(SettingGroup::Theme, 'logo_path');

    $this->actingAs($sahip, 'staff-web')
        ->delete('http://marka-a.test/yonetim/tema/logo')
        ->assertRedirect();

    Storage::disk('public')->assertMissing($yol);
    expect(app(SettingsService::class)->al(SettingGroup::Theme, 'logo_path'))->toBeNull();
});

it('★ iki markanin temasi karismiyor', function () {
    ['sahip' => $sahipA] = markaKur('marka-a.test');
    app(SettingsService::class)->yaz(SettingGroup::Theme, 'primary_color', '#111111');
    tenancy()->end();

    ['sahip' => $sahipB] = markaKur('marka-b.test');
    app(SettingsService::class)->yaz(SettingGroup::Theme, 'primary_color', '#222222');
    tenancy()->end();

    $sayfaB = inertiaVerisi(
        $this->actingAs($sahipB, 'staff-web')->get('http://marka-b.test/yonetim/tema')->getContent(),
    );

    expect($sayfaB['props']['tema']['renk'])->toBe('#222222');
    expect($sahipA)->not->toBeNull();
});

it('★★ SERVIS de turu kendi denetliyor — ikinci savunma', function () {
    markaKur('marka-a.test');

    Storage::fake('public');

    /*
    | ★ BU TESTİ KIRMA DENEMESİ DOĞURDU. Servisin tür kontrolünü
    | kaldırdım ve panel testi DÜŞMEDİ: Laravel'in `mimes:` kuralı zaten
    | yakalıyordu. Yani servisteki ikinci savunma ölçülmüyordu.
    |
    | ⚠️ Savunma ÖLÜ DEĞİL, ÖLÇÜLMEMİŞTİ — fark önemli. Servis panelden
    | başka yerlerden de çağrılabilir (artisan komutu, ileride bir uç);
    | o yollarda Laravel doğrulaması yok. 2F/3E'de ölü savunmalar
    | kaldırılmıştı; bu ölü değil, testi eksikti.
    */
    $gecici = tempnam(sys_get_temp_dir(), 'tema').'.png';
    file_put_contents($gecici, '<?php echo "zararli"; ?>');

    $dosya = new UploadedFile($gecici, 'logo.png', null, null, test: true);

    expect(fn () => app(ThemeLogoService::class)->yukle($dosya))
        ->toThrow(UnsupportedImageTypeException::class);

    expect(app(SettingsService::class)->al(SettingGroup::Theme, 'logo_path'))->toBeNull();

    @unlink($gecici);
});
