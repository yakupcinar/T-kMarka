<?php

use App\Models\Customer;

/*
| Bu uygulamanın cevabı HER ZAMAN JSON. (2E'de ölçülerek bulundu)
|
| ★ NEDEN AYRI TEST DOSYASI: 425 testin hiçbiri bunu yakalamamıştı.
| Hepsi `postJson`/`getJson` kullanıyor ve o yardımcılar
| `Accept: application/json` başlığını OTOMATİK ekliyor. Gerçek bir
| istemci eklemediğinde Laravel `login` rotasına yönlendirmeye çalışıyor,
| arayüz olmadığı için (M-3) öyle bir rota yok ve 500 dönüyor.
|
| ⚠️ Bu dosyadaki testler BİLEREK `postJson` KULLANMIYOR — `post()` ham
| istek gönderiyor. `postJson`'a çevrilirse test yine yeşil olur ama
| hiçbir şey ölçmez.
*/

it('★ Accept BAŞLIĞI OLMADAN korumalı uç 401 dönüyor — 500 DEĞİL', function () {
    markaKur('json-a.test');

    /*
    | ⚠️ Ölçüm:
    |   Accept: application/json  →  401  ✓
    |   başlık YOK                →  500  ✗  "Route [login] not defined."
    |
    | Sessiz olmayan ama GÖRÜNMEYEN bir hata: testler hep başlık
    | gönderdiği için 425 koşuda bir kez bile ortaya çıkmadı.
    */
    $cevap = $this->post('http://json-a.test/api/addresses', [], ['Accept' => '*/*']);

    $cevap->assertStatus(401);

    expect($cevap->headers->get('content-type'))->toContain('application/json');
});

it('★ PANEL ucu da başlıksız 401 dönüyor', function () {
    markaKur('json-b.test');

    $this->post('http://json-b.test/panel/collections', [], ['Accept' => '*/*'])
        ->assertStatus(401);
});

it('★ DOĞRULAMA hatası da başlıksız JSON dönüyor — 422', function () {
    $marka = markaKur('json-c.test');

    $musteri = Customer::factory()->create(['email' => 'json@ornek.test']);
    $token = $musteri->createToken('test')->plainTextToken;

    /*
    | ⚠️ Doğrulama hatası HTML isteğinde normalde 302 (geri yönlendirme)
    | döner. Bu uygulamada arayüz yok; 302 alan bir istemci hatanın ne
    | olduğunu HİÇ öğrenemezdi.
    */
    $cevap = $this->post('http://json-c.test/api/addresses', [], [
        'Accept' => '*/*',
        'Authorization' => 'Bearer '.$token,
    ]);

    $cevap->assertStatus(422);

    expect($cevap->headers->get('content-type'))->toContain('application/json');
});
