<?php

use App\Domain\Catalog\OptionService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantSelector;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Enums\ProductStatus;
use App\Models\Product;

/*
| VİTRİNDE VARYANT SEÇİCİSİ (4.6A)
|
| ★ Önce TEK DÜZ AÇILIR LİSTE vardı: "Kırmızı · M — 100 TL". Müşteri iki
| ekseni birden okumak zorundaydı ve STOKTA OLMAYAN birleşimler de
| seçilebiliyordu — seçiyor, sepete ekliyor, hata alıyordu.
*/

/**
 * Eksenli, bazı birleşimleri tükenmiş ürün.
 *
 * ⚠️ Ad çakışması kontrol edildi (`grep -rn "function seciciUrunu" tests/`).
 */
function seciciUrunu(): Product
{
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $renk = eksenliDeger('Renk', ['Kırmızı', 'Mavi']);
    $beden = eksenliDeger('Beden', ['S', 'M']);

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört', 'brand' => 'Demo']);
    app(ProductService::class)->eksenleriAyarla($urun, [$renk, $beden]);

    $varyantlar = app(VariantService::class);

    foreach ([['kirmizi', 's', 5], ['kirmizi', 'm', 0], ['mavi', 's', 3], ['mavi', 'm', 4]] as [$r, $b, $stok]) {
        $varyantlar->ekle($urun->refresh(), [
            'sku' => "TS-{$r}-{$b}", 'price' => 100, 'stock' => $stok,
        ], ['renk' => $r, 'beden' => $b]);
    }

    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    return $urun->refresh();
}

it('★★★ EKSEN BASINA secim: her eksen ayri grup, degerler kutucuk', function () {
    $urun = seciciUrunu();

    $this->get("http://marka-a.test/urun/{$urun->slug}")
        ->assertOk()
        ->assertSee('data-eksen="renk"', escape: false)
        ->assertSee('data-eksen="beden"', escape: false)
        ->assertSee('data-deger="kirmizi"', escape: false)
        ->assertSee('data-deger="m"', escape: false)
        /*
        | ⚠️ Eski düz liste GİTMELİ: kalsaydı iki ayrı seçim yolu olur ve
        | biri stok kontrolü yapmazdı.
        */
        ->assertDontSee('<select name="variant_uuid"', escape: false);
});

it('★★★ TUKENEN birlesim SATILAMAZ isaretleniyor — sepetle AYNI kuraldan', function () {
    $urun = seciciUrunu();

    $veri = app(VariantSelector::class)->coz($urun->load(['variants', 'options.values']));

    $tukenen = collect($veri['varyantlar'])->where('satilabilir', false)->firstOrFail();

    /*
    | ⚠️ "Satılabilir" bilgisi `stock − committed` + aktiflik demek
    | (1D-K1) — ekran kendi hesabını yapsaydı müşteri BAĞLI stoğu seçer ve
    | sepete eklerken hata alırdı (4.5J'deki "iki formül" tuzağı).
    */
    expect(collect($veri['varyantlar'])->where('satilabilir', false)->count())->toBe(1)
        ->and($tukenen['secenekler']['renk'])->toBe('kirmizi');
});

it('★★★ BAGLI STOK da tukenmis sayiliyor — stock>0 yetmez', function () {
    $urun = seciciUrunu();

    $varyant = $urun->variants()->where('sku', 'TS-mavi-s')->firstOrFail();

    /*
    | ⚠️ Stok VAR ama ödemesi süren bir siparişe BAĞLI. Ekran `stock > 0`
    | baksaydı bu seçenek açık görünürdü.
    */
    $varyant->committed = $varyant->stock;
    $varyant->save();

    $veri = app(VariantSelector::class)->coz($urun->refresh()->load(['variants', 'options.values']));

    /*
    | ⚠️ BİRLEŞİMİN TAMAMI aranıyor. Yalnızca `renk = mavi` denseydi ilk
    | eşleşen mavi/M olurdu ve test yanlış satırı ölçerdi.
    */
    $satir = collect($veri['varyantlar'])
        ->firstOrFail(fn (array $v) => $v['secenekler'] === ['renk' => 'mavi', 'beden' => 's']);

    expect(collect($veri['varyantlar'])->where('satilabilir', false)->count())->toBe(2)
        ->and($satir['satilabilir'])->toBeFalse();
});

it('★★★ ESIGI ASAN eksen ACILIR LISTEYE dusuyor', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ Eşik SUNUCUDAN geliyor; ekranda sabit yazılsaydı eşik
    | değiştiğinde iki taraf ayrışırdı (4.5S'deki `maksEksen` kararı).
    */
    $degerler = range(1, VariantSelector::LISTE_ESIGI + 1);
    $beden = eksenliDeger('Beden', array_map(fn (int $n) => "Beden {$n}", $degerler));

    $urun = app(ProductService::class)->olustur(['title' => 'Çorap', 'brand' => 'Demo']);
    app(ProductService::class)->eksenleriAyarla($urun, [$beden]);

    foreach ($beden->values as $deger) {
        app(VariantService::class)->ekle($urun->refresh(), [
            'sku' => 'CR-'.$deger->slug, 'price' => 50, 'stock' => 3,
        ], ['beden' => $deger->slug]);
    }

    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $veri = app(VariantSelector::class)->coz($urun->refresh()->load(['variants', 'options.values']));

    expect($veri['eksenler'][0]['listeMi'])->toBeTrue();

    $this->get("http://marka-a.test/urun/{$urun->slug}")
        ->assertOk()
        ->assertSee('data-deger-liste', escape: false)
        ->assertDontSee('class="kutucuk"', escape: false);
});

it('★★★ EKSENSIZ urun BOZULMUYOR — gizli girdi ile calisiyor', function () {
    markaKur('marka-a.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Cüzdan', 'brand' => 'Demo']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'CZ-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    /*
    | ⚠️ Eksensiz ürün geçerli bir şey ve çoğunluk bu. Kutucuk mantığı onu
    | kapsam dışı bırakmalı, yoksa tek varyantlı ürünlerin sayfası bozulur.
    */
    $this->get("http://marka-a.test/urun/{$urun->slug}")
        ->assertOk()
        /*
        | ⚠️ Seçici betiği de basılmamalı: eksensiz sayfaya ölü kod
        | göndermenin anlamı yok ve "data-secici" metni testin ölçtüğü
        | şeyi bulanıklaştırıyordu.
        */
        ->assertDontSee('data-secici', escape: false)
        ->assertSee('value="'.$varyant->uuid.'"', escape: false);
});

it('★★ URETILMEMIS deger LISTEDE GORUNMUYOR — "yok" ile "tukendi" ayri', function () {
    $urun = seciciUrunu();

    /*
    | ⚠️ Eksende tanımlı ama bu üründe HİÇ varyantı olmayan değer
    | gösterilmemeli: müşteri hiç üretilmemiş bir bedeni görür ve neden
    | seçemediğini anlamazdı. "Stokta yok" ile "böyle bir şey yok" aynı
    | şey değil.
    */
    app(OptionService::class)->degerEkle(
        $urun->options()->where('slug', 'beden')->firstOrFail(),
        'XXL',
    );

    $veri = app(VariantSelector::class)->coz($urun->refresh()->load(['variants', 'options.values']));

    $bedenler = collect($veri['eksenler'])->firstOrFail(fn (array $e) => $e['slug'] === 'beden');

    expect(collect($bedenler['degerler'])->pluck('slug')->all())->toBe(['s', 'm']);
});
