<?php

use App\Domain\Cart\CartService;
use App\Domain\Catalog\ProductService;
use App\Domain\Catalog\VariantService;
use App\Domain\Identity\RoleService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Order\CheckoutService;
use App\Domain\Returns\ReturnService;
use App\Enums\LegalDocumentType;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;

/*
| PANEL SİPARİŞ VE KARGO EKRANLARI (4E)
|
| ★ BU BLOĞUN İDDİASI: yetki ÜÇ KATMANLI ve arayüz onu BOZMUYOR.
|
|   order.view     görebilir
|   order.fulfill  kargolayabilir
|   order.refund   para iadesi yapabilir
|
| ⚠️ Tek izne indirgemek en kolay yoldu ve depo personeline para iadesi
| yetkisi vermek demekti.
*/

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Belirli izinlere sahip personel — SAHİP DEĞİL (sahip her şeyi yapabilir).
 *
 * @param  list<string>  $izinler
 */
function izinliPersonel(array $izinler, string $eposta = 'personel@marka-a.test'): User
{
    $rol = app(RoleService::class)->olustur('Rol-'.uniqid(), $izinler);

    $personel = User::factory()->create(['email' => $eposta, 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    return $personel->refresh();
}

it('★ siparis listesi aciliyor ve sevkiyatlik siparisi gosteriyor', function () {
    $siparis = sevkiyatlikSiparis('marka-a.test');
    $personel = izinliPersonel([Permission::OrderView->value]);

    $cevap = $this->actingAs($personel, 'staff-web')->get('http://marka-a.test/yonetim/siparisler');

    $cevap->assertOk();

    $sayfa = inertiaVerisi($cevap->getContent());

    expect($sayfa['component'])->toBe('Siparisler/Liste')
        ->and($sayfa['props']['siparisler']['data'][0]['order_number'])->toBe($siparis->order_number);
});

it('★★ SADECE order.view olan personel KARGOLAYAMIYOR — 403', function () {
    $siparis = sevkiyatlikSiparis('marka-a.test');
    $personel = izinliPersonel([Permission::OrderView->value]);

    $satir = $siparis->items->firstOrFail();

    /*
    | ★ BU BLOĞUN EN ÖNEMLİ TESTİ. Arayüzde "Paket oluştur" bölümü bu
    | kullanıcıya çizilmiyor — ama o bir KOLAYLIK. Adresi elle çağırırsa
    | sunucu durdurmalı.
    */
    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparis->uuid}/paketler", [
            'items' => [['order_item_id' => $satir->id, 'quantity' => 1]],
        ])
        ->assertForbidden();

    expect(Fulfillment::count())->toBe(0);
});

it('★★ order.fulfill olan personel KARGOLAYABILIYOR', function () {
    $siparis = sevkiyatlikSiparis('marka-a.test');
    $personel = izinliPersonel([Permission::OrderView->value, Permission::OrderFulfill->value]);

    $satir = $siparis->items->firstOrFail();

    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparis->uuid}/paketler", [
            'items' => [['order_item_id' => $satir->id, 'quantity' => 1]],
            'carrier' => 'Test Kargo',
        ])
        ->assertRedirect();

    expect(Fulfillment::count())->toBe(1);
});

it('★★ ASIRI SEVKIYAT panelden de engelleniyor', function () {
    $siparis = sevkiyatlikSiparis('marka-a.test');
    $personel = izinliPersonel([Permission::OrderView->value, Permission::OrderFulfill->value]);

    $satir = $siparis->items->firstOrFail();

    /*
    | ⚠️ Kural SERVİSTE (1D), controller'da tekrarlanmıyor. Tekrarlansaydı
    | iki yerden biri güncellenmeden kalır ve panelden sipariş edilenden
    | fazlası kargolanabilirdi.
    */
    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparis->uuid}/paketler", [
            'items' => [['order_item_id' => $satir->id, 'quantity' => $satir->quantity + 5]],
        ])
        ->assertStatus(422);

    expect(Fulfillment::count())->toBe(0);
});

