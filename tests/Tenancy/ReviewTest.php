<?php

use App\Domain\Order\CheckoutService;
use App\Domain\Order\FulfillmentService;
use App\Domain\Review\DuplicateReviewException;
use App\Domain\Review\NotPurchasedException;
use App\Domain\Review\RatingCounter;
use App\Domain\Review\ReviewService;
use App\Domain\Settings\StorePublication;
use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Ürün yorumları (2E).
|
| ★ ÜÇ İDDİA:
|   1  yalnızca TESLİM ALAN yazabilir       (2E-K1)
|   2  onaysız yorum vitrinde YOK           (2E-K2)
|   3  ortalama sayacı DENETLENİYOR         (2E-K3)
*/

/**
 * Yorum yazmaya hazır durum: müşteri ürünü almış ve TESLİM ALMIŞ.
 *
 * @return array{musteri: Customer, urun: Product, siparis: Order, marka: array<string, mixed>}
 */
function teslimAlmisMusteri(string $alanAdi): array
{
    $marka = markaKur($alanAdi);
    magazayiHazirla();

    $musteri = Customer::factory()->create(['email' => 'alici@ornek.test']);

    $hazir = odemeAsamasiSiparisiMusteriyle($alanAdi, $musteri);
    $siparis = $hazir['siparis'];

    app(CheckoutService::class)->odemeBasarili($siparis);

    /*
    | ⚠️ Yalnızca "ödendi" YETMİYOR — paket teslim edilmeli. Bu satır
    | kaldırılınca yorum yazma reddedilmeli; test bunu ayrıca ölçüyor.
    */
    $satir = $siparis->refresh()->items->firstOrFail();

    $paket = app(FulfillmentService::class)->olustur($siparis, [$satir->id => 1]);

    app(FulfillmentService::class)->kargoyaVer($paket);
    app(FulfillmentService::class)->teslimEdildi($paket->refresh());

    $urun = Product::firstOrFail();

    return ['musteri' => $musteri, 'urun' => $urun, 'siparis' => $siparis->refresh(), 'marka' => $marka];
}

it('★ TESLİM ALAN yazabiliyor, ALMAYAN yazamıyor', function () {
    $d = teslimAlmisMusteri('yor-a.test');

    $yorum = app(ReviewService::class)->yaz($d['musteri'], $d['urun'], [
        'rating' => 5,
        'body' => 'Çok beğendim.',
    ]);

    expect($yorum->status)->toBe(ReviewStatus::Pending);

    /*
    | ⚠️ Hiç sipariş vermemiş müşteri REDDEDİLİYOR. Kontrol olmasaydı
    | rakip ve bot yorumu kaçınılmazdı — hiçbiri hata vermeden.
    */
    $yabanci = Customer::factory()->create(['email' => 'yabanci@ornek.test']);

    expect(fn () => app(ReviewService::class)->yaz($yabanci, $d['urun'], [
        'rating' => 1,
        'body' => 'Kötü.',
    ]))->toThrow(NotPurchasedException::class);
});

it('★ ÖDEDİ ama TESLİM ALMADI — yine yazamıyor', function () {
    $marka = markaKur('yor-b.test');
    magazayiHazirla();

    $musteri = Customer::factory()->create(['email' => 'bekleyen@ornek.test']);
    $hazir = odemeAsamasiSiparisiMusteriyle('yor-b.test', $musteri);

    app(CheckoutService::class)->odemeBasarili($hazir['siparis']);

    /*
    | ★ "Ödendi" ile "eline geçti" AYRI ŞEYLER. Ödemeyle yetinilseydi
    | kargodaki ürün hakkında yorum yazılırdı — ürün deneyimi değil,
    | beklenti olurdu.
    */
    expect(fn () => app(ReviewService::class)->yaz($musteri, Product::firstOrFail(), [
        'rating' => 5,
        'body' => 'Henüz gelmedi ama iyi görünüyor.',
    ]))->toThrow(NotPurchasedException::class);
});

it('★ ONAYSIZ yorum VİTRİNDE YOK ve ortalamaya GİRMİYOR', function () {
    $d = teslimAlmisMusteri('yor-c.test');

    app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 5, 'body' => 'Harika.']);

    /*
    | ⚠️ İkisi AYRI iddia ve ikisi de sessiz bozulabilir: yorum vitrinde
    | görünmezken puanı çoktan etkilemiş olsaydı moderasyonun anlamı
    | kalmazdı.
    |
    | ⚠️ SAYAÇ AÇIKÇA TAZELENİYOR — bu satır olmadan test yanlış şeyi
    | ölçüyordu: sayaç zaten 0'dı çünkü yazma sırasında hiç tazeleme
    | yapılmıyor. Kırma denemesi ("bekleyenler de sayılsın") testi
    | düşürmedi ve yakalandı.
    */
    app(RatingCounter::class)->tazele($d['urun']);

    expect(app(ReviewService::class)->vitrindeGorunenler($d['urun'])->count())->toBe(0)
        ->and($d['urun']->refresh()->rating_count)->toBe(0)
        ->and($d['urun']->rating_avg)->toBeNull();
});

