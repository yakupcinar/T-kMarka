<?php

use App\Domain\Catalog\CategoryCycleException;
use App\Domain\Catalog\CategoryHasChildrenException;
use App\Domain\Catalog\CategoryService;
use App\Models\Category;

/*
| Kategori ağacı (1B-K6).
|
| Bu bloğun iki gerçek kuralı var, ikisi de sessiz bozulmaya açık:
|   taşıma → alt ağacın TAMAMI güncellenmeli (yarım kalırsa ağaç kopar)
|   döngü  → kategori kendi torununun altına giremez (path sonsuza gider)
*/

/**
 * Giyim > Üst Giyim > Tişört > Basic + ayrı bir Erkek kökü kurar.
 *
 * @return array{giyim: Category, ust: Category, tisort: Category, basic: Category, erkek: Category}
 */
function ornekAgac(CategoryService $servis): array
{
    $giyim = $servis->olustur('Giyim');
    $ust = $servis->olustur('Üst Giyim', $giyim);
    $tisort = $servis->olustur('Tişört', $ust);
    $basic = $servis->olustur('Basic', $tisort);
    $erkek = $servis->olustur('Erkek');

    return compact('giyim', 'ust', 'tisort', 'basic', 'erkek');
}

it('path ve level ağaçtaki yere göre üretiliyor', function () {
    markaKur('kat-a.test');
    $a = ornekAgac(app(CategoryService::class));

    expect($a['giyim']->path)->toBe("/{$a['giyim']->id}/")
        ->and($a['giyim']->level)->toBe(0)
        ->and($a['basic']->path)->toBe("/{$a['giyim']->id}/{$a['ust']->id}/{$a['tisort']->id}/{$a['basic']->id}/")
        ->and($a['basic']->level)->toBe(3);
});

it('★ taşıma ALT AĞACIN TAMAMINI güncelliyor', function () {
    markaKur('kat-b.test');
    $servis = app(CategoryService::class);
    $a = ornekAgac($servis);

    // "Üst Giyim" dalını (altında 2 kategori var) başka köke taşı.
    $servis->tasi($a['ust'], $a['erkek']);

    // ⚠️ Asıl sınav torunlarda: yalnızca taşınan güncellenseydi Tişört ve
    // Basic eski yolu göstermeye devam eder, "Erkek'in altındaki her şey"
    // sorgusu onları BULAMAZDI — ve hata vermezdi.
    expect($a['tisort']->refresh()->path)
        ->toBe("/{$a['erkek']->id}/{$a['ust']->id}/{$a['tisort']->id}/")
        ->and($a['tisort']->level)->toBe(2)
        ->and($a['basic']->refresh()->level)->toBe(3)
        ->and($a['basic']->path)->toStartWith("/{$a['erkek']->id}/");
});

it('alt ağaç sorgusu taşımadan sonra doğru sonuç veriyor', function () {
    markaKur('kat-c.test');
    $servis = app(CategoryService::class);
    $a = ornekAgac($servis);

    $servis->tasi($a['ust'], $a['erkek']);

    $altAgac = Category::altAgac($a['erkek']->refresh())->pluck('name')->all();

    expect($altAgac)->toContain('Erkek', 'Üst Giyim', 'Tişört', 'Basic')
        ->and($altAgac)->not->toContain('Giyim');
});

it('köke taşıma çalışıyor', function () {
    markaKur('kat-d.test');
    $servis = app(CategoryService::class);
    $a = ornekAgac($servis);

    $servis->tasi($a['tisort'], null);

    expect($a['tisort']->refresh()->level)->toBe(0)
        ->and($a['tisort']->parent_id)->toBeNull()
        ->and($a['tisort']->path)->toBe("/{$a['tisort']->id}/")
        // Torunu da yükselmeli.
        ->and($a['basic']->refresh()->level)->toBe(1);
});

