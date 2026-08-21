<?php

use App\Domain\Legal\LegalDocumentService;
use App\Domain\Payment\FakePaymentProvider;
use App\Domain\Settings\DefaultsBackfill;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;
use App\Models\LegalDocumentDraft;
use App\Models\Role;
use App\Models\Setting;

/*
| Eksik varsayılanların tamamlanması (3A).
|
| ★ İKİ İDDİA ve ikincisi daha önemli:
|   1  EKSİK olan ekleniyor
|   2  VAR OLAN kesinlikle EZİLMİYOR
|
| ⚠️ 2 bozulursa sonuç felaket: açık mağaza kapanır, markanın yazdığı
| yasal metin silinir, yoldaki ödeme bildirimlerinin imzası geçersiz olur.
*/

/** Bir ayarı ve/veya taslağı silerek "eski marka" durumu üretir. */
function ayariSil(SettingGroup $grup, string $anahtar): void
{
    Setting::where('group', $grup)->where('key', $anahtar)->delete();
}

it('★ EKSİK ayar tamamlanıyor', function () {
    $marka = markaKur('bf-a.test');

    /*
    | ⚠️ Gerçek bir vaka taklit ediliyor: `threshold_after_discount` 2A'da
    | eklendi ve ÖLÇÜLDÜ — bugün iki gerçek markada eksik.
    */
    ayariSil(SettingGroup::Shipping, 'threshold_after_discount');

    $servis = app(DefaultsBackfill::class);

    expect($servis->eksikler('X')['settings'])->toContain('shipping.threshold_after_discount');

    $servis->tamamla('X');

    expect(app(SettingsService::class)->al(SettingGroup::Shipping, 'threshold_after_discount'))->toBeTrue()
        ->and($servis->eksikler('X')['settings'])->toBe([]);
});

it('★ VAR OLAN AYAR EZİLMİYOR — değiştirilmiş değer korunuyor', function () {
    markaKur('bf-b.test');

    $ayarlar = app(SettingsService::class);

    // Marka vergi oranını kendi değiştirmiş.
    $ayarlar->yaz(SettingGroup::Tax, 'default_rate', 10);

    /*
    | ⚠️ `DefaultSettings::kur()` çağrılsaydı bu 20'ye DÖNERDİ. Backfill'in
    | tamamı bu satırın korunması için var.
    */
    app(DefaultsBackfill::class)->tamamla('X');

    expect((int) $ayarlar->al(SettingGroup::Tax, 'default_rate'))->toBe(10);
});

it('★ AÇIK MAĞAZA KAPANMIYOR — en tehlikeli ezme', function () {
    markaKur('bf-c.test');
    magazayiHazirla();

    app(StorePublication::class)->yayinla();

    expect(app(StorePublication::class)->yayindaMi())->toBeTrue();

    /*
    | ★ EN TEHLİKELİ VAKA. `kur()` `is_published`'ı FALSE yazıyor; backfill
    | onu çağırsaydı bu komut ÇALIŞAN BİR MAĞAZAYI KAPATIRDI — hata
    | vermeden, tek koşuda, bütün markalarda.
    */
    app(DefaultsBackfill::class)->tamamla('X');

    expect(app(StorePublication::class)->yayindaMi())->toBeTrue();
});

it('★ MARKANIN YAZDIĞI TASLAK EZİLMİYOR', function () {
    markaKur('bf-d.test');

    $belgeler = app(LegalDocumentService::class);
    $belgeler->taslagaYaz(LegalDocumentType::DistanceSales, 'Markanın kendi yazdığı metin.');

    app(DefaultsBackfill::class)->tamamla('X');

    /*
    | ⚠️ Ezilseydi marka saatlerce yazdığı sözleşme metnini kaybederdi ve
    | yerine iskelet gelirdi — üstelik fark etmesi zor.
    */
    expect($belgeler->taslak(LegalDocumentType::DistanceSales))
        ->toBe('Markanın kendi yazdığı metin.');
});

it('★ İMZA ANAHTARI YENİLENMİYOR — yoldaki bildirimler geçersiz olurdu', function () {
    markaKur('bf-e.test');

    $ayarlar = app(SettingsService::class);
    $once = (string) $ayarlar->al(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);

    expect($once)->not->toBeEmpty();

    app(DefaultsBackfill::class)->tamamla('X');

    /*
    | ⚠️ Yenilenseydi sağlayıcının yolda olan bildirimleri imza
    | doğrulamasından geçemez, ödeme kaydedilemezdi (1E.6'daki webhook
    | zincirinin aynısı).
    */
    expect((string) $ayarlar->al(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR))->toBe($once);
});

it('★ EKSİK İMZA ANAHTARI üretiliyor — 1E.4 vakası', function () {
    markaKur('bf-f.test');

    /*
    | ★ GERÇEK VAKA: 0.5'te açılan markaların imza anahtarı yoktu
    | (`DefaultSettings` 1E.1'de genişledi) ve iki kiracıda gerçek HTTP
    | koşusu "fake_secret anahtarı ayarlarda yok" hatasıyla DURDU.
    */
    ayariSil(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);

    app(DefaultsBackfill::class)->tamamla('X');

    $uretilen = (string) app(SettingsService::class)->al(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);

    // ⚠️ Sabit değil RASTGELE: sabit olsaydı bir markanın ürettiği
    // bildirim diğerinde de geçerli olurdu (1E.1'de ölçüldü).
    expect(strlen($uretilen))->toBe(48);
});