it('★ ONAY ortalamayı YAZIYOR, RED geri ALIYOR', function () {
    $d = teslimAlmisMusteri('yor-d.test');
    $personel = User::firstOrFail();

    $yorum = app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 4, 'body' => 'İyi.']);

    app(ReviewService::class)->onayla($yorum, $personel);

    expect($d['urun']->refresh()->rating_count)->toBe(1)
        ->and((string) $d['urun']->rating_avg)->toBe('4.00');

    /*
    | ★ GERİ ALMA — burası kolayca unutulur. Yalnızca onayda tazeleme
    | yazılsaydı reddedilen yorum ortalamada kalır, puan sessizce şişik
    | görünürdü.
    */
    app(ReviewService::class)->reddet($yorum->refresh(), $personel, 'Kişisel veri içeriyor.');

    expect($d['urun']->refresh()->rating_count)->toBe(0)
        ->and($d['urun']->rating_avg)->toBeNull();
});

it('★ SAYAÇ DENETİMİ bozulmayı yakalıyor — onarmıyor', function () {
    $d = teslimAlmisMusteri('yor-e.test');
    $personel = User::firstOrFail();

    $yorum = app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 3, 'body' => 'Ortalama.']);
    app(ReviewService::class)->onayla($yorum, $personel);

    $sayac = app(RatingCounter::class);

    expect($sayac->tutarsizliklar())->toBe([]);

    /*
    | ⚠️ Sayacı BİLEREK bozuyoruz — `committed` denetiminin (1D-K1)
    | aynısı. Denetim bunu yakalamazsa vitrinde yanlış puan görünmeye
    | devam eder ve HATA VERMEZ.
    */
    DB::table('products')->where('id', $d['urun']->id)->update(['rating_count' => 9, 'rating_avg' => '5.00']);

    $tutarsiz = $sayac->tutarsizliklar();

    expect($tutarsiz)->toHaveCount(1)
        ->and($tutarsiz[0]['rating_count'])->toBe(9)
        ->and($tutarsiz[0]['gercek_adet'])->toBe(1);

    // ⚠️ Denetim ONARMIYOR: değer hâlâ bozuk.
    expect($d['urun']->refresh()->rating_count)->toBe(9);
});

it('★ YORUMSUZ ürünün bozulması da yakalanıyor — null tuzağı', function () {
    $d = teslimAlmisMusteri('yor-f.test');

    /*
    | ⚠️ `<>` kullanılsaydı `null <> null` sonucu `null` olur ve bu satır
    | "farklı" sayılmazdı: yorumu olmayan ürünlerdeki bozukluk sessizce
    | denetimden kaçardı. `IS DISTINCT FROM` bu yüzden.
    */
    DB::table('products')->where('id', $d['urun']->id)->update(['rating_avg' => '5.00', 'rating_count' => 0]);

    expect(app(RatingCounter::class)->tutarsizliklar())->toHaveCount(1);
});

it('★ AYNI ÜRÜNE ikinci yorum yazılamıyor — silinmişi de sayılıyor', function () {
    $d = teslimAlmisMusteri('yor-g.test');

    $yorum = app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 5, 'body' => 'Güzel.']);

    expect(fn () => app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 1, 'body' => 'Fikrim değişti.']))
        ->toThrow(DuplicateReviewException::class);

    /*
    | ★ SİLİNMİŞ YORUM DA SAYILIYOR. Sayılmasaydı müşteri yorumunu silip
    | yenisini yazarak kotayı sonsuz kullanırdı — ve veritabanı kısıtı
    | `deleted_at`'e bakmadığı için istek 500 ile düşerdi.
    */
    app(ReviewService::class)->sil($yorum);

    expect(fn () => app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 1, 'body' => 'Tekrar.']))
        ->toThrow(DuplicateReviewException::class);
});

it('★ ONAYLI yorum SİLİNİNCE ortalama düşüyor', function () {
    $d = teslimAlmisMusteri('yor-h.test');

    $yorum = app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 5, 'body' => 'Süper.']);
    app(ReviewService::class)->onayla($yorum, User::firstOrFail());

    expect($d['urun']->refresh()->rating_count)->toBe(1);

    app(ReviewService::class)->sil($yorum->refresh());

    expect($d['urun']->refresh()->rating_count)->toBe(0)
        ->and($d['urun']->rating_avg)->toBeNull();
});

