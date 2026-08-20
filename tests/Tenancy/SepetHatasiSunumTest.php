<?php

use App\Domain\Legal\LegalDocumentService;
use App\Enums\LegalDocumentType;
use App\Models\Customer;
use App\Models\Order;

/*
| SEPET/STOK HATALARININ SUNUMU (4.5O) — gerçek kullanımda ölçüldü.
|
| ★ AYNI HATANIN DÖRDÜNCÜSÜ: 4A'da kapalı mağaza, 4B'de ödeme dönüşü,
| 4.5G'de ödeme başlatma. Bu üçü gözden kaçmıştı.
|
| ⚠️ Sepette stok yetmeyince ödeme düğmesi müşteriye HAM JSON basıyordu:
|
|     {"message":"'DC-1' için yeterli stok yok: 2 istendi, 1 kaldı."}
|
| ⚠️ Bu ailenin tamamı testlerden KAÇTI çünkü testler `postJson`
| kullanıyor — `Accept: application/json` ekleyen yardımcı, ölçülmek
| istenen ayrımı ortadan kaldırıyor. Bu dosyada `postJson` KULLANILMAZ.
*/

it('★★★ BAGLI STOK YETMEYINCE tarayici SEPETE yonlendiriliyor — JSON DEGIL', function () {
    $varyant = sayacMagazasi();

    $rakip = Customer::create([
        'email' => 'rakip@ornek.test', 'password' => bcrypt('sifre1234'), 'name' => 'Rakip',
    ]);

    /*
    | ★ SENARYO GERÇEK KOŞUDAN ALINDI: stok DÜŞÜK değil, BAĞLI.
    |
    | ⚠️ İlk yazdığım test `stock` alanını düşürüyordu ve YANLIŞ ŞEYİ
    | ölçüyordu: düşük stok `CartService::engeller()`'e takılıyor,
    | controller onu yakalıyor ve zaten anlaşılır mesaj veriyor.
    |
    | Ham JSON'un çıktığı yol başka: stok VAR ama ödemesi süren başka bir
    | siparişe BAĞLI. Sepet engel görmüyor, rezervasyon adımı patlıyor ve
    | `InsufficientStockException` controller'ın yakaladığı türlerin
    | DIŞINDA kalıyordu.
    */
    bekleyenSiparis($varyant, $rakip, 8);

    $musteri = Customer::create([
        'email' => 'stok@ornek.test', 'password' => bcrypt('sifre1234'), 'name' => 'Ayşe',
    ]);

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 5]);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    $cevap = $this->post('http://marka-a.test/odeme', [
        'email' => $musteri->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'shipping' => ornekAdres(),
    ]);

    $cevap->assertRedirect('http://marka-a.test/sepet');

    /*
    | ⚠️ HAM JSON GÖRÜNMEMELİ. Yalnızca yönlendirmeye bakılsaydı gövdenin
    | ne olduğu ölçülmezdi.
    */
    expect($cevap->getContent())->not->toContain('"message"');

    $sepet = $this->followRedirects($cevap)->getContent() ?: '';

    expect($sepet)->toContain('yeterli stok yok')
        ->and($sepet)->not->toContain('"available"');

    // ⚠️ Sipariş OLUŞMAMALI — rakibin bağlı stoğu korunuyor.
    expect(Order::where('email', $musteri->email)->count())->toBe(0);
});

it('★★★ API ISTEMCISI hala JSON aliyor — 409', function () {
    $varyant = sayacMagazasi();

    $rakip = Customer::create([
        'email' => 'rakip@ornek.test', 'password' => bcrypt('sifre1234'), 'name' => 'Rakip',
    ]);

    bekleyenSiparis($varyant, $rakip, 8);

    $musteri = Customer::create([
        'email' => 'api@ornek.test', 'password' => bcrypt('sifre1234'), 'name' => 'Api',
    ]);

    $this->post('http://marka-a.test/giris', ['email' => $musteri->email, 'password' => 'sifre1234']);
    $this->post('http://marka-a.test/sepet/ekle', ['variant_uuid' => $varyant->uuid, 'quantity' => 5]);

    $sozlesme = app(LegalDocumentService::class)->guncelSurum(LegalDocumentType::DistanceSales);

    /*
    | ⚠️ AYNI istisna, farklı istemci. Tek dal yazılsaydı ya tarayıcı JSON
    | görürdü ya API HTML — ikisi de bozuk.
    |
    | ⚠️ `postJson` BURADA DOĞRU: ölçülen şey API davranışı. Tarayıcı
    | testinde ise YASAK — `Accept` başlığını eklemesi tam da ölçmek
    | istediğimiz ayrımı ortadan kaldırırdı.
    */
    $this->postJson('http://marka-a.test/odeme', [
        'email' => $musteri->email,
        'legal_version_id' => $sozlesme?->id,
        'sozlesme_onay' => '1',
        'shipping' => ornekAdres(),
    ])->assertStatus(409)->assertJsonStructure(['message', 'sku', 'available']);
});

it('★★ SATILAMAYAN VARYANT da tarayiciya HTML donuyor', function () {
    $varyant = sayacMagazasi();

    $varyant->is_active = false;
    $varyant->save();

    /*
    | ⚠️ Üç istisnanın üçü de aynı yolu izlemeli. Biri atlanırsa aynı
    | hata dördüncü kez değil BEŞİNCİ kez geri gelir.
    */
    $cevap = $this->post('http://marka-a.test/sepet/ekle', [
        'variant_uuid' => $varyant->uuid,
        'quantity' => 1,
    ]);

    expect($cevap->getContent())->not->toContain('"message"');
});
