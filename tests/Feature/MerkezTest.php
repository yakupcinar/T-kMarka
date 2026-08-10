<?php

/*
| MERKEZ (kontrol düzlemi) rotaları.
|
| Laravel'in varsayılan `ExampleTest`'inin yerini aldı: o, "/" 200 dönüyor
| mu diye bakıyordu ama hangi ADRESTE olduğunu söylemiyordu — bizde asıl
| soru bu. `routes/web.php` merkez rotalarını alan adına KİLİTLİYOR, çünkü
| `routes/tenant.php` de "/" tanımlıyor; kilit olmasaydı sonra yüklenen
| dosya diğerini gölgeler ve merkez adres sessizce erişilemez olurdu.
*/

it('merkez adresinde kontrol düzlemi cevap veriyor', function () {
    $merkez = config('tenancy.central_domains')[0];

    $this->get("http://{$merkez}/")
        ->assertOk()
        ->assertSee('kontrol düzlemi');
});

it('sağlık ucu ayakta', function () {
    // bootstrap/app.php → health: '/up'. İzleme ve dağıtım buna bakacak (Faz 6).
    $merkez = config('tenancy.central_domains')[0];

    $this->get("http://{$merkez}/up")->assertOk();
});

it('tanımsız alan adı 404 dönüyor', function () {
    /*
    | ⚠️ 500 DEĞİL 404. Paket "kiracı bulunamadı" istisnası fırlatıyor ve
    | Laravel bunu varsayılan olarak 500 sayardı; bootstrap/app.php'de 404'e
    | çeviriyoruz. Sunucuda bir şey patlamadı — öyle bir marka yok. Ayrıca
    | 500, saldırgana "burada bir şey var ama bozuldu" bilgisi verirdi.
    */
    $this->get('http://kayitli-olmayan.test/')->assertStatus(404);
});