it('★ İKİ MARKANIN imza anahtarı FARKLI üretiliyor', function () {
    markaKur('bf-g.test');
    ayariSil(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);
    app(DefaultsBackfill::class)->tamamla('X');
    $a = (string) app(SettingsService::class)->al(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);

    tenancy()->end();

    markaKur('bf-h.test');
    ayariSil(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);
    app(DefaultsBackfill::class)->tamamla('X');
    $b = (string) app(SettingsService::class)->al(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);

    expect($a)->not->toBe($b);
});

it('★ EKSİK YASAL TASLAK tamamlanıyor — 1A.6 vakası', function () {
    markaKur('bf-i.test');

    /*
    | ★ GERÇEK VAKA: 0.5'te açılan B markası yasal TASLAKSIZDI ve bu
    | 1A.6'ya kadar fark edilmedi.
    */
    LegalDocumentDraft::query()->delete();

    $servis = app(DefaultsBackfill::class);

    expect($servis->eksikler('X')['drafts'])->toHaveCount(count(LegalDocumentType::cases()));

    $servis->tamamla('X');

    expect($servis->eksikler('X')['drafts'])->toBe([])
        ->and(LegalDocumentDraft::count())->toBe(count(LegalDocumentType::cases()));
});

it('★ EKSİK ROL tamamlanıyor', function () {
    markaKur('bf-j.test');

    Role::query()->delete();

    $servis = app(DefaultsBackfill::class);

    expect($servis->eksikler('X')['roles'])->not->toBe([]);

    $servis->tamamla('X');

    expect($servis->eksikler('X')['roles'])->toBe([])
        /*
        | ⚠️ DÖRT: "Salt Okunur" 4.6S'de eklendi. Sayı SABİT tutuluyor
        | (dinamik hesaplanmıyor) çünkü yeni bir sistem rolü eklemek
        | bilinçli bir karar olmalı — test onu görünür kılıyor.
        */
        ->and(Role::count())->toBe(4);
});

it('★ store.name eksikse MERKEZDEKİ marka adıyla dolduruluyor', function () {
    markaKur('bf-k.test');

    ayariSil(SettingGroup::Store, 'name');

    app(DefaultsBackfill::class)->tamamla('Gerçek Marka Adı');

    /*
    | ⚠️ Yer tutucu yazılsaydı ("Bilinmeyen" gibi) marka onu VİTRİNİNDE
    | görürdü. Ad merkez kayıtta zaten var.
    */
    expect((string) app(SettingsService::class)->al(SettingGroup::Store, 'name'))
        ->toBe('Gerçek Marka Adı');
});

it('★ EKSİK is_published KAPALI yazılıyor', function () {
    markaKur('bf-l.test');

    ayariSil(SettingGroup::Store, StorePublication::ANAHTAR);

    app(DefaultsBackfill::class)->tamamla('X');

    /*
    | ⚠️ Açık yazılsaydı hazırlık denetiminden geçmemiş bir mağaza
    | kendiliğinden satışa açılırdı.
    */
    expect(app(StorePublication::class)->yayindaMi())->toBeFalse();
});

it('★ KURU ÇALIŞMA hiçbir şey yazmıyor', function () {
    markaKur('bf-m.test');

    ayariSil(SettingGroup::Shipping, 'threshold_after_discount');

    $this->artisan('marka:eksikleri-tamamla --kuru')->assertExitCode(0);

    /*
    | ⚠️ Kuru çalışma yazsaydı "önce bak, sonra yap" güvencesi yalan
    | olurdu — ve bu komut bütün markalara dokunuyor.
    */
    expect(app(DefaultsBackfill::class)->eksikler('X')['settings'])
        ->toContain('shipping.threshold_after_discount');
});

it('★ KOMUT marka bağlamı OLMADAN çalışmıyor', function () {
    markaKur('bf-n.test');
    tenancy()->end();

    $this->artisan('marka:eksikleri-tamamla')->assertExitCode(1);
});

it('★ İKİNCİ KOŞU hiçbir şey yapmıyor — idempotent', function () {
    markaKur('bf-o.test');

    ayariSil(SettingGroup::Shipping, 'flat_fee');

    $servis = app(DefaultsBackfill::class);

    expect($servis->tamamla('X')['settings'])->toBe(1);

    /*
    | ⚠️ İdempotan olmasaydı zamanlanmış ya da tekrarlı çalıştırma her
    | seferinde yazar, `fake_secret` her koşuda yenilenirdi.
    */
    expect($servis->tamamla('X'))->toBe(['settings' => 0, 'drafts' => 0, 'roles' => 0]);
});

it('★ UÇTAN: eksiği olan marka komuttan sonra TAM', function () {
    markaKur('bf-p.test');

    ayariSil(SettingGroup::Shipping, 'threshold_after_discount');
    ayariSil(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);
    LegalDocumentDraft::query()->delete();

    $this->artisan('marka:eksikleri-tamamla')->assertExitCode(0);

    expect(app(DefaultsBackfill::class)->eksikler('X'))
        ->toBe(['settings' => [], 'drafts' => [], 'roles' => []]);
});
