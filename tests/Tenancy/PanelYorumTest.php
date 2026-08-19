<?php

use App\Domain\Identity\RoleService;
use App\Domain\Review\ReviewService;
use App\Domain\Settings\StorePublication;
use App\Enums\Permission;
use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

/*
| YORUM MODERASYONU (4.5F) — EKRANI OLMAYAN SON ALANDI.
|
| ★ Uçları 2E'de vardı; ekran olmadığı için müşteri yorumları HİÇ
| ONAYLANAMIYORDU — yani vitrinde hiçbir yorum görünmüyordu ve marka
| bunu fark edemiyordu.
*/

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Yorum yazmaya hazır + mağazası AÇIK marka.
 *
 * @return array{siparis: Order, musteri: Customer, urun: Product}
 */
function yorumluAcikMagaza(): array
{
    $hazir = yorumaHazir();

    // ⚠️ Vitrin uçları `magaza-acik` arkasında; yayınlanmazsa 503.
    app(StorePublication::class)->yayinla();

    return $hazir;
}

it('★ yorum ekrani BEKLEYEN kuyrugu gosteriyor', function () {
    ['siparis' => $siparis, 'musteri' => $musteri, 'urun' => $urun] = yorumaHazir();

    app(ReviewService::class)->yaz($musteri, $urun, ['rating' => 5, 'body' => 'Cok iyi']);

    $sahip = User::where('is_owner', true)->firstOrFail();

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/yorumlar')->getContent(),
    );

    expect($sayfa['component'])->toBe('Yorumlar')
        ->and($sayfa['props']['yorumlar']['data'])->toHaveCount(1)
        ->and($sayfa['props']['bekleyen'])->toBe(1);

    // ⚠️ Panelde TAM AD görünüyor (vitrinde kısaltılmış — 2E).
    expect($sayfa['props']['yorumlar']['data'][0]['customer'])->toBe($musteri->name);

    expect($siparis)->not->toBeNull();
});

it('★★ ONAYLANAN yorum VITRINDE gorunuyor', function () {
    ['musteri' => $musteri, 'urun' => $urun] = yorumluAcikMagaza();

    $yorum = app(ReviewService::class)->yaz($musteri, $urun, ['rating' => 5, 'body' => 'Harika urun']);

    // Onaydan ÖNCE vitrinde yok.
    $this->getJson("http://marka-a.test/api/products/{$urun->slug}/reviews")
        ->assertOk()
        ->assertJsonCount(0, 'reviews');

    $sahip = User::where('is_owner', true)->firstOrFail();

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/yorumlar/{$yorum->uuid}/onayla")
        ->assertRedirect();

    /*
    | ★ BLOĞUN ASIL İDDİASI: ekran olmadan bu kuyruğun ÇIKIŞI YOKTU.
    | Yorum onaylanmadan vitrinde görünmüyor ve ortalamaya girmiyor (2E).
    */
    $this->getJson("http://marka-a.test/api/products/{$urun->slug}/reviews")
        ->assertOk()
        ->assertJsonCount(1, 'reviews');

    expect($urun->refresh()->rating_avg)->not->toBeNull();
});

it('★ REDDEDILEN yorumun gerekcesi saklaniyor ama vitrinde YOK', function () {
    ['musteri' => $musteri, 'urun' => $urun] = yorumluAcikMagaza();

    $yorum = app(ReviewService::class)->yaz($musteri, $urun, ['rating' => 1, 'body' => 'Uygunsuz icerik']);

    $sahip = User::where('is_owner', true)->firstOrFail();

    $this->actingAs($sahip, 'staff-web')
        ->post("http://marka-a.test/yonetim/yorumlar/{$yorum->uuid}/reddet", ['note' => 'Kufur iceriyor'])
        ->assertRedirect();

    expect($yorum->refresh()->status)->toBe(ReviewStatus::Rejected)
        ->and($yorum->moderation_note)->toBe('Kufur iceriyor');

    /*
    | ⚠️ Gerekçe VİTRİNDE GÖRÜNMÜYOR (2E): moderasyon notu markanın iç
    | kaydı; müşteriye gösterilmesi hem gereksiz hem incitici olurdu.
    */
    $this->getJson("http://marka-a.test/api/products/{$urun->slug}/reviews")
        ->assertOk()
        ->assertDontSee('Kufur iceriyor');
});

it('★ MODERASYON KUYRUGU eskiden yeniye siralaniyor', function () {
    ['musteri' => $musteri, 'urun' => $urun] = yorumluAcikMagaza();

    $ilk = app(ReviewService::class)->yaz($musteri, $urun, ['rating' => 4, 'body' => 'Ilk yorum']);

    $sahip = User::where('is_owner', true)->firstOrFail();

    $sayfa = inertiaVerisi(
        $this->actingAs($sahip, 'staff-web')->get('http://marka-a.test/yonetim/yorumlar')->getContent(),
    );

    /*
    | ⚠️ Listenin geri kalanının TERSİNE eskiden yeniye: moderasyon
    | kuyruğunda en eski yorum EN ÇOK BEKLEYEN demek. Yeniden eskiye
    | sıralansaydı ilk yazan müşteri en son sırada kalırdı.
    */
    expect($sayfa['props']['yorumlar']['data'][0]['uuid'])->toBe($ilk->uuid);
});

it('★★ IZINSIZ personel yorum ekranina GIREMIYOR', function () {
    yorumaHazir();

    $rol = app(RoleService::class)->olustur('Depocu', [Permission::OrderView->value]);
    $personel = User::factory()->create(['email' => 'depo@marka-a.test', 'password' => 'sifre1234']);
    $personel->roles()->sync([$rol->id]);

    $this->actingAs($personel->refresh(), 'staff-web')
        ->get('http://marka-a.test/yonetim/yorumlar')
        ->assertForbidden();
});
