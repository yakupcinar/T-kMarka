<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Payment\FakePaymentProvider;
use App\Domain\Payment\PaymentProviderFactory;
use App\Domain\Payment\PaymentRequest;
use App\Domain\Payment\UnknownPaymentProviderException;
use App\Domain\Settings\SettingsService;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentAttemptStatus;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Database\QueryException;

/*
| Ödeme altyapısı (1E.1).
|
| ★ Bu bloğun iddiası "ödeme alabiliyoruz" DEĞİL — ödeme uçları 1E.3/1E.4'te.
| Buradaki iddia şu: AYNI ÖDEMEYİ İKİ KEZ İŞLEMEK İMKÂNSIZ ve İMZASIZ
| BİLDİRİM GEÇERSİZ.
|
| İkisi de sessiz arıza sınıfı: tekrar işlenen webhook stoğu iki kez
| düşürür, sahte bildirim bedava sipariş üretir — hiçbiri hata vermez.
*/

/** Ödenmemiş (pending) tek satırlık bir sipariş üretir. */
function odemelikSiparis(string $alanAdi): Order
{
    markaKur($alanAdi);
    magazayiHazirla();

    $urun = app(ProductService::class)->olustur(['title' => 'Tişört']);
    $varyant = app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 100, 'stock' => 10]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    return app(CheckoutService::class)->baslat($sepet, odemeVerisi((int) $sozlesme?->id));
}

/** Doğrudan ödeme denemesi satırı açar — servis 1E.3'te gelecek. */
function odemeDenemesi(Order $siparis, ?string $referans, string $anahtar): Payment
{
    $odeme = new Payment;
    $odeme->order()->associate($siparis);
    $odeme->provider = 'fake';
    $odeme->provider_ref = $referans;
    $odeme->idempotency_key = $anahtar;
    $odeme->amount = $siparis->grand_total;
    $odeme->save();

    return $odeme;
}

it('★ AYNI SAĞLAYICI REFERANSI İKİ KEZ KAYDEDİLEMİYOR', function () {
    $siparis = odemelikSiparis('odeme-a.test');

    odemeDenemesi($siparis, 'FAKE-1', 'TM-1');

    /*
    | ⚠️ 1E-K3'ün kalbi. Webhook teslimi "en az bir kez"dir: iyzico ilk
    | bildirimi 10-15 sn sonra atıyor, 2xx alamazsa 15 dk arayla 3 kez
    | daha. Uygulamada "işledim mi" kontrolü YARIŞI ÇÖZMEZ — iki istek
    | aynı anda bakar, ikisi de bulamaz, ikisi de stoğu düşürür.
    |
    | Kısıt olmasaydı bu satır sessizce geçerdi.
    */
    expect(fn () => odemeDenemesi($siparis, 'FAKE-1', 'TM-2'))
        ->toThrow(QueryException::class);
});

it('YARIM KALAN denemeler birbirini engellemiyor (NULL referans)', function () {
    $siparis = odemelikSiparis('odeme-b.test');

    /*
    | ⚠️ Deneme kaydı sağlayıcıya istek GİTMEDEN açılıyor; referans
    | cevapla geliyor. PostgreSQL'de NULL ≠ NULL olduğu için iki yarım
    | deneme UNIQUE'e takılmıyor — istenen davranış bu.
    */
    odemeDenemesi($siparis, null, 'TM-1');
    odemeDenemesi($siparis, null, 'TM-2');

    expect(Payment::whereNull('provider_ref')->count())->toBe(2);
});

it('★ AYNI İDEMPOTANSLIK ANAHTARIYLA ikinci deneme açılamıyor', function () {
    $siparis = odemelikSiparis('odeme-c.test');

    odemeDenemesi($siparis, 'FAKE-1', $siparis->order_number);

    /*
    | ⚠️ Bu, referans kısıtından FARKLI bir problem: müşteri "öde"ye iki
    | kez basınca sağlayıcı iki AYRI işlem numarası üretir ve referans
    | kısıtı hiçbir şey yakalamaz. Korunan taraf burada GİDEN taraf.
    */
    expect(fn () => odemeDenemesi($siparis, 'FAKE-2', $siparis->order_number))
        ->toThrow(QueryException::class);
});

it('ödeme denemesi pending doğuyor (kolon varsayılanı modele ulaşmıyor)', function () {
    $siparis = odemelikSiparis('odeme-d.test');

    $odeme = odemeDenemesi($siparis, 'FAKE-1', 'TM-1');

    // ⚠️ `$attributes` olmasaydı burası null okurdu — CLAUDE.md'deki tuzak.
    expect($odeme->status)->toBe(PaymentAttemptStatus::Pending)
        ->and($odeme->tahsilEdildiMi())->toBeFalse();
});