it('★ kategori kendi torununun altına taşınamıyor', function () {
    markaKur('kat-e.test');
    $servis = app(CategoryService::class);
    $a = ornekAgac($servis);

    // Engellenmeseydi path sonsuza gider, alt ağaç sorgusu asla dönmezdi.
    expect(fn () => $servis->tasi($a['giyim'], $a['basic']))
        ->toThrow(CategoryCycleException::class);

    // Kendi altına da taşınamaz.
    expect(fn () => $servis->tasi($a['giyim'], $a['giyim']))
        ->toThrow(CategoryCycleException::class);

    expect($a['giyim']->refresh()->parent_id)->toBeNull();
});

it('alt kategorisi olan silinemiyor', function () {
    markaKur('kat-f.test');
    $servis = app(CategoryService::class);
    $a = ornekAgac($servis);

    // Cascade olsaydı "Giyim" silinince altındaki dal da sessizce giderdi.
    expect(fn () => $servis->sil($a['tisort']))
        ->toThrow(CategoryHasChildrenException::class);

    $servis->sil($a['basic']);
    $servis->sil($a['tisort']->refresh());

    expect(Category::count())->toBe(3);
});

it('ekmek kırıntısı path ten çıkıyor — ek sorgu yok', function () {
    markaKur('kat-g.test');
    $a = ornekAgac(app(CategoryService::class));

    // Ata id'leri zaten path'in içinde; ayrı bir özyinelemeli sorguya
    // gerek kalmıyor. `path`'in varlık sebebi bu.
    expect($a['basic']->ataIdleri())
        ->toBe([$a['giyim']->id, $a['ust']->id, $a['tisort']->id]);
});

it('ad değişince path DEĞİŞMİYOR', function () {
    markaKur('kat-h.test');
    $servis = app(CategoryService::class);
    $a = ornekAgac($servis);
    $eskiPath = $a['basic']->path;

    // path id zinciri (1B-K6). Slug zinciri olsaydı burada alt ağacın
    // tamamı yeniden yazılırdı.
    $servis->guncelle($a['tisort'], 'T-Shirt', 0);

    expect($a['tisort']->refresh()->slug)->toBe('t-shirt')
        ->and($a['basic']->refresh()->path)->toBe($eskiPath);
});

it('taşıma ucu HTTP üzerinden çalışıyor, döngü 409 dönüyor', function () {
    $marka = markaKur('kat-i.test');
    $token = panelTokeni('kat-i.test', $marka['sahip']->email);
    $a = ornekAgac(app(CategoryService::class));

    $this->withToken($token)
        ->postJson("http://kat-i.test/panel/categories/{$a['ust']->uuid}/move", [
            'parent_uuid' => $a['erkek']->uuid,
        ])->assertOk();

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://kat-i.test/panel/categories/{$a['erkek']->uuid}/move", [
            'parent_uuid' => $a['basic']->uuid,
        ])->assertStatus(409);
});

it('slug ve path istekten YAZILAMIYOR', function () {
    $marka = markaKur('kat-j.test');
    $token = panelTokeni('kat-j.test', $marka['sahip']->email);

    $cevap = $this->withToken($token)->postJson('http://kat-j.test/panel/categories', [
        'name' => 'Giyim',
        'slug' => 'ele-gecirildi',
        'path' => '/999/',
        'level' => 7,
    ])->assertStatus(201);

    expect($cevap->json('category.slug'))->toBe('giyim')
        ->and($cevap->json('category.level'))->toBe(0)
        ->and($cevap->json('category.path'))->not->toBe('/999/');
});

it('iki markanın kategorileri karışmıyor', function () {
    markaKur('kat-k.test');
    app(CategoryService::class)->olustur('Giyim');

    tenancy()->end();
    markaKur('kat-l.test');
    app(CategoryService::class)->olustur('Giyim');   // aynı slug, ayrı şema

    expect(Category::count())->toBe(1);
});
