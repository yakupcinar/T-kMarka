<?php

use App\Domain\Order\CheckoutService;
use App\Models\Order;

/*
| ÖDEME DÖNÜŞ EKRANI (4.5R) — gerçek kullanımda bulundu.
|
| ★ Şikâyet: *"ödeme yapıldı ama web in webde karşıma açılamayan bir
| sayfa çıktı."* Ölçüldü ve sebep koddaydı:
|
|   sağlayıcı POST (referans GÖVDEDE)        → 200  ✅
|   çerçeveden çıkış betiğinin gittiği GET   → 404  ❌
|
| Betik `window.top.location.href = window.location.href` yazıyordu;
| gövdedeki referans adres çubuğunda olmadığı için üst pencere referanssız
| bir GET yapıyor ve müşteri, ÖDEMESİ BAŞARILI OLMASINA RAĞMEN 404
| görüyordu.
|
| ⚠️ SAHTE SAĞLAYICI BUNU GİZLEMİŞTİ — İKİNCİ KEZ (1E.7.3'ün aynısı).
| Referansı adres çubuğuna koyduğu için testler `?ref=` ile koşuyor ve
| betik çalışıyordu. Gerçek sağlayıcının şekli hiç sınanmamıştı.
*/

it('★★★ SAGLAYICI GOVDEYLE POST edince 303 ile SONUC sayfasina gidiliyor', function () {
    ['referans' => $referans] = bildirimeHazirSiparis('marka-a.test');

    /*
    | ★ GERÇEK ŞEKİL: POST, referans GÖVDEDE — adres çubuğunda DEĞİL.
    */
    $cevap = $this->post('http://marka-a.test/odeme/donus', ['ref' => $referans]);

    $cevap->assertStatus(303);

    $hedef = (string) $cevap->headers->get('Location');

    /*
    | ⚠️ Hedef İMZALI olmalı: sonuç sayfası GET'lenebilir olduğu için
    | uuid'i bilen herkes başkasının sipariş durumunu okuyabilirdi.
    */
    expect($hedef)->toContain('/odeme/sonuc/')
        ->and($hedef)->toContain('signature=');

    // ⚠️ ASIL İDDİA: betiğin gideceği adres artık ÇALIŞIYOR.
    $this->get($hedef)->assertOk()->assertSee('window.top.location.href', escape: false);
});

it('★★★ IMZASIZ sonuc adresi ACILMIYOR', function () {
    ['referans' => $referans] = bildirimeHazirSiparis('marka-a.test');

    $this->post('http://marka-a.test/odeme/donus', ['ref' => $referans]);

    $siparis = Order::orderByDesc('id')->firstOrFail();

    /*
    | ⚠️ İmza olmasaydı uuid'i ele geçiren biri (kargo etiketi, ekran
    | görüntüsü, tarayıcı geçmişi) siparişin ödeme durumunu okurdu.
    */
    $this->get("http://marka-a.test/odeme/sonuc/{$siparis->uuid}")
        ->assertForbidden();
});

it('★★★ SONUC SAYFASI durumu SIPARISTEN okuyor — istekten DEGIL', function () {
    ['referans' => $referans] = bildirimeHazirSiparis('marka-a.test');

    $hedef = (string) $this->post('http://marka-a.test/odeme/donus', ['ref' => $referans])
        ->headers->get('Location');

    /*
    | ⚠️ Sipariş henüz ödenmedi: ekran "işleniyor" demeli. `?status=success`
    | benzeri bir alana bakılsaydı müşteri adres çubuğuna yazarak kendine
    | "ödendi" ekranı gösterebilirdi (1E-K1).
    */
    $this->get($hedef)->assertOk()->assertDontSee('Siparişiniz alındı');

    $siparis = Order::orderByDesc('id')->firstOrFail();
    app(CheckoutService::class)->odemeBasarili($siparis);

    // Aynı imzalı adres, artık BAŞARILI gösteriyor.
    $this->get($hedef)->assertOk()->assertSee('Siparişiniz alındı');
});

it('★★ API ISTEMCISI hala JSON aliyor — yonlendirme YOK', function () {
    ['referans' => $referans] = bildirimeHazirSiparis('marka-a.test');

    /*
    | ⚠️ Sağlayıcı bazen sunucudan sunucuya çağırıyor; JSON dalı
    | korunmalı. Tek dal yazılsaydı ya tarayıcı JSON görürdü ya makine
    | yönlendirme yerdi.
    */
    $this->postJson('http://marka-a.test/odeme/donus', ['ref' => $referans])
        ->assertOk()
        ->assertJsonStructure(['order_number', 'payment_status', 'state']);
});

it('★★ BILINMEYEN referans hala 404', function () {
    bildirimeHazirSiparis('marka-a.test');

    $this->post('http://marka-a.test/odeme/donus', ['ref' => 'olmayan-referans'])
        ->assertNotFound();
});
