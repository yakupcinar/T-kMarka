<?php

use App\Domain\Analytics\EventRecorder;
use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Settings\StorePublication;
use App\Enums\EventType;
use App\Enums\ProductStatus;
use App\Jobs\RecordEvent;
use App\Models\Event;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/*
| Olay kaydı (1F).
|
| ★ İKİ ASIL İDDİA:
|   1  olay DOĞRU MARKANIN şemasına yazılıyor  (M-2.4 · kuyruk tuzağı)
|   2  GERİ SARILAN transaction'ın olayı hiç kuyruğa girmiyor  (1F-K5)
|
| ⚠️ İkisi de sessiz arıza sınıfı: yanlış şemaya yazılan olay hata
| vermez, olmayan siparişin olayı da vermez.
*/

it('sepete ekleme olayı kuyruğa atılıyor', function () {
    Queue::fake();

    ['varyant' => $varyant] = odemeAsamasiSiparisi('olay-a.test');

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepet, $varyant, 2);

    Queue::assertPushed(RecordEvent::class);
});

it('★ olay DOĞRU MARKANIN şemasına yazılıyor', function () {
    ['varyant' => $a] = odemeAsamasiSiparisi('olay-b.test');

    $sepetA = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepetA, $a, 1);

    $aSayisi = Event::count();
    expect($aSayisi)->toBeGreaterThan(0);

    tenancy()->end();
    markaKur('olay-c.test');
    magazayiHazirla();

    /*
    | ⚠️ B markası HENÜZ HİÇBİR ŞEY YAPMADI — A'nın olayları burada
    | görünmemeli. Kuyruk işi kiracı kimliğini taşımasaydı iş merkez
    | bağlamda koşar ya da bir önceki markanın şemasına yazardı, ve
    | HATA VERMEZDİ (M-2.4 / 1).
    */
    expect(Event::count())->toBe(0);

    $urun = app(ProductService::class)->olustur(['title' => 'Kupa']);
    $vB = app(VariantService::class)->ekle($urun, ['sku' => 'KP-1', 'price' => 60, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sepetB = app(CartService::class)->misafirSepetiOlustur();
    app(CartService::class)->ekle($sepetB, $vB, 1);

    // B kendi olayını aldı; A'nınkiler B'ye sızmadı.
    expect(Event::count())->toBe(1)
        ->and(Event::firstOrFail()->payload['sku'] ?? null)->toBe('KP-1');
});

it('★ GERİ SARILAN transaction\'ın olayı KUYRUĞA GİRMİYOR', function () {
    /*
    | ★ 1F-K5'in sınavı — ve testin KENDİSİ iki kez yanlış yazıldı.
    |
    | ⚠️ 1. deneme `Queue::fake()` kullandı: sahte kuyruk işi doğrudan
    |    yakalayıp `afterCommit` mekanizmasını atlıyor.
    | ⚠️ 2. deneme veritabanına baktı: `sync` sürücüsünde iş transaction
    |    İÇİNDE koşuyor ve satır zaten birlikte geri sarılıyor — yani
    |    `afterCommit` kaldırılınca da test YEŞİL kalıyordu.
    |
    | Doğrusu GERÇEK kuyruğa bakmak: iş Redis'e girdi mi? Ölçülen şey
    | budur, çünkü canlıda iş oradan alınıp AYRI bir süreçte koşuyor.
    */
    config(['queue.default' => 'redis']);
    Redis::connection()->del('queues:default');

    markaKur('olay-d.test');
    magazayiHazirla();

    try {
        DB::transaction(function () {
            app(EventRecorder::class)->kaydet(EventType::OrderPlaced, ['order_id' => 1]);

            throw new RuntimeException('bilerek düşürüldü');
        });
    } catch (RuntimeException) {
        // beklenen
    }

    /*
    | ⚠️ Sipariş HİÇ VAR OLMADI. İş kuyruğa girseydi worker onu alır ve
    | olmayan bir siparişin `order_placed` olayını yazardı.
    */
    expect(Redis::connection()->llen('queues:default'))->toBe(0);
});

it('BAŞARILI transaction\'ın olayı KUYRUĞA GİRİYOR', function () {
    config(['queue.default' => 'redis']);
    Redis::connection()->del('queues:default');

    markaKur('olay-e.test');
    magazayiHazirla();

    // ⚠️ Simetrik sınav: `afterCommit` olayı YUTMUYOR, ERTELİYOR.
    DB::transaction(function () {
        app(EventRecorder::class)->kaydet(EventType::OrderPlaced, ['order_id' => 1]);
    });

    expect(Redis::connection()->llen('queues:default'))->toBe(1);

    // ⚠️ Ve iş KİRACI KİMLİĞİNİ taşıyor (M-2.4).
    $govde = json_decode((string) Redis::connection()->lpop('queues:default'), true);

    expect($govde['tenant_id'] ?? null)->not->toBeNull();
});

it('★ sipariş olayı yazılıyor ve KİŞİSEL VERİ TAŞIMIYOR', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('olay-f.test');

    $olay = Event::where('type', EventType::OrderPlaced)->firstOrFail();

    expect($olay->payload['order_number'] ?? null)->toBe($siparis->order_number)
        ->and($olay->payload['grand_total'] ?? null)->toBe($siparis->grand_total)
        ->and($olay->payload['item_count'] ?? null)->toBe(1);

    /*
    | ⚠️ 1F-K4: ad, e-posta, adres GİRMİYOR. Faz 2'de KVKK silme talebi
    | geliyor ve o iş kişisel alanları anonimleştirmek zorunda; `events`
    | tablosunu da taraması gerekirse iş iki katına çıkar.
    */
    $ham = json_encode($olay->payload);

    expect($ham)->not->toContain('ayse@ornek.test')
        ->and($ham)->not->toContain('Ayşe')
        ->and($ham)->not->toContain('Moda Cad');
});

