<?php

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/*
| Mağaza ayarları servisi.
|
| Kiracı paketinde (RefreshDatabase yok): şema oluşturmak transaction
| içinde çalışmıyor — gerekçe tests/Pest.php'de.
*/

it('ayarı yazıp okuyor, olmayan ayarda varsayılanı dönüyor', function () {
    markaKur('ayar-a.test');
    $ayarlar = app(SettingsService::class);

    $ayarlar->yaz(SettingGroup::Shipping, 'flat_fee', 49.90);

    expect($ayarlar->al(SettingGroup::Shipping, 'flat_fee'))->toBe(49.90)
        ->and($ayarlar->al(SettingGroup::Shipping, 'yok', 'VARSAYILAN'))->toBe('VARSAYILAN');
});

it('grubu önbellekten okuyor, yazınca önbelleği temizliyor', function () {
    markaKur('ayar-b.test');
    $ayarlar = app(SettingsService::class);

    $ayarlar->yaz(SettingGroup::Store, 'name', 'İlk Ad');
    $ayarlar->grup(SettingGroup::Store);   // önbelleğe alsın

    DB::flushQueryLog();
    DB::enableQueryLog();
    $ayarlar->al(SettingGroup::Store, 'name');
    $ayarlar->al(SettingGroup::Store, 'name');

    expect(DB::getQueryLog())->toHaveCount(0);

    // Yazma önbelleği düşürmeliydi; düşürmeseydi eski ad dönerdi.
    $ayarlar->yaz(SettingGroup::Store, 'name', 'Yeni Ad');
    expect($ayarlar->al(SettingGroup::Store, 'name'))->toBe('Yeni Ad');
});

it('şifreli ayarı veritabanında düz metin tutmuyor', function () {
    markaKur('ayar-c.test');
    $ayarlar = app(SettingsService::class);

    $ayarlar->yaz(SettingGroup::Payment, 'api_key', 'cok-gizli-anahtar', sifreli: true);

    // ⚠️ Modelden değil, HAM SATIRDAN okuyoruz. Model okurken çözüyor;
    // asıl soru diskte ne yazdığı.
    $ham = (string) DB::table('settings')
        ->where('group', 'payment')->where('key', 'api_key')->value('value');

    expect($ham)->not->toContain('cok-gizli-anahtar')
        ->and($ayarlar->al(SettingGroup::Payment, 'api_key'))->toBe('cok-gizli-anahtar');
});

it('şifreli ayarı panele DEĞER olarak vermiyor', function () {
    markaKur('ayar-d.test');
    $ayarlar = app(SettingsService::class);

    $ayarlar->yaz(SettingGroup::Payment, 'api_key', 'cok-gizli-anahtar', sifreli: true);

    $panel = $ayarlar->paneleGorunen(SettingGroup::Payment);

    expect($panel['api_key'])->toBe(['is_set' => true, 'encrypted' => true]);
});

it('iki markanın aynı anahtarı birbirine karışmıyor', function () {
    $a = markaKur('ayar-e.test');
    $ayarlar = app(SettingsService::class);
    $ayarlar->yaz(SettingGroup::Store, 'name', 'A Adı');

    tenancy()->end();
    markaKur('ayar-f.test');
    $ayarlar->yaz(SettingGroup::Store, 'name', 'B Adı');

    expect($ayarlar->al(SettingGroup::Store, 'name'))->toBe('B Adı');

    // ⚠️ Asıl sınav burada: A'ya dönünce önbellek B'nin değerini
    // vermemeli. Cache kiracı etiketli olmasaydı burası kırılırdı (0.5).
    tenancy()->end();
    tenancy()->initialize($a['tenant']);

    expect($ayarlar->al(SettingGroup::Store, 'name'))->toBe('A Adı');
});

it('değer yazılmadan önce is_encrypted belirlenmemişse hata veriyor', function () {
    markaKur('ayar-g.test');

    // Sıra bozulursa ödeme anahtarı sessizce düz metin kaydedilirdi (1A.1).
    expect(fn () => (new Setting)->fill(['group' => 'payment', 'key' => 'x', 'value' => 'y']))
        ->toThrow(RuntimeException::class);
});
