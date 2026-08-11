<?php

use App\Domain\Catalog\CategoryHasProductsException;
use App\Domain\Catalog\CategoryService;
use App\Domain\Catalog\InvalidVariantOptionsException;
use App\Domain\Catalog\OptionInUseException;
use App\Domain\Catalog\OptionService;
use App\Domain\Catalog\OptionsLockedException;
use App\Domain\Catalog\OptionValueInUseException;
use App\Domain\Catalog\ProductHasNoVariantsException;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\TooManyOptionsException;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\SettingsService;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Models\Option;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;

/*
| Ürün · eksen bağlama · varyant (1B-K1…K5).
|
| Bu bloğun asıl işi DOĞRULAMA. Eksen değerleri ürünün tanımıyla
| uyuşmazsa kayıt başarılı olur, stok o varyanta yazılır ve müşteri onu
| hiçbir zaman SEÇEMEZ — hata vermeden.
*/

/**
 * Renk(Kırmızı·Mavi) + Beden(S·M·L) eksenli bir mağaza kurar.
 *
 * @return array{renk: Option, beden: Option}
 */
function eksenleriKur(): array
{
    $servis = app(OptionService::class);

    $renk = $servis->olustur('Renk');
    foreach (['Kırmızı', 'Mavi'] as $d) {
        $servis->degerEkle($renk, $d);
    }

    $beden = $servis->olustur('Beden');
    foreach (['S', 'M', 'L'] as $d) {
        $servis->degerEkle($beden, $d);
    }

    return ['renk' => $renk, 'beden' => $beden];
}

it('ürün taslak doğuyor, KDV mağaza ayarından geliyor', function () {
    markaKur('urun-a.test');
    app(SettingsService::class)->yaz(SettingGroup::Tax, 'default_rate', 10);

    $urun = app(ProductService::class)->olustur(['title' => 'Basic Tişört']);

    expect($urun->status)->toBe(ProductStatus::Draft)
        ->and($urun->slug)->toBe('basic-tisort')
        // Kolonun varsayılanına bırakılsaydı "marka oranı değiştirince eski
        // ürünler ne olacak" belirsizliği doğardı; oran satırda YAZILI.
        ->and((float) $urun->tax_rate)->toBe(10.0);
});

it('aynı başlıklı ikinci ürün SONEK alıyor', function () {
    markaKur('urun-b.test');
    $servis = app(ProductService::class);

    // ⚠️ Eksende çakışmayı REDDEDİYORDUK, burada sonek ekliyoruz.
    // "Renk" ekseni iki kez tanımlanıyorsa bu bir hatadır; ama aynı
    // başlıklı iki ürün olağan ve markayı ad uydurmaya zorlamak anlamsız.
    expect($servis->olustur(['title' => 'Tişört'])->slug)->toBe('tisort')
        ->and($servis->olustur(['title' => 'Tişört'])->slug)->toBe('tisort-2');
});

it('varyantsız ürün SATIŞA ALINAMIYOR', function () {
    markaKur('urun-c.test');
    $servis = app(ProductService::class);
    $urun = $servis->olustur(['title' => 'Tişört']);

    // 1A.4'teki asimetrinin aynısı: taslakta serbest, yayında denetimli.
    expect(fn () => $servis->durumDegistir($urun, ProductStatus::Active))
        ->toThrow(ProductHasNoVariantsException::class);

    expect($urun->refresh()->status)->toBe(ProductStatus::Draft);
});

it('3ten fazla eksen bağlanamıyor', function () {
    markaKur('urun-d.test');
    $eksenServis = app(OptionService::class);
    $e = eksenleriKur();
    $boy = $eksenServis->olustur('Boy');
    $kumas = $eksenServis->olustur('Kumaş');

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);

    // Kombinatorik patlama sınırı (1B-K4). Shopify 10+ yıl 3 ile yaşadı.
    expect(fn () => app(ProductService::class)->eksenleriAyarla($urun, [$e['renk'], $e['beden'], $boy, $kumas]))
        ->toThrow(TooManyOptionsException::class);
});

it('★ varyant seçenekleri ürünün eksenleriyle uyuşmak ZORUNDA', function () {
    markaKur('urun-e.test');
    $e = eksenleriKur();
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    app(ProductService::class)->eksenleriAyarla($urun, [$e['renk'], $e['beden']]);
    $varyantlar = app(VariantService::class);

    $temel = ['sku' => 'TS-1', 'price' => 199.90, 'stock' => 5];

    // Üçünün de sonucu aynı olurdu: müşterinin SEÇEMEDİĞİ bir varyant.
    expect(fn () => $varyantlar->ekle($urun, $temel, ['renk' => 'kirmizi']))
        ->toThrow(InvalidVariantOptionsException::class)                       // eksik anahtar
        ->and(fn () => $varyantlar->ekle($urun, $temel, ['renk' => 'kirmizi', 'beden' => 'm', 'boy' => 'kisa']))
        ->toThrow(InvalidVariantOptionsException::class)                       // fazla anahtar
        ->and(fn () => $varyantlar->ekle($urun, $temel, ['renk' => 'turuncu', 'beden' => 'm']))
        ->toThrow(InvalidVariantOptionsException::class);                      // tanımsız değer

    expect(ProductVariant::count())->toBe(0);
});