it('★ ürün görüntüleme olayı UÇTAN yazılıyor, 404\'te YAZILMIYOR', function () {
    markaKur('olay-g.test');
    magazayiHazirla();
    app(StorePublication::class)->yayinla();

    $urun = app(ProductService::class)->olustur(['title' => 'Basic Tişört']);
    app(VariantService::class)->ekle($urun, ['sku' => 'TS-1', 'price' => 100, 'stock' => 5]);
    app(ProductService::class)->durumDegistir($urun->refresh(), ProductStatus::Active);

    $this->getJson('http://olay-g.test/api/products/basic-tisort')->assertOk();

    expect(Event::where('type', EventType::ProductViewed)->count())->toBe(1);

    /*
    | ⚠️ Bulunamayan üründe olay YOK: 404 alan istek görüntüleme sayılmaz,
    | yoksa bozuk bağlantılar raporu şişirirdi.
    */
    $this->getJson('http://olay-g.test/api/products/olmayan-urun')->assertNotFound();

    expect(Event::where('type', EventType::ProductViewed)->count())->toBe(1);
});

it('sepetten çıkarma olayı yazılıyor', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('olay-h.test');

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    $satir = app(CartService::class)->ekle($sepet, $varyant, 1);

    app(CartService::class)->satirSil($satir);

    expect(Event::where('type', EventType::CartItemRemoved)->count())->toBe(1);
});

it('★ olay kaydı SİPARİŞİ BOZMUYOR', function () {
    ['varyant' => $varyant] = odemeAsamasiSiparisi('olay-i.test');

    /*
    | ⚠️ 1F-K3. Kuyruk sürücüsü patlasa bile sepete ekleme çalışmalı:
    | olayın kaydedilmemesi kötü, sepete eklenememesi felaket.
    */
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('kuyruk yok'));

    $sepet = app(CartService::class)->misafirSepetiOlustur();
    $satir = app(CartService::class)->ekle($sepet, $varyant, 1);

    expect($satir->quantity)->toBe(1);
});

it('olayın ZAMANI olayın olduğu an — yazıldığı an değil', function () {
    Queue::fake();

    markaKur('olay-j.test');
    magazayiHazirla();

    $simdi = now();
    app(EventRecorder::class)->kaydet(EventType::ProductViewed, ['product_id' => 1]);

    /*
    | ⚠️ Kuyruk gecikirse olayın olduğu an ile yazıldığı an dakikalarca
    | ayrışıyor. `created_at` kullanılsaydı rapor yanlış saati gösterirdi.
    */
    Queue::assertPushed(RecordEvent::class, function (RecordEvent $is) use ($simdi) {
        $yansima = new ReflectionProperty($is, 'olusmaAni');

        /** @var CarbonInterface $an */
        $an = $yansima->getValue($is);

        return abs($an->diffInSeconds($simdi)) < 2;
    });
});

it('sipariş olayı MÜŞTERİYE bağlanıyor, misafirde boş kalıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('olay-k.test');

    $olay = Event::where('type', EventType::OrderPlaced)->firstOrFail();

    /*
    | ⚠️ Misafir siparişi: `customer_id` boş, `anon_id` de boş (1F-K2).
    | Misafiri tanımanın yolu vitrin teknolojisi seçilince belli olacak.
    */
    expect($siparis)->toBeInstanceOf(Order::class)
        ->and($olay->customer_id)->toBeNull()
        ->and($olay->anon_id)->toBeNull();
});
