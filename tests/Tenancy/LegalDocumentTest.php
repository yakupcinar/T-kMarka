<?php

use App\Domain\Legal\EmptyLegalDocumentException;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Legal\LegalTemplates;
use App\Domain\Legal\UnfilledPlaceholderException;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StoreReadiness;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;
use App\Models\LegalDocumentVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('taslağa yazmak yayınlamıyor', function () {
    markaKur('yasal-a.test');
    $belgeler = app(LegalDocumentService::class);

    $belgeler->taslagaYaz(LegalDocumentType::Returns, 'iade 14 gün');

    expect($belgeler->taslak(LegalDocumentType::Returns))->toBe('iade 14 gün')
        ->and($belgeler->guncelSurum(LegalDocumentType::Returns))->toBeNull();
});

it('boş taslağı yayınlamıyor', function () {
    markaKur('yasal-b.test');
    $belgeler = app(LegalDocumentService::class);

    $belgeler->taslagaYaz(LegalDocumentType::Returns, '   ');

    expect(fn () => $belgeler->yayinla(LegalDocumentType::Returns))
        ->toThrow(EmptyLegalDocumentException::class);
});

it('yeni sürüm eskisine DOKUNMUYOR — eski siparişin dayanağı korunuyor', function () {
    markaKur('yasal-c.test');
    $belgeler = app(LegalDocumentService::class);

    $belgeler->taslagaYaz(LegalDocumentType::Returns, 'iade süresi 14 gündür');
    $v1 = $belgeler->yayinla(LegalDocumentType::Returns);

    $belgeler->taslagaYaz(LegalDocumentType::Returns, 'iade süresi 7 gündür');
    $v2 = $belgeler->yayinla(LegalDocumentType::Returns);

    expect($v2->version_no)->toBe($v1->version_no + 1)
        ->and($belgeler->guncelSurum(LegalDocumentType::Returns)?->content)->toBe('iade süresi 7 gündür')
        // ⚠️ Bu satır bloğun varlık sebebi: 15 Mart'ta verilen sipariş
        // 20 Mart'ta değiştirilen metne değil, kendi sürümüne bağlı kalmalı.
        ->and($belgeler->surum($v1->id)?->content)->toBe('iade süresi 14 gündür');
});

it('yayınlanmış sürüm GÜNCELLENEMİYOR ve SİLİNEMİYOR', function () {
    markaKur('yasal-d.test');
    $belgeler = app(LegalDocumentService::class);

    $belgeler->taslagaYaz(LegalDocumentType::Returns, 'iade süresi 14 gündür');
    $surum = $belgeler->yayinla(LegalDocumentType::Returns);

    // Veritabanı tetiği — kodda "güncellemiyoruz" demek yetmez.
    expect(fn () => $surum->update(['content' => 'değişti']))->toThrow(QueryException::class)
        ->and(fn () => $surum->delete())->toThrow(QueryException::class)
        // TRUNCATE ayrı tetik ister: satır tetiği onu görmüyor.
        ->and(fn () => DB::statement('TRUNCATE legal_document_versions'))->toThrow(QueryException::class);

    expect(LegalDocumentVersion::find($surum->id)?->content)->toBe('iade süresi 14 gündür');
});

it('doldurulamayan yer tutucu varsa yayınlamıyor', function () {
    markaKur('yasal-e.test');
    $belgeler = app(LegalDocumentService::class);

    // İskelet, şirket bilgileri girilmeden yayınlanmaya çalışılıyor.
    $belgeler->taslagaYaz(
        LegalDocumentType::Returns,
        LegalTemplates::iskelet(LegalDocumentType::Returns),
    );

    expect(fn () => $belgeler->yayinla(LegalDocumentType::Returns))
        ->toThrow(UnfilledPlaceholderException::class);

    // ⚠️ Reddedilen yayın SÜRÜM BIRAKMAMALI — transaction geri sarılmalı.
    expect(LegalDocumentVersion::count())->toBe(0);
});

it('yayınlanan metinde yer tutucu kalmıyor ve değerler DONUYOR', function () {
    markaKur('yasal-f.test');
    sirketBilgileriniDoldur();
    $belgeler = app(LegalDocumentService::class);
    $ayarlar = app(SettingsService::class);

    $belgeler->taslagaYaz(LegalDocumentType::Returns, 'Satıcı {{unvan}} · Vergi {{vergi_no}}');
    $surum = $belgeler->yayinla(LegalDocumentType::Returns);

    expect($surum->content)->toBe('Satıcı Test Ticaret Ltd. Şti. · Vergi 1234567890')
        ->and($surum->content)->not->toContain('{{');

    // Şirket bilgisi sonradan değişse bile yayınlanmış metin DEĞİŞMİYOR.
    $ayarlar->yaz(SettingGroup::Store, 'legal_name', 'Başka Unvan A.Ş.');

    expect($belgeler->surum($surum->id)?->content)->toContain('Test Ticaret Ltd. Şti.');
});

it('tanınmayan yer tutucu metni yayından düşürüyor', function () {
    markaKur('yasal-g.test');
    sirketBilgileriniDoldur();
    $belgeler = app(LegalDocumentService::class);

    $belgeler->taslagaYaz(LegalDocumentType::Returns, 'IBAN: {{iban}}');

    expect(fn () => $belgeler->yayinla(LegalDocumentType::Returns))
        ->toThrow(UnfilledPlaceholderException::class);
});

it('hazırlık denetimi TASLAĞI değil YAYINLANMIŞ sürümü arıyor', function () {
    markaKur('yasal-h.test');
    sirketBilgileriniDoldur();
    $belgeler = app(LegalDocumentService::class);
    $hazirlik = app(StoreReadiness::class);

    foreach (LegalDocumentType::cases() as $tur) {
        $belgeler->taslagaYaz($tur, 'metin');
    }

    // Üç taslak da dolu ama hiçbiri yayınlanmadı.
    expect($hazirlik->eksikler())->toHaveCount(3);

    foreach (LegalDocumentType::cases() as $tur) {
        $belgeler->yayinla($tur);
    }

    expect($hazirlik->hazirMi())->toBeTrue();
});