it('★★ BASKA SIPARISIN paketi bu siparis uzerinden kargolanamiyor', function () {
    /*
    | ⚠️ İKİ SİPARİŞ AYNI MARKADA. `sevkiyatlikSiparis()` her çağrıda YENİ
    | marka kuruyor; iki kez çağırmak "alan adı başka kiracıda" hatası
    | veriyor. İkinci sipariş aynı kiracı bağlamında elle üretiliyor.
    */
    $a = sevkiyatlikSiparis('marka-a.test');
    $b = ikinciSiparis();

    $personel = izinliPersonel([Permission::OrderView->value, Permission::OrderFulfill->value]);

    // B siparişinde bir paket oluştur.
    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$b->uuid}/paketler", [
            'items' => [['order_item_id' => $b->items->firstOrFail()->id, 'quantity' => 1]],
        ])->assertRedirect();

    /** @var Fulfillment $bninPaketi */
    $bninPaketi = Fulfillment::query()->latest('id')->firstOrFail();

    /*
    | ⚠️ 1A.5 deseni: paket SİPARİŞE DARALTILMIŞ doğrulanıyor. İç içe rota
    | kapsaması bilerek kapalı (4D-K3) — koruma görünür ve ölçülebilir olsun.
    */
    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$a->uuid}/paketler/{$bninPaketi->uuid}/kargo")
        ->assertNotFound();
});

/** AÇIK kiracı bağlamında ikinci bir ödenmiş sipariş üretir. */
function ikinciSiparis(): Order
{
    $urunler = app(ProductService::class);
    $varyantlar = app(VariantService::class);

    $urun = $urunler->olustur(['title' => 'Ikinci Urun']);
    $varyant = $varyantlar->ekle($urun, ['sku' => 'IK-1', 'price' => 70, 'stock' => 10]);
    $urunler->durumDegistir($urun->refresh(), ProductStatus::Active);

    $sepetler = app(CartService::class);
    $sepet = $sepetler->misafirSepetiOlustur();
    $sepetler->ekle($sepet, $varyant, 2);

    $sozlesme = app(LegalDocumentService::class)
        ->guncelSurum(LegalDocumentType::DistanceSales);

    $odeme = app(CheckoutService::class);

    return $odeme->odemeBasarili($odeme->baslat($sepet, odemeVerisi((int) $sozlesme?->id)));
}

it('★ KARGOYA VERME ve TESLIM zinciri calisiyor', function () {
    $siparis = sevkiyatlikSiparis('marka-a.test');
    $personel = izinliPersonel([Permission::OrderView->value, Permission::OrderFulfill->value]);

    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparis->uuid}/paketler", [
            'items' => [['order_item_id' => $siparis->items->firstOrFail()->id, 'quantity' => 1]],
        ])->assertRedirect();

    /** @var Fulfillment $paket */
    $paket = Fulfillment::query()->latest('id')->firstOrFail();

    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparis->uuid}/paketler/{$paket->uuid}/kargo", [
            'carrier' => 'Test Kargo', 'tracking_number' => 'TK-1',
        ])->assertRedirect();

    expect($paket->refresh()->status->value)->toBe('shipped');

    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/siparisler/{$siparis->uuid}/paketler/{$paket->uuid}/teslim")
        ->assertRedirect();

    expect($paket->refresh()->status->value)->toBe('delivered');
});

it('★★ IZINSIZ personel siparis listesini bile GOREMIYOR — 403', function () {
    sevkiyatlikSiparis('marka-a.test');
    $personel = izinliPersonel([Permission::ProductWrite->value]);

    $this->actingAs($personel, 'staff-web')
        ->get('http://marka-a.test/yonetim/siparisler')
        ->assertForbidden();
});