it('★ ANAHTAR SIRASI farklı aynı kombinasyon ikinci kez eklenemiyor', function () {
    markaKur('urun-f.test');
    $e = eksenleriKur();
    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    app(ProductService::class)->eksenleriAyarla($urun, [$e['renk'], $e['beden']]);
    $varyantlar = app(VariantService::class);

    $varyantlar->ekle($urun, ['sku' => 'TS-1', 'price' => 199.90, 'stock' => 5], ['renk' => 'kirmizi', 'beden' => 'm']);

    /*
    | ⚠️ 1B-K5. `jsonb` anahtar sırasını normalize ettiği için UNIQUE
    | kısıtı bunu yakalıyor. Kolon `json` olsaydı YAKALAMAZDI — ölçüldü:
    |   jsonb: {"renk":"K","beden":"M"} = {"beden":"M","renk":"K"} → TRUE
    |   json : aynı karşılaştırma                                  → FALSE
    | Yakalanmasaydı "Kırmızı/M" seçen müşteri hangi stoğu düşürdüğünü
    | bilemezdi.
    */
    expect(fn () => $varyantlar->ekle($urun, ['sku' => 'TS-2', 'price' => 1, 'stock' => 1], ['beden' => 'm', 'renk' => 'kirmizi']))
        ->toThrow(QueryException::class);

    expect($urun->variants()->count())->toBe(1);
});

it('varyant varken eksenler DEĞİŞTİRİLEMİYOR', function () {
    markaKur('urun-g.test');
    $e = eksenleriKur();
    $servis = app(ProductService::class);
    $urun = $servis->olustur(['title' => 'Tişört']);
    $servis->eksenleriAyarla($urun, [$e['renk'], $e['beden']]);
    app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 199.90, 'stock' => 5], ['renk' => 'kirmizi', 'beden' => 'm']);

    // İzin verilseydi eldeki varyant eksik anahtarlı kalır, ürün
    // sayfasında seçilemez olur ve stok orada asılı kalırdı.
    expect(fn () => $servis->eksenleriAyarla($urun, [$e['renk']]))
        ->toThrow(OptionsLockedException::class);
});

it('toplu üretim tüm kombinasyonları açıyor, var olanı atlıyor', function () {
    markaKur('urun-h.test');
    $e = eksenleriKur();
    $servis = app(ProductService::class);
    $urun = $servis->olustur(['title' => 'Tişört']);
    $servis->eksenleriAyarla($urun, [$e['renk'], $e['beden']]);
    $varyantlar = app(VariantService::class);

    $varyantlar->ekle($urun, ['sku' => 'TS-EL', 'price' => 199.90, 'stock' => 5], ['renk' => 'kirmizi', 'beden' => 'm']);

    // 2 renk × 3 beden = 6; biri elle eklenmişti → 5 yeni.
    $uretilen = $varyantlar->tumKombinasyonlariUret($urun, ['price' => 249.90, 'stock' => 10], 'TS');

    expect($uretilen)->toHaveCount(5)
        ->and($urun->variants()->count())->toBe(6);
});

it('ürünün fiyatı EN DÜŞÜK satılabilir varyanttan türetiliyor', function () {
    markaKur('urun-i.test');
    $e = eksenleriKur();
    $servis = app(ProductService::class);
    $urun = $servis->olustur(['title' => 'Tişört']);
    $servis->eksenleriAyarla($urun, [$e['renk'], $e['beden']]);
    $varyantlar = app(VariantService::class);

    $varyantlar->ekle($urun, ['sku' => 'A', 'price' => 299.90, 'stock' => 5], ['renk' => 'kirmizi', 'beden' => 'm']);
    // Ucuz olan TÜKENMİŞ: gösterilirse müşteri seçemeyeceği bir fiyatla çağrılır.
    $varyantlar->ekle($urun, ['sku' => 'B', 'price' => 99.90, 'stock' => 0], ['renk' => 'mavi', 'beden' => 'm']);

    expect((float) $urun->load('variants')->enDusukFiyat())->toBe(299.90);
});

