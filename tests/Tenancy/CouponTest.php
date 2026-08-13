<?php

use App\Domain\Cart\CartService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Promotion\CouponCode;
use App\Domain\Promotion\CouponService;
use App\Domain\Promotion\InvalidCouponException;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\CouponType;
use App\Enums\LegalDocumentType;
use App\Enums\SettingGroup;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Kupon (2A).
|
| ★ DÖRT İDDİA:
|   1  kargo eşiği İNDİRİMDEN SONRAKİ tutara bakıyor (ayarlanabilir)
|   2  kota SATIR KİLİDİYLE harcanıyor, sepette değil siparişte
|   3  kupon kodu siparişe KOPYALANIYOR
|   4  Türkçe büyütme tuzağı kapalı: `indirim` → `INDIRIM`
*/

/**
 * Kupon oluşturur.
 *
 * @param  array<string, mixed>  $ek
 */
function kuponOlustur(string $kod, CouponType $tur, string $deger, array $ek = []): Coupon
{
    $kupon = new Coupon;
    $kupon->code = (string) CouponCode::normallestir($kod);
    $kupon->type = $tur;
    $kupon->value = $deger;

    foreach ($ek as $alan => $v) {
        $kupon->{$alan} = $v;
    }

    $kupon->save();

    return $kupon;
}

it('★ TÜRKÇE BÜYÜTME TUZAĞI kapalı — `indirim` → `INDIRIM`', function () {
    /*
    | ⚠️ `mb_strtoupper('indirim')` Türkçe yerelde `İNDİRİM` üretiyor.
    | Marka onu kaydeder, müşteri klavyeden `INDIRIM` yazar ve kupon
    | BULUNAMAZ — hata da vermez, "geçersiz kupon" der ve marka
    | kampanyasının neden tutmadığını anlayamaz. (1B'de ölçülmüştü.)
    */
    expect(CouponCode::normallestir('indirim'))->toBe('INDIRIM')
        ->and(CouponCode::normallestir('İNDİRİM'))->toBe('INDIRIM')
        ->and(CouponCode::normallestir('ıNDırım'))->toBe('INDIRIM')
        ->and(CouponCode::normallestir('yaz şenlik'))->toBe('YAZSENLIK')
        ->and(CouponCode::normallestir('  bahar-25 '))->toBe('BAHAR-25')
        ->and(CouponCode::normallestir('  '))->toBeNull();
});

it('★ VERİTABANI da bozuk kodu reddediyor', function () {
    markaKur('kupon-a.test');
    magazayiHazirla();

    /*
    | ⚠️ Uygulamadan kaçan tek satır bile bozuk kod yazamasın diye
    | `CHECK` kısıtı var — "unutmayı imkânsız kıl" deseni.
    */
    expect(function () {
        $kupon = new Coupon;
        $kupon->code = 'İndirim';
        $kupon->type = CouponType::Fixed;
        $kupon->value = '10';
        $kupon->save();
    })->toThrow(QueryException::class);
});

it('yüzde ve sabit indirim doğru hesaplanıyor', function () {
    ['siparis' => $s] = odemeAsamasiSiparisi('kupon-b.test');

    kuponOlustur('YUZDE20', CouponType::Percentage, '20');
    kuponOlustur('SABIT50', CouponType::Fixed, '50');

    $servis = app(CouponService::class);

    // 2 × 100 = 200 TL sepet
    expect($servis->etki('YUZDE20', '200.00')['discount'])->toBe('40.00')
        ->and($servis->etki('SABIT50', '200.00')['discount'])->toBe('50.00');
});

it('★ İNDİRİM SEPETTEN BÜYÜK OLAMIYOR', function () {
    markaKur('kupon-c.test');
    magazayiHazirla();

    kuponOlustur('BUYUK', CouponType::Fixed, '500');

    /*
    | ⚠️ Olsaydı `grand_total` eksiye düşer, sağlayıcıya negatif tutar
    | gider ve ödeme hiç başlatılamazdı.
    */
    expect(app(CouponService::class)->etki('BUYUK', '200.00')['discount'])->toBe('200.00');
});

it('★ KARGO EŞİĞİ İNDİRİMDEN SONRAKİ tutara bakıyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-d.test', stok: 20);

    // Eşik 500, kargo 49,90 (varsayılan ayarlar).
    kuponOlustur('YUZDE20', CouponType::Percentage, '20');

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 6);   // 600 TL

    app(CouponService::class)->uygula($sepet, 'YUZDE20', '600.00');

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    /*
    | ★ 2A-K1. 600 − %20 = 480 → eşiğin (500) ALTINDA → kargo VAR.
    |
    | ⚠️ Diğer sıra seçilseydi (eşiğe indirimsiz bak) müşteri hem indirim
    | hem bedava kargo alırdı — kuruş değil YÜZDE farkı.
    */
    expect($siparis->discount_total)->toBe('120.00')
        ->and($siparis->shipping_total)->toBe('49.90')
        ->and($siparis->grand_total)->toBe('529.90');
});