it('★ İMZASIZ ve BOZULMUŞ bildirim REDDEDİLİYOR', function () {
    markaKur('odeme-e.test');
    magazayiHazirla();

    $saglayici = app(FakePaymentProvider::class);
    ['yuk' => $yuk, 'imza' => $imza] = $saglayici->bildirim('TM-2026-000001', 'FAKE-1', '200.00');

    expect($saglayici->webhookuDogrula($yuk, $imza))->toBeTrue()
        ->and($saglayici->webhookuDogrula($yuk, null))->toBeFalse()
        ->and($saglayici->webhookuDogrula($yuk, 'uydurma'))->toBeFalse();

    /*
    | ⚠️ ASIL SINAV: tutarı değiştirip ESKİ imzayı kullanmak.
    |
    | İmza yalnızca varlığı doğrulasaydı (örneğin sabit bir jeton) bu
    | geçerdi ve saldırgan 200 TL'lik siparişi 1 TL'ye ödettirirdi.
    */
    $yuk['amount'] = '1.00';
    expect($saglayici->webhookuDogrula($yuk, $imza))->toBeFalse();
});

it('★ BİR MARKANIN bildirimi DİĞERİNDE geçersiz', function () {
    markaKur('odeme-f.test');
    magazayiHazirla();

    ['yuk' => $yuk, 'imza' => $imza] = app(FakePaymentProvider::class)
        ->bildirim('TM-2026-000001', 'FAKE-1', '200.00');

    tenancy()->end();
    markaKur('odeme-g.test');
    magazayiHazirla();

    /*
    | ⚠️ Gizli anahtar MARKA BAŞINA rastgele. Sabit olsaydı A markasının
    | ürettiği geçerli bildirim B'de de kabul edilirdi ve imza aslında
    | hiçbir şey korumazdı.
    */
    expect(app(FakePaymentProvider::class)->webhookuDogrula($yuk, $imza))->toBeFalse();
});

it('gizli anahtar ŞİFRELİ saklanıyor', function () {
    markaKur('odeme-h.test');
    magazayiHazirla();

    $satir = Setting::where('group', SettingGroup::Payment->value)
        ->where('key', FakePaymentProvider::GIZLI_ANAHTAR)
        ->firstOrFail();

    // ⚠️ Ham kolonda düz metin durmamalı — yedeği gören geçerli bildirim üretemesin.
    $ham = (string) $satir->getRawOriginal('value');
    $cozulmus = app(SettingsService::class)->al(SettingGroup::Payment, FakePaymentProvider::GIZLI_ANAHTAR);

    expect($satir->is_encrypted)->toBeTrue()
        ->and($cozulmus)->toBeString()
        ->and($ham)->not->toContain((string) $cozulmus);
});

it('başlatma yönlendirme adresi ve referans üretiyor', function () {
    markaKur('odeme-i.test');
    magazayiHazirla();

    $sonuc = app(FakePaymentProvider::class)->baslat(new PaymentRequest(
        siparisNumarasi: 'TM-2026-000001',
        tutar: '200.00',
        eposta: 'misafir@ornek.test',
        idempotanslikAnahtari: 'TM-2026-000001',
        donusAdresi: 'http://odeme-i.test/odeme/donus',
    ));

    /*
    | ⚠️ Referans sipariş numarasından TÜRETİLMİYOR. Türetilseydi testler
    | onu tahmin eder ve idempotanslık sınavı gerçekte olmayan bir
    | kolaylıkla geçerdi.
    */
    expect($sonuc->saglayiciReferansi)->toStartWith('FAKE-')
        ->and($sonuc->saglayiciReferansi)->not->toContain('TM-2026-000001')
        ->and($sonuc->yonlendirmeAdresi)->toContain($sonuc->saglayiciReferansi);
});

it('AYNI bildirim defalarca üretilebiliyor — tekrar teslim sınanabilsin', function () {
    markaKur('odeme-j.test');
    magazayiHazirla();

    $saglayici = app(FakePaymentProvider::class);

    $ilk = $saglayici->bildirim('TM-2026-000001', 'FAKE-1', '200.00');
    $ikinci = $saglayici->bildirim('TM-2026-000001', 'FAKE-1', '200.00');

    // ⚠️ 1E.4 bu eşitliğe yaslanacak: aynı yük, aynı imza, üç kez gönderim.
    expect($ikinci)->toBe($ilk);
});

it('başarısız bildirim çözülünce hata kodu taşıyor', function () {
    markaKur('odeme-k.test');
    magazayiHazirla();

    $saglayici = app(FakePaymentProvider::class);
    ['yuk' => $yuk] = $saglayici->bildirim('TM-2026-000001', 'FAKE-1', '200.00', basarili: false);

    $sonuc = $saglayici->webhookuCoz($yuk);

    expect($sonuc->basarili)->toBeFalse()
        ->and($sonuc->hataKodu)->toBe('declined')
        ->and($sonuc->siparisNumarasi)->toBe('TM-2026-000001')
        ->and($sonuc->tutar)->toBe('200.00');
});

it('★ TANINMAYAN sağlayıcı adı GÜRÜLTÜLÜ patlıyor', function () {
    markaKur('odeme-l.test');
    magazayiHazirla();

    expect(app(PaymentProviderFactory::class)->coz())
        ->toBeInstanceOf(FakePaymentProvider::class);

    // Canlıda `iyzico` yerine `iyziko` yazılan tek harf.
    app(SettingsService::class)->yaz(SettingGroup::Payment, 'provider', 'iyziko');

    /*
    | ⚠️ Sessizce sahteye düşseydi bütün siparişler "ödendi" görünür ve
    | hiç para tahsil edilmezdi.
    */
    expect(fn () => app(PaymentProviderFactory::class)->coz())
        ->toThrow(UnknownPaymentProviderException::class);
});