it('★ satılabilirlik TEK KAPIDAN geçiyor', function () {
    markaKur('urun-j.test');
    $servis = app(ProductService::class);
    $urun = $servis->olustur(['title' => 'Kitap']);

    // Tek seçenekli üründe bile varyant var, `options` boş (1B-K1).
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'KT-1', 'price' => 120, 'stock' => 3]);

    expect($varyant->options)->toBe([])
        ->and($varyant->satinAlinabilirMi())->toBeTrue();

    // 1D'de bu cevap `stock - rezerve > 0` olacak ve YALNIZCA orası değişecek.
    $varyant->update(['stock' => 0]);
    expect($varyant->satinAlinabilirMi())->toBeFalse();

    $varyant->update(['stock' => 3, 'is_active' => false]);
    expect($varyant->satinAlinabilirMi())->toBeFalse();

    $servis->durumDegistir($urun->refresh(), ProductStatus::Active);
    expect($urun->load('variants')->vitrindeGorunurMu())->toBeFalse();
});

it('kullanımdaki eksen ve değer SİLİNEMİYOR', function () {
    markaKur('urun-k.test');
    $e = eksenleriKur();
    $servis = app(ProductService::class);
    $urun = $servis->olustur(['title' => 'Tişört']);
    $servis->eksenleriAyarla($urun, [$e['renk'], $e['beden']]);
    app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 199.90, 'stock' => 5], ['renk' => 'kirmizi', 'beden' => 'm']);

    $eksenServis = app(OptionService::class);

    expect(fn () => $eksenServis->sil($e['renk']))->toThrow(OptionInUseException::class);

    $kirmizi = $e['renk']->values()->where('slug', 'kirmizi')->firstOrFail();
    expect(fn () => $eksenServis->degerSil($kirmizi))->toThrow(OptionValueInUseException::class);

    // Kullanılmayan değer silinebilmeli.
    $mavi = $e['renk']->values()->where('slug', 'mavi')->firstOrFail();
    $eksenServis->degerSil($mavi);
    expect($e['renk']->values()->count())->toBe(1);
});

it('içinde ürün olan kategori SİLİNEMİYOR', function () {
    markaKur('urun-l.test');
    $kategoriServis = app(CategoryService::class);
    $giyim = $kategoriServis->olustur('Giyim');
    app(ProductService::class)->olustur(['title' => 'Tişört'], $giyim);

    // nullOnDelete olsaydı ürünler sessizce kategorisiz kalır, menüden düşerdi.
    expect(fn () => $kategoriServis->sil($giyim))->toThrow(CategoryHasProductsException::class);
});

it('varyant BAŞKA ürünün altından yönetilemiyor', function () {
    $marka = markaKur('urun-m.test');
    $token = panelTokeni('urun-m.test', $marka['sahip']->email);
    $servis = app(ProductService::class);

    $a = $servis->olustur(['title' => 'A Ürünü']);
    $b = $servis->olustur(['title' => 'B Ürünü']);
    $varyant = app(VariantService::class)->ekle($a, ['sku' => 'A-1', 'price' => 10, 'stock' => 1]);

    // 1A.5 deseni: sorgu ürüne daraltılı, yabancı varyant sonuç kümesine
    // hiç girmiyor → 404.
    $this->withToken($token)
        ->deleteJson("http://urun-m.test/panel/products/{$b->uuid}/variants/{$varyant->uuid}")
        ->assertStatus(404);

    expect($varyant->fresh()?->deleted_at)->toBeNull();
});

it('panel cevabında cost_price VAR', function () {
    $marka = markaKur('urun-n.test');
    $token = panelTokeni('urun-n.test', $marka['sahip']->email);

    $cevap = $this->withToken($token)
        ->postJson('http://urun-n.test/panel/products', ['title' => 'Tişört'])
        ->assertStatus(201);

    $urunUuid = $cevap->json('product.uuid');

    guardOnbelleginiTemizle();
    $this->withToken($token)
        ->postJson("http://urun-n.test/panel/products/{$urunUuid}/variants", [
            'sku' => 'TS-1', 'price' => 199.90, 'cost_price' => 80, 'stock' => 5, 'options' => [],
        ])
        ->assertStatus(201)
        // ⚠️ Panelde görünür, vitrinde ASLA (1B.5'te kanıtlanacak).
        ->assertJsonPath('variant.cost_price', '80.00');
});

it('iki markanın ürünleri karışmıyor', function () {
    markaKur('urun-o.test');
    app(ProductService::class)->olustur(['title' => 'Tişört']);

    tenancy()->end();
    markaKur('urun-p.test');
    app(ProductService::class)->olustur(['title' => 'Tişört']);   // aynı slug, ayrı şema

    expect(Product::count())->toBe(1);
});