it('★ SIPARIS AYRINTISI onaylanan SOZLESME SURUMUNU gosteriyor', function () {
    $siparis = sevkiyatlikSiparis('marka-a.test');
    $personel = izinliPersonel([Permission::OrderView->value]);

    $sayfa = inertiaVerisi(
        $this->actingAs($personel, 'staff-web')
            ->get("http://marka-a.test/yonetim/siparisler/{$siparis->uuid}")
            ->getContent(),
    );

    /*
    | ⚠️ Marka "müşteri NEYİ onayladı" sorusunu ekrandan cevaplayabilmeli
    | (1A.4 · 1D-K2). Sürüm gösterilmezse o soru veritabanı sorgusuyla
    | cevaplanır — yani pratikte hiç cevaplanmaz.
    */
    expect($sayfa['props']['siparis']['contract_version'])->not->toBeNull();
});

it('★ iki markanin siparisleri panelde karismiyor', function () {
    sevkiyatlikSiparis('marka-a.test');
    tenancy()->end();

    $bSiparisi = sevkiyatlikSiparis('marka-b.test');
    $personelB = izinliPersonel([Permission::OrderView->value], 'personel@marka-b.test');

    $sayfa = inertiaVerisi(
        $this->actingAs($personelB, 'staff-web')
            ->get('http://marka-b.test/yonetim/siparisler')
            ->getContent(),
    );

    $numaralar = array_column($sayfa['props']['siparisler']['data'], 'order_number');

    expect($numaralar)->toBe([$bSiparisi->order_number]);
});

/*
|--------------------------------------------------------------------------
| ★★ İADE EKRANLARI — order.refund AYRI BİR YETKİ
|--------------------------------------------------------------------------
|
| ⚠️ Depo personeli kargolayabilmeli ama PARA İADESİ YAPAMAMALI. Bu ayrım
| Faz 2'de uçlarda kuruldu; arayüz onu bozmuyor.
*/

/**
 * İADEYE HAZIR sipariş + açılmış talep.
 *
 * ⚠️ `sevkiyatlikSiparis()` YETMİYOR ve bunu 4E'de ölçtüm: para iadesi
 * sağlayıcıya gidiyor ve TAHSİL EDİLMİŞ bir `Payment` kaydı istiyor;
 * o yardımcı ödemeyi servisten yapıyor, kayıt açmıyor. Sonuç 404'tü ve
 * belirtisi yanıltıcıydı — hata mesajı yerine Laravel'in 404 sayfası
 * geliyordu (`firstOrFail()`).
 *
 * @return array{siparis: Order, talep: OrderReturn}
 */
function iadeTalebiKur(string $alanAdi = 'marka-a.test'): array
{
    $siparis = iadeyeHazirSiparis($alanAdi);
    $satir = $siparis->items->firstOrFail();

    return [
        'siparis' => $siparis,
        'talep' => app(ReturnService::class)->talepAc($siparis, [$satir->id => 1]),
    ];
}

it('★ iade listesi order.view ile GORULEBILIYOR', function () {
    iadeTalebiKur();

    $personel = izinliPersonel([Permission::OrderView->value]);

    $sayfa = inertiaVerisi(
        $this->actingAs($personel, 'staff-web')
            ->get('http://marka-a.test/yonetim/iadeler')
            ->getContent(),
    );

    expect($sayfa['component'])->toBe('Iadeler/Liste')
        ->and($sayfa['props']['talepler']['data'])->toHaveCount(1);
});

it('★★ SADECE order.view olan personel IADEYI ONAYLAYAMIYOR — 403', function () {
    ['talep' => $talep] = iadeTalebiKur();

    $personel = izinliPersonel([Permission::OrderView->value]);

    /*
    | ★ Arayüzde işlem paneli bu kullanıcıya çizilmiyor ("İade kararı
    | vermek için yetkiniz yok") — ama koruma sunucuda.
    */
    $this->actingAs($personel, 'staff-web')
        ->post("http://marka-a.test/yonetim/iadeler/{$talep->uuid}/onayla")
        ->assertForbidden();

    expect($talep->refresh()->status->value)->toBe('requested');
});

it('★★ KARGOLAYABILEN personel PARA IADESI YAPAMIYOR — 403', function () {
    ['talep' => $talep] = iadeTalebiKur();

    /*
    | ⚠️ FAZIN ASIL AYRIMI: depo personeli kargoluyor ama parayı gönderemez.
    | Tek izne indirgenseydi kargo yetkisi verilen herkes para iadesi de
    | yapabilirdi.
    */
    $depocu = izinliPersonel([Permission::OrderView->value, Permission::OrderFulfill->value]);

    $this->actingAs($depocu, 'staff-web')
        ->post("http://marka-a.test/yonetim/iadeler/{$talep->uuid}/onayla")
        ->assertForbidden();
});