it('★ PUAN ARALIĞI veritabanında da kısıtlı', function () {
    $d = teslimAlmisMusteri('yor-i.test');

    /*
    | ⚠️ Yalnızca uygulamada doğrulansaydı tohumlayıcı, artisan komutu ya
    | da elle yazılan bir satır 7 yıldızlı yorum sokabilir ve ortalama
    | sessizce bozulurdu.
    */
    expect(fn () => DB::table('reviews')->insert([
        'uuid' => (string) Str::uuid(),
        'product_id' => $d['urun']->id,
        'customer_id' => $d['musteri']->id,
        'order_item_id' => OrderItem::firstOrFail()->id,
        'rating' => 7,
        'body' => 'Yedi yıldız.',
        'status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('★ UÇTAN: müşteri yazıyor, personel onaylıyor, vitrin gösteriyor', function () {
    $d = teslimAlmisMusteri('yor-j.test');
    app(StorePublication::class)->yayinla();

    $musteriToken = $d['musteri']->createToken('test')->plainTextToken;
    $slug = $d['urun']->slug;

    // 1 — Müşteri yorum yazıyor.
    $yaz = $this->withToken($musteriToken)
        ->postJson("http://yor-j.test/api/products/{$slug}/reviews", [
            'rating' => 5,
            'body' => 'Kumaşı çok iyi.',
        ])->assertCreated();

    expect($yaz->json('review.status'))->toBe('pending');

    // 2 — Vitrinde HENÜZ YOK.
    $once = $this->getJson("http://yor-j.test/api/products/{$slug}/reviews")->assertOk();

    expect($once->json('meta.total'))->toBe(0)
        ->and($once->json('rating.count'))->toBe(0);

    /*
    | ⚠️ `uuid` UÇTAN okunuyor, modelden değil: panel bu değeri nereden
    | bulacak sorusunu sormayan test iki ölü uç kaçırmıştı (1D.6).
    */
    guardOnbelleginiTemizle();
    $token = panelTokeni('yor-j.test', $d['marka']['sahip']->email);

    $kuyruk = $this->withToken($token)->getJson('http://yor-j.test/panel/reviews')->assertOk();

    expect($kuyruk->json('meta.total'))->toBe(1);

    $uuid = $kuyruk->json('reviews.0.uuid');

    // 3 — Personel onaylıyor.
    $this->withToken($token)->postJson("http://yor-j.test/panel/reviews/{$uuid}/approve")->assertOk();

    // 4 — Vitrinde ARTIK VAR, puan da yazılmış.
    $sonra = $this->getJson("http://yor-j.test/api/products/{$slug}/reviews")->assertOk();

    expect($sonra->json('meta.total'))->toBe(1)
        ->and($sonra->json('rating.count'))->toBe(1)
        ->and((string) $sonra->json('rating.average'))->toBe('5.00')
        ->and($sonra->json('reviews.0.body'))->toBe('Kumaşı çok iyi.');
});

it('★ VİTRİNDE tam ad ve moderasyon notu YOK', function () {
    $d = teslimAlmisMusteri('yor-k.test');
    app(StorePublication::class)->yayinla();

    $d['musteri']->name = 'Ahmet Yılmaz';
    $d['musteri']->save();

    $yorum = app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 4, 'body' => 'Fena değil.']);
    app(ReviewService::class)->onayla($yorum, User::firstOrFail());

    $cevap = $this->getJson("http://yor-k.test/api/products/{$d['urun']->slug}/reviews")->assertOk();

    /*
    | ⚠️ Tam ad yazılsaydı müşterinin kim olduğu vitrinde herkese açık
    | olurdu; `moderation_note` çıksaydı markanın iç notu sızardı.
    */
    expect($cevap->json('reviews.0.author'))->toBe('Ahmet Y.')
        ->and($cevap->json('reviews.0'))->not->toHaveKey('moderation_note')
        ->and(json_encode($cevap->json()))->not->toContain('alici@ornek.test');
});

it('★ MİSAFİR yorum yazamıyor — 401', function () {
    $d = teslimAlmisMusteri('yor-l.test');
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ Bu bir SINIR, gizlenmiyor: misafir siparişte kimlik yok, "bu kişi
    | gerçekten aldı mı" sorusu cevaplanamaz.
    */
    $this->postJson("http://yor-l.test/api/products/{$d['urun']->slug}/reviews", [
        'rating' => 5,
        'body' => 'Misafirim ama yazayım.',
    ])->assertUnauthorized();
});

it('★ SATIN ALMAYAN uçtan 403 alıyor — 500 değil', function () {
    $d = teslimAlmisMusteri('yor-m.test');
    app(StorePublication::class)->yayinla();

    $yabanci = Customer::factory()->create(['email' => 'gecen@ornek.test']);
    $token = $yabanci->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson("http://yor-m.test/api/products/{$d['urun']->slug}/reviews", [
            'rating' => 5,
            'body' => 'Almadım ama iyidir.',
        ])->assertForbidden();
});

it('iki markanın yorumları karışmıyor', function () {
    $d = teslimAlmisMusteri('yor-n.test');
    app(ReviewService::class)->yaz($d['musteri'], $d['urun'], ['rating' => 5, 'body' => 'Güzel.']);

    tenancy()->end();
    markaKur('yor-o.test');

    expect(Review::count())->toBe(0);
});
