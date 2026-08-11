<?php

use App\Domain\Catalog\ProductImageService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\UnsupportedImageTypeException;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Ürün görselleri — ve kiracı DOSYA izolasyonu (M-2.4/3).
|
| Bu blokta veritabanı izolasyonu değil DOSYA izolasyonu sınanıyor:
| A markasının görseli B markasının adresinden okunabiliyor mu?
*/

/** Gerçek bir PNG üretir — `image` doğrulaması içeriğe bakıyor. */
function ornekGorsel(string $ad = 'urun.png'): UploadedFile
{
    return UploadedFile::fake()->image($ad, 200, 200);
}

it('görsel MARKA klasörüne yazılıyor', function () {
    markaKur('gorsel-a.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    $gorsel = app(ProductImageService::class)->yukle($urun, ornekGorsel());

    // ⚠️ Asıl sınav: yolun içinde marka kimliği geçiyor mu?
    // Paketin bootstrapper'ı storage_path()'i çeviriyor (ölçüldü).
    $tamYol = Storage::disk('public')->path($gorsel->path);

    expect($tamYol)->toContain('storage/tenant')
        ->and(Storage::disk('public')->exists($gorsel->path))->toBeTrue()
        ->and($gorsel->path)->toStartWith("products/{$urun->uuid}/");
});

it('★ A markasının görseli B markasından OKUNAMIYOR', function () {
    markaKur('gorsel-b.test');
    $urunA = app(ProductService::class)->olustur(['title' => 'A Ürünü']);
    $gorselA = app(ProductImageService::class)->yukle($urunA, ornekGorsel());
    $yolA = $gorselA->path;

    // A'nın kendi bağlamında dosya duruyor.
    expect(Storage::disk('public')->exists($yolA))->toBeTrue();

    tenancy()->end();
    markaKur('gorsel-c.test');

    /*
    | ⚠️ AYNI YOL, BAŞKA MARKA — dosya YOK.
    |
    | İzolasyon adres üzerinden çalışıyor: `storage_path()` her markada
    | kendi klasörüne çevriliyor. Merkez sembolik bağ kullansaydık
    | (`Storage::url`) iki marka da `/storage/...` altından okurdu ve
    | izolasyon HİÇ OLMAZDI.
    |
    | ⚠️ Burada HTTP ile ölçmüyoruz: paketin varlık ucu TEST MODUNDA
    | bilerek ham istisna fırlatıyor ("hatanın sebebini test etmeyi
    | kolaylaştırmak için"), üretimde ise 404 veriyor — sızıntı olmasın
    | diye. 1A.2'deki guard önbelleği gibi bir TEST YAPAYLIĞI.
    |
    | Üretim davranışı gerçek HTTP ile ölçüldü:
    |   marka-a.localhost/tenancy/assets/<yol>  → 200   (sahibi)
    |   marka-b.localhost/tenancy/assets/<yol>  → 404   (yabancı)
    |   localhost/tenancy/assets/<yol>          → 404   (merkez)
    |   .../assets/../../../.env                → 404   (yol dışına çıkma)
    */
    expect(Storage::disk('public')->exists($yolA))->toBeFalse();
});

it('url() merkez adresi DEĞİL markanın adresini veriyor', function () {
    markaKur('gorsel-d.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $gorsel = app(ProductImageService::class)->yukle($urun, ornekGorsel());

    /*
    | ⚠️ Ölçüldü: `Storage::disk('public')->url()` iki markada da
    | `http://localhost/storage/a.jpg` döndürüyor — hem yanlış alan adı hem
    | merkez yol, hem de hata vermiyor. `tenant_asset()` doğrusunu veriyor.
    */
    expect($gorsel->url())->toContain('/tenancy/assets/')
        ->and($gorsel->url())->toContain($gorsel->path)
        ->and($gorsel->url())->not->toContain('/storage/');
});

it('dosya adı ve uzantısı İSTEMCİDEN alınmıyor', function () {
    markaKur('gorsel-e.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    $gorsel = app(ProductImageService::class)->yukle($urun, ornekGorsel('../../zararlı.php.png'));

    // Gerçek ad kullanılsaydı: yol dışına yazma denemesi, aynı adlı
    // ikinci yüklemenin öncekini ezmesi, bozuk karakterler.
    expect($gorsel->path)->not->toContain('..')
        ->and($gorsel->path)->not->toContain('zararlı')
        ->and($gorsel->path)->toEndWith('.png');
});

it('desteklenmeyen tür reddediliyor', function () {
    markaKur('gorsel-f.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    /*
     | ⚠️ ÖLÇÜLDÜ — sahte dosyanın sınırı:
     |   UploadedFile::fake()->createWithContent('urun.png', 'düz metin')
     |   → getMimeType() 'image/png' dönüyor (türü UZANTIDAN tahmin ediyor).
     | Yani "doğru uzantı, yanlış içerik" senaryosu bu test aracıyla
     | canlandırılamıyor. Gerçek `UploadedFile` dosyayı okuyup karar veriyor.
     |
     | Ölçülebilen kısım: türü gerçekten desteklenmeyen dosya reddediliyor.
     */
    $sahte = UploadedFile::fake()->create('zararli.php', 10);

    expect($sahte->getMimeType())->toBe('application/x-php')
        ->and(fn () => app(ProductImageService::class)->yukle($urun, $sahte))
        ->toThrow(UnsupportedImageTypeException::class);

    expect(ProductImage::count())->toBe(0);
});

it('sıra yükleme sırasına göre veriliyor ve yeniden sıralanabiliyor', function () {
    markaKur('gorsel-g.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $servis = app(ProductImageService::class);

    $a = $servis->yukle($urun, ornekGorsel('a.png'));
    $b = $servis->yukle($urun, ornekGorsel('b.png'));
    $c = $servis->yukle($urun, ornekGorsel('c.png'));

    expect($servis->listele($urun)->pluck('uuid')->all())
        ->toBe([$a->uuid, $b->uuid, $c->uuid]);

    $servis->sirala($urun, [$c->uuid, $a->uuid, $b->uuid]);

    expect($servis->listele($urun)->pluck('uuid')->all())
        ->toBe([$c->uuid, $a->uuid, $b->uuid]);
});

it('silme DOSYAYI da kaldırıyor', function () {
    markaKur('gorsel-h.test');
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $servis = app(ProductImageService::class);
    $gorsel = $servis->yukle($urun, ornekGorsel());
    $yol = $gorsel->path;

    $servis->sil($gorsel);

    // Yalnızca satır silinseydi dosya diskte öksüz kalırdı.
    expect(Storage::disk('public')->exists($yol))->toBeFalse()
        ->and(ProductImage::count())->toBe(0);
});

it('başka ürünün görseli sıralamaya karıştırılamıyor', function () {
    markaKur('gorsel-i.test');
    $servis = app(ProductImageService::class);
    $urunServis = app(ProductService::class);

    $a = $urunServis->olustur(['title' => 'A']);
    $b = $urunServis->olustur(['title' => 'B']);
    $gorselB = $servis->yukle($b, ornekGorsel());

    // 1A.5 deseni: sorgu ürüne daraltılı, yabancı satır hiç girmiyor.
    $servis->sirala($a, [$gorselB->uuid]);

    expect($gorselB->refresh()->position)->toBe(0);
});

it('görsel ucu HTTP üzerinden çalışıyor', function () {
    $marka = markaKur('gorsel-j.test');
    $token = panelTokeni('gorsel-j.test', $marka['sahip']->email);
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    $this->withToken($token)
        ->post("http://gorsel-j.test/panel/products/{$urun->uuid}/images", [
            'image' => ornekGorsel(),
            'alt' => 'Kırmızı tişört önden',
        ])
        ->assertStatus(201)
        ->assertJsonPath('image.alt', 'Kırmızı tişört önden')
        ->assertJsonPath('image.position', 0);
});