it('★ order.refund olan personel ZINCIRI yurutebiliyor', function () {
    ['talep' => $talep] = iadeTalebiKur();

    $yetkili = izinliPersonel([Permission::OrderView->value, Permission::OrderRefund->value]);

    $this->actingAs($yetkili, 'staff-web')
        ->post("http://marka-a.test/yonetim/iadeler/{$talep->uuid}/onayla")
        ->assertRedirect();

    expect($talep->refresh()->status->value)->toBe('approved');

    /*
    | ⚠️ STOĞA GERİ KOYMA VARSAYILAN KAPALI (2B): iade edilen ürün hasarlı
    | olabilir. Burada açıkça `false` gönderiliyor.
    */
    $this->actingAs($yetkili, 'staff-web')
        ->post("http://marka-a.test/yonetim/iadeler/{$talep->uuid}/teslim-al", ['restock' => false])
        ->assertRedirect();

    expect($talep->refresh()->status->value)->toBe('received');

    $this->actingAs($yetkili, 'staff-web')
        ->post("http://marka-a.test/yonetim/iadeler/{$talep->uuid}/para-iadesi")
        ->assertRedirect();

    expect($talep->refresh()->status->value)->toBe('completed');
});

it('★ iade AYRINTISI yapilan para iadelerini gosteriyor', function () {
    ['talep' => $talep] = iadeTalebiKur();

    $yetkili = izinliPersonel([Permission::OrderView->value, Permission::OrderRefund->value]);

    foreach (['onayla', 'teslim-al', 'para-iadesi'] as $adim) {
        $this->actingAs($yetkili, 'staff-web')
            ->post("http://marka-a.test/yonetim/iadeler/{$talep->uuid}/{$adim}")
            ->assertRedirect();
    }

    $sayfa = inertiaVerisi(
        $this->actingAs($yetkili, 'staff-web')
            ->get("http://marka-a.test/yonetim/iadeler/{$talep->uuid}")
            ->getContent(),
    );

    /*
    | ⚠️ Marka "bu talebe ne kadar ödendi" sorusunu EKRANDAN cevaplayabilmeli;
    | yoksa aynı talebe ikinci kez iade denemesi yapılırdı.
    */
    expect($sayfa['props']['talep']['refunds'])->toHaveCount(1)
        ->and($sayfa['props']['talep']['refunds'][0]['amount'])->not->toBeNull();
});

it('★★ STOK ACIGI olan siparis listenin BASINDA', function () {
    /*
    | ★ BU TESTİ KIRMA DENEMESİ DOĞURDU. Sıralamayı kaldırdım ve hiçbir
    | test düşmedi — yani "sorunlu siparişler önce" davranışı yorumda
    | yazılıydı ama ÖLÇÜLMÜYORDU.
    |
    | ⚠️ Neden önemli: tarihe göre sıralansaydı yoğun bir günde stok
    | açığı uyarısı üçüncü sayfaya düşer ve pratikte görünmez olurdu.
    | Marka onu ancak müşteri sorunca fark ederdi.
    */
    $ilk = sevkiyatlikSiparis('marka-a.test');
    $sonraki = ikinciSiparis();

    // Eski sipariş sorunlu; yenisi değil.
    $ilk->stock_shortfall = true;
    $ilk->save();

    $personel = izinliPersonel([Permission::OrderView->value]);

    $sayfa = inertiaVerisi(
        $this->actingAs($personel, 'staff-web')
            ->get('http://marka-a.test/yonetim/siparisler')
            ->getContent(),
    );

    $numaralar = array_column($sayfa['props']['siparisler']['data'], 'order_number');

    expect($numaralar[0])->toBe($ilk->order_number)
        ->and($numaralar)->toContain($sonraki->order_number);
});