it('AYAR DEĞİŞİNCE eşik indirimsiz tutara bakıyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-e.test', stok: 20);

    app(SettingsService::class)->yaz(SettingGroup::Shipping, 'threshold_after_discount', false);
    kuponOlustur('YUZDE20', CouponType::Percentage, '20');

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 6);   // 600 TL

    app(CouponService::class)->uygula($sepet, 'YUZDE20', '600.00');

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    // 600 eşiği geçti → kargo YOK; indirim yine de uygulanıyor.
    expect($siparis->shipping_total)->toBe('0.00')
        ->and($siparis->grand_total)->toBe('480.00');
});

it('ücretsiz kargo kuponu ürün tutarına DOKUNMUYOR', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-f.test');

    kuponOlustur('KARGOBEDAVA', CouponType::FreeShipping, '0');

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);   // 200 TL

    app(CouponService::class)->uygula($sepet, 'KARGOBEDAVA', '200.00');

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    /*
    | ⚠️ İndirim gibi işlenseydi vergi hesabı bozulurdu: kargonun vergisi
    | ürün vergisinden ayrı hesaplanıyor (§8.2).
    */
    expect($siparis->discount_total)->toBe('0.00')
        ->and($siparis->shipping_total)->toBe('0.00')
        ->and($siparis->grand_total)->toBe('200.00');
});

it('★ KOTA SEPETTE DEĞİL SİPARİŞTE harcanıyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-g.test');

    $kupon = kuponOlustur('TEK', CouponType::Fixed, '10', ['max_uses' => 1]);

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    app(CouponService::class)->uygula($sepet, 'TEK', '200.00');

    /*
    | ⚠️ Sepette harcansaydı kuponu deneyip vazgeçen her müşteri kotadan
    | bir kullanım yer ve kampanya hiç satış olmadan tükenirdi.
    */
    expect($kupon->refresh()->used_count)->toBe(0);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    expect($kupon->refresh()->used_count)->toBe(1)
        ->and(CouponRedemption::count())->toBe(1);
});

it('★ KOTASI DOLMUŞ kupon uygulanmıyor', function () {
    ['siparis' => $s] = odemeAsamasiSiparisi('kupon-h.test');

    $kupon = kuponOlustur('DOLU', CouponType::Fixed, '10', ['max_uses' => 1]);
    $kupon->used_count = 1;
    $kupon->save();

    expect(fn () => app(CouponService::class)->dogrula($kupon->refresh(), '200.00'))
        ->toThrow(InvalidCouponException::class);
});

it('SÜRESİ ve ALT SINIR kontrol ediliyor', function () {
    markaKur('kupon-i.test');
    magazayiHazirla();

    $gecmis = kuponOlustur('GECMIS', CouponType::Fixed, '10', ['ends_at' => now()->subDay()]);
    $gelecek = kuponOlustur('GELECEK', CouponType::Fixed, '10', ['starts_at' => now()->addDay()]);
    $altSinir = kuponOlustur('ALTSINIR', CouponType::Fixed, '10', ['min_subtotal' => 300]);

    $servis = app(CouponService::class);

    expect(fn () => $servis->dogrula($gecmis, '500.00'))->toThrow(InvalidCouponException::class)
        ->and(fn () => $servis->dogrula($gelecek, '500.00'))->toThrow(InvalidCouponException::class)
        ->and(fn () => $servis->dogrula($altSinir, '200.00'))->toThrow(InvalidCouponException::class);

    // Alt sınırı geçince sorun yok.
    $servis->dogrula($altSinir, '300.00');

    expect(true)->toBeTrue();
});

it('★ KUPON KODU SİPARİŞE KOPYALANIYOR — kupon silinse de okunuyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-j.test');

    $kupon = kuponOlustur('YAZ25', CouponType::Fixed, '25');

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);
    app(CouponService::class)->uygula($sepet, 'yaz25', '200.00');

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    $siparis = app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    expect($siparis->coupon_code)->toBe('YAZ25')
        ->and($siparis->discount_total)->toBe('25.00');

    /*
    | ★ 2A-K4 — "Sipariş bir fotoğraftır" (1D). FK ile bağlansaydı silinen
    | kuponda geçmiş sipariş neyle indirildiğini söyleyemezdi.
    */
    CouponRedemption::query()->delete();
    $kupon->delete();

    expect($siparis->refresh()->coupon_code)->toBe('YAZ25');
});

it('★ SAYAÇ DENETİMİ tutarsızlığı yakalıyor ve ONARMIYOR', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-k.test');

    $kupon = kuponOlustur('SAYAC', CouponType::Fixed, '10');

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);
    app(CouponService::class)->uygula($sepet, 'SAYAC', '200.00');

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    expect(app(CouponService::class)->tutarsizliklar())->toBe([]);

    // Sayaç bozuldu.
    $kupon->refresh()->forceFill(['used_count' => 9])->save();

    /*
    | ⚠️ `committed` sayacındaki (1D.5) dersin aynısı: materyalleştirilmiş
    | sayının bedeli denetimdir. ONARMIYOR — onarsaydı sayacı hangi kod
    | yolunun bozduğu hiç görünmezdi.
    */
    $tutarsizlik = app(CouponService::class)->tutarsizliklar();

    expect($tutarsizlik)->toHaveCount(1)
        ->and($tutarsizlik[0]['code'])->toBe('SAYAC')
        ->and($kupon->refresh()->used_count)->toBe(9);
});

it('★ UÇTAN: kupon uygulanıyor ve kaldırılıyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-l.test');
    app(StorePublication::class)->yayinla();

    kuponOlustur('YUZDE10', CouponType::Percentage, '10');

    $token = $this->postJson('http://kupon-l.test/api/cart/items', [
        'variant_uuid' => $varyant->uuid, 'quantity' => 2,
    ])->assertStatus(201)->json('cart_token');

    $cevap = $this->withHeader('X-Cart-Token', $token)
        ->postJson('http://kupon-l.test/api/cart/coupon', ['code' => 'yuzde10'])
        ->assertOk();

    // ⚠️ Normalleştirilmiş kod dönüyor.
    expect($cevap->json('coupon.code'))->toBe('YUZDE10')
        ->and($cevap->json('discount'))->toBe('20.00');

    $this->withHeader('X-Cart-Token', $token)
        ->deleteJson('http://kupon-l.test/api/cart/coupon')->assertOk();
});

it('★ UÇTAN: geçersiz kupon 422 ve VARLIK bilgisi sızdırmıyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-m.test');
    app(StorePublication::class)->yayinla();

    kuponOlustur('GECMIS', CouponType::Fixed, '10', ['ends_at' => now()->subDay()]);

    $token = $this->postJson('http://kupon-m.test/api/cart/items', [
        'variant_uuid' => $varyant->uuid, 'quantity' => 1,
    ])->assertStatus(201)->json('cart_token');

    $yok = $this->withHeader('X-Cart-Token', $token)
        ->postJson('http://kupon-m.test/api/cart/coupon', ['code' => 'HICYOK'])
        ->assertStatus(422);

    $gecmis = $this->withHeader('X-Cart-Token', $token)
        ->postJson('http://kupon-m.test/api/cart/coupon', ['code' => 'GECMIS'])
        ->assertStatus(422);

    /*
    | ⚠️ İkisi de 422 ama mesajları FARKLI olabilir mi? "Yok" ile "süresi
    | geçmiş" ayrımı, kod deneyerek geçerli kupon aramanın kapısını açar.
    | Bu yüzden ikisi de kuponun VARLIĞI hakkında bilgi vermiyor.
    */
    expect($yok->json('message'))->not->toContain('süresi')
        ->and($gecmis->json('message'))->not->toContain('bulunamadı');
});

it('★ kupon tüketimi GERÇEKTEN "for update" içeriyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('kupon-p.test');

    kuponOlustur('KILIT', CouponType::Fixed, '10', ['max_uses' => 1]);

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);
    app(CouponService::class)->uygula($sepet, 'KILIT', '200.00');

    /*
    | ⚠️ BU TESTİN VARLIK SEBEBİ BİR BOŞLUK — 1D'dekinin aynısı.
    |
    | Ölçüldü: `lockForUpdate()` servisten silindiğinde diğer 14 testin
    | HİÇBİRİ kırılmıyor. Sıralı testler kilidi hiç zorlamıyor; kilit
    | yalnızca EŞZAMANLI iki istekte fark yaratıyor ve onu tek süreçte
    | üretmek zor.
    |
    | Bu yüzden davranış değil YAPI sınanıyor: üretilen SQL kilit
    | içeriyor mu? Olmasaydı son bir kullanımı kalan kupon, aynı anda
    | gelen iki siparişte iki kez kullanılırdı — hatasız (1D-K5).
    */
    DB::flushQueryLog();
    DB::enableQueryLog();

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);
    app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));

    $kilitli = collect(DB::getQueryLog())
        ->filter(fn (array $k) => str_contains(strtolower((string) $k['query']), 'from "coupons"'))
        ->contains(fn (array $k) => str_contains(strtolower((string) $k['query']), 'for update'));

    DB::disableQueryLog();

    expect($kilitli)->toBeTrue();
});

it('iki markanın kuponları karışmıyor', function () {
    markaKur('kupon-n.test');
    magazayiHazirla();
    kuponOlustur('ORTAK', CouponType::Fixed, '10');

    tenancy()->end();
    markaKur('kupon-o.test');
    magazayiHazirla();

    expect(Coupon::count())->toBe(0)
        ->and(fn () => app(CouponService::class)->bul('ORTAK'))->toThrow(InvalidCouponException::class);
});
