<?php

use App\Domain\Payment\FakePaymentProvider;
use App\Domain\Payment\IyzicoProvider;
use App\Domain\Payment\PaymentNotConfiguredException;
use App\Domain\Payment\PaymentProviderException;
use App\Domain\Payment\PaymentService;
use App\Domain\Settings\SettingsService;
use App\Domain\Settings\StorePublication;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\SettingGroup;
use App\Models\Payment;
use App\Models\StockReservation;
use Illuminate\Support\Facades\Http;

/*
| iyzico sağlayıcısı (1E.7.2).
|
| ⚠️ iyzico'nun SUNUCUSU taklit ediliyor (Http::fake) — gerçek sandbox'a
| karşı koşu 1E.7.3'te, ngrok tüneliyle. Burada sınanan şey ağ değil,
| BİZİM tarafımız: imza düzeni, alan eşleşmesi ve hata davranışı.
*/

/** iyzico anahtarlarını kurup sağlayıcıyı seçer. */
function iyzicoluMarka(string $alanAdi): void
{
    $ayarlar = app(SettingsService::class);
    $ayarlar->yaz(SettingGroup::Payment, 'provider', 'iyzico');
    $ayarlar->yaz(SettingGroup::Payment, IyzicoProvider::API_ANAHTARI, 'sandbox-api', sifreli: true);
    $ayarlar->yaz(SettingGroup::Payment, IyzicoProvider::GIZLI_ANAHTAR, 'sandbox-gizli', sifreli: true);
}

/**
 * iyzico'nun imzaladığı gibi bir bildirim üretir.
 *
 * @return array{yuk: array<string, string>, imza: string}
 */
function iyzicoBildirimi(string $jeton, string $denemeUuid, string $durum = 'SUCCESS'): array
{
    $yuk = [
        'iyziEventType' => 'CHECKOUT_FORM_AUTH',
        'iyziPaymentId' => '19238412',
        'token' => $jeton,
        'paymentConversationId' => $denemeUuid,
        'status' => $durum,
        'iyziReferenceCode' => 'REF-1',
    ];

    /*
    | ⚠️ İmza düzeni iyzico'nun belgesinden: gizli anahtar + beş alan
    | BELİRLİ SIRAYLA. Testin kendi düzeni olsaydı yalnızca kendimizi
    | doğrulamış olurduk.
    */
    $metin = 'sandbox-gizli'
        .$yuk['iyziEventType'].$yuk['iyziPaymentId'].$yuk['token']
        .$yuk['paymentConversationId'].$yuk['status'];

    return ['yuk' => $yuk, 'imza' => hash_hmac('sha256', $metin, 'sandbox-gizli')];
}

it('★ başlatma: token ve ödeme sayfası adresi alınıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-a.test');
    iyzicoluMarka('iyz-a.test');

    Http::fake(['*/checkoutform/initialize/*' => Http::response([
        'status' => 'success',
        'token' => 'IYZ-TOKEN-1',
        'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com/?token=IYZ-TOKEN-1',
    ])]);

    $sonuc = app(PaymentService::class)->baslat($siparis, 'http://iyz-a.test/odeme/donus');

    expect($sonuc->saglayiciReferansi)->toBe('IYZ-TOKEN-1')
        ->and($sonuc->yonlendirmeAdresi)->toContain('iyzipay.com');

    $deneme = Payment::firstOrFail();

    expect($deneme->provider)->toBe('iyzico')
        ->and($deneme->provider_ref)->toBe('IYZ-TOKEN-1');

    Http::assertSent(function ($istek) use ($deneme, $siparis) {
        $govde = $istek->data();

        /*
        | ★ 1E-K8: eşleşme anahtarı ödeme DENEMESİNİN uuid'si — sipariş
        | numarası DEĞİL. Numara tahmin edilebilir ve bir siparişin birden
        | çok denemesi olabilir.
        */
        return $govde['conversationId'] === $deneme->uuid

            // ⚠️ Tutar SUNUCUDAN; price ile paidPrice aynı (taksit yok).
            && $govde['price'] === $siparis->grand_total
            && $govde['paidPrice'] === $siparis->grand_total

            // ⚠️ Kimlik doğrulama IYZWSv2 + aynı rastgele değer iki yerde.
            && str_starts_with($istek->header('Authorization')[0], 'IYZWSv2 ')
            && $istek->header('x-iyzi-rnd')[0] !== '';
    });
});

it('★ SEPET TOPLAMI tutarla birebir tutuyor — kargo da bir satır', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-b.test');
    iyzicoluMarka('iyz-b.test');

    Http::fake(['*' => Http::response(['status' => 'success', 'token' => 'T', 'paymentPageUrl' => 'https://x/'])]);

    app(PaymentService::class)->baslat($siparis, 'http://iyz-b.test/odeme/donus');

    Http::assertSent(function ($istek) use ($siparis) {
        $toplam = '0';

        foreach ($istek->data()['basketItems'] as $satir) {
            $toplam = bcadd($toplam, $satir['price'], 2);
        }

        /*
        | ⚠️ iyzico satır toplamının `price` ile BİREBİR tutmasını istiyor.
        | Kargo satır olarak eklenmeseydi toplam eksik kalır ve iyzico
        | isteği reddederdi — ödeme hiç başlamazdı.
        */
        /** @var numeric-string $beklenen */
        $beklenen = (string) $siparis->grand_total;

        return bccomp($toplam, $beklenen, 2) === 0;
    });
});

it('★ iyzico İŞ HATASINI 200 ile döndürüyor — yakalanıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-c.test');
    iyzicoluMarka('iyz-c.test');

    /*
    | ⚠️ HTTP 200 ama `status: failure`. Yalnızca HTTP koduna bakılsaydı
    | başarısız çağrı başarılı sanılırdı.
    |
    | ⚠️ Cevaba `token` ve `paymentPageUrl` BİLEREK konuyor. İlk yazışımda
    | yoktular ve test yeşildi — ama yanlış sebepten: `status` kontrolü
    | kaldırıldığında bile "token eksik" koruması yakalıyordu. Kırmızı
    | kontrol bunu ortaya çıkardı: TEST GEÇİYOR ≠ TEST DOĞRU ŞEYİ ÖLÇÜYOR.
    */
    Http::fake(['*' => Http::response([
        'status' => 'failure',
        'errorCode' => '5084',
        'errorMessage' => 'Sepet tutarı geçersiz',
        'token' => 'IYZ-TOKEN-X',
        'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com/?token=IYZ-TOKEN-X',
    ], 200)]);

    expect(fn () => app(PaymentService::class)->baslat($siparis, 'http://iyz-c.test/odeme/donus'))
        ->toThrow(PaymentProviderException::class);
});

it('★ UÇTAN: sağlayıcı reddedince 502 — ham istisna SIZMIYOR', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-j.test');
    iyzicoluMarka('iyz-j.test');
    app(StorePublication::class)->yayinla();

    /*
    | ⚠️ 1E.7.3'te GERÇEK sandbox'ta yaşandı: `.test` uzantılı e-posta
    | reddedildi ve müşteri ham istisna gövdesini gördü — sınıf adı,
    | dosya yolu, yığın izi dâhil.
    */
    Http::fake(['*' => Http::response([
        'status' => 'failure',
        'errorMessage' => 'email hatalı format ile gönderilmiştir',
    ], 200)]);

    $cevap = $this->postJson("http://iyz-j.test/api/orders/{$siparis->uuid}/pay")
        ->assertStatus(502);

    // ⚠️ Sağlayıcının mesajı da yığın izi de müşteriye gitmiyor.
    expect(json_encode($cevap->json()))->not->toContain('email hatalı')
        ->and(json_encode($cevap->json()))->not->toContain('IyzicoProvider');
});

it("★ DÖNÜŞ: iyzico token'ı GÖVDEDE yolluyor", function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-k.test');
    iyzicoluMarka('iyz-k.test');
    app(StorePublication::class)->yayinla();

    Http::fake(['*' => Http::response([
        'status' => 'success', 'token' => 'IYZ-TOKEN-7', 'paymentPageUrl' => 'https://x/',
    ])]);

    app(PaymentService::class)->baslat($siparis, 'http://iyz-k.test/odeme/donus');

    /*
    | ⚠️ Gerçek sandbox'ın gönderdiği biçim: gövdede `token`, sorguda
    | hiçbir şey. Uç `?ref=` bekliyordu ve 404 dönüyordu.
    */
    $this->post('http://iyz-k.test/odeme/donus', ['token' => 'IYZ-TOKEN-7'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('state', 'processing');
});

it('★ İMZASIZ bildirim: iyzico KABUL (sorgulanabilir), sahte RED', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-l.test');
    iyzicoluMarka('iyz-l.test');
    app(StorePublication::class)->yayinla();

    Http::fake([
        '*/checkoutform/initialize/*' => Http::response([
            'status' => 'success', 'token' => 'IYZ-TOKEN-6', 'paymentPageUrl' => 'https://x/',
        ]),
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'success', 'paymentStatus' => 'SUCCESS',
            'paidPrice' => (string) $siparis->grand_total,
        ]),
    ]);

    app(PaymentService::class)->baslat($siparis, 'http://iyz-l.test/odeme/donus');

    $deneme = Payment::firstOrFail();
    ['yuk' => $yuk] = iyzicoBildirimi('IYZ-TOKEN-6', (string) $deneme->uuid);

    /*
    | ★ 1E-K12. ÖLÇÜLDÜ: iyzico sandbox `X-Iyz-Signature` başlığını BOŞ
    | gönderiyor. İmzasız bildirim yine de işleniyor — ama GÖVDESİNE
    | GÜVENİLEREK değil, referansı alıp sağlayıcıya SORULARAK.
    */
    $this->withHeader('X-Iyz-Signature', '')
        ->postJson('http://iyz-l.test/webhooks/payment', $yuk)
        ->assertOk()
        ->assertJsonPath('result', 'paid');

    expect($siparis->refresh()->payment_status->value)->toBe('paid');
});

it('★ SORGULANAMAYAN sağlayıcıda imzasız bildirim REDDEDİLİYOR', function () {
    ['siparis' => $s, 'referans' => $ref, 'tutar' => $tutar] = bildirimeHazirSiparis('iyz-o.test');

    ['yuk' => $yuk] = app(FakePaymentProvider::class)
        ->bildirim($s->order_number, $ref, $tutar);

    /*
    | ⚠️ İstisna SAĞLAYICI BAŞINA. Sahte sağlayıcı imzalıyor ve
    | sorgulanamıyor — imzasız bildirimi kabul etmesi için hiçbir sebep
    | yok. Genel bir gevşetme olsaydı, imzalayan bir sağlayıcının imzası
    | bir gün hiç gelmemeye başlasa da fark etmezdik.
    */
    $this->postJson('http://iyz-o.test/webhooks/payment', $yuk)
        ->assertStatus(401);

    expect($s->refresh()->payment_status->value)->toBe('pending');
});

it('★ BOZUK imza sorgulanabilir sağlayıcıda da REDDEDİLİYOR', function () {
    markaKur('iyz-p.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-p.test');

    ['yuk' => $yuk] = iyzicoBildirimi('IYZ-TOKEN-5', 'u');

    /*
    | ⚠️ İmzasız kabul ediyoruz diye BOZUK imzayı da kabul etmiyoruz.
    | Bozuk imza, imzasızdan DAHA kötü bir işarettir: ya anahtar değişmiş
    | ya da biri kurcalıyor.
    */
    $this->withHeader('X-Iyz-Signature', 'uydurma-imza')
        ->postJson('http://iyz-p.test/webhooks/payment', $yuk)
        ->assertStatus(401);
});

it('★ ESKİ imza başlığı da okunuyor', function () {
    markaKur('iyz-m.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-m.test');

    ['yuk' => $yuk, 'imza' => $imza] = iyzicoBildirimi('T', 'u');

    /*
    | ⚠️ Belgede yalnızca V3 yazıyor ama sandbox eski adı gönderiyor.
    | Tek ada bağlansaydık gelen bildirimi hiç doğrulayamazdık.
    */
    $this->withHeader('X-Iyz-Signature', $imza)
        ->postJson('http://iyz-m.test/webhooks/payment', $yuk)
        ->assertStatus(404);   // imza GEÇTİ; referans bu markada yok
});

it('★ İADE: kırılım kırılım gönderiliyor', function () {
    markaKur('iyz-s.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-s.test');

    /*
    | ★ GERÇEK SANDBOX'TA ÖLÇÜLDÜ: iyzico ödemeyi sepet satırlarına
    | bölüyor ("kırılım") ve iade HER KIRILIM İÇİN AYRI yapılıyor.
    | Tek kırılıma tüm tutar gönderildiğinde reddediliyor:
    | `5093 — verilen iade tutarı kırılımın tutarından büyük olamaz`.
    */
    Http::fake([
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paidPrice' => '299.80',
            'itemTransactions' => [
                ['paymentTransactionId' => 'TX-1', 'paidPrice' => 249.9],
                ['paymentTransactionId' => 'TX-2', 'paidPrice' => 49.9],
            ],
        ]),
        '*/payment/refund' => Http::sequence()
            ->push(['status' => 'success', 'price' => 249.9, 'paymentId' => 'R1'])
            ->push(['status' => 'success', 'price' => 49.9, 'paymentId' => 'R2']),
    ]);

    $sonuc = app(IyzicoProvider::class)->iadeEt('TOKEN', '299.80', 'anahtar');

    expect($sonuc->basarili)->toBeTrue()
        ->and($sonuc->tutar)->toBe('299.80');

    // İki ayrı çağrı, her biri kendi kırılımına kendi tutarıyla.
    Http::assertSentCount(3);
});

it('★ İADE: sağlayıcı FARKLI TUTAR iade ederse gürültülü hata', function () {
    markaKur('iyz-t.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-t.test');

    /*
    | ⚠️ GERÇEK KOŞUDA YAŞANDI: 249,90 istendi, cevapta `price: 200`
    | döndü ve sebebi cevaptan anlaşılamadı. `status: success` yetmiyor.
    |
    | Kontrol olmasaydı kayıtta 299,80 iade yazarken müşteriye 249,90
    | gitmiş olurdu — ve bu hiçbir yerde görünmezdi.
    */
    Http::fake([
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paidPrice' => '249.90',
            'itemTransactions' => [['paymentTransactionId' => 'TX-1', 'paidPrice' => 249.9]],
        ]),
        '*/payment/refund' => Http::response(['status' => 'success', 'price' => 200]),
    ]);

    expect(fn () => app(IyzicoProvider::class)->iadeEt('TOKEN', '249.90', 'anahtar'))
        ->toThrow(PaymentProviderException::class);
});

it('★ İADE: daha önce iade edilen tutar DÜŞÜLÜYOR', function () {
    markaKur('iyz-u.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-u.test');

    /*
    | ⚠️ Kısmi iadeden sonra kalan hesaba katılmasaydı ikinci iade yine
    | 5093 alırdı.
    */
    Http::fake([
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paidPrice' => '249.90',
            'itemTransactions' => [
                ['paymentTransactionId' => 'TX-1', 'paidPrice' => 249.9, 'refundedPrice' => 149.9],
            ],
        ]),
        '*/payment/refund' => Http::response(['status' => 'success', 'price' => 100]),
    ]);

    $sonuc = app(IyzicoProvider::class)->iadeEt('TOKEN', '100.00', 'anahtar');

    expect($sonuc->basarili)->toBeTrue();

    Http::assertSent(fn ($istek) => ! str_contains($istek->url(), 'refund') || $istek->data()['price'] === '100.00');
});

it('★ İMZA: geçerli kabul, bozulmuş RED', function () {
    markaKur('iyz-d.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-d.test');

    $saglayici = app(IyzicoProvider::class);
    ['yuk' => $yuk, 'imza' => $imza] = iyzicoBildirimi('IYZ-TOKEN-1', 'deneme-uuid');

    expect($saglayici->webhookuDogrula($yuk, $imza))->toBeTrue()
        ->and($saglayici->webhookuDogrula($yuk, null))->toBeFalse()
        ->and($saglayici->webhookuDogrula($yuk, 'uydurma'))->toBeFalse();

    // Tek alan değişince imza tutmuyor.
    $yuk['status'] = 'FAILURE';
    expect($saglayici->webhookuDogrula($yuk, $imza))->toBeFalse();
});

it('★ ARA DURUMLAR başarı sayılmıyor', function () {
    markaKur('iyz-e.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-e.test');

    $saglayici = app(IyzicoProvider::class);

    /*
    | ⚠️ iyzico ara durumlar da gönderiyor. "FAILURE değilse başarılıdır"
    | denseydi müşteri daha kart bilgisini girerken sipariş ödenmiş
    | sayılırdı.
    */
    foreach (['INIT_THREEDS', 'CALLBACK_THREEDS', 'FAILURE'] as $durum) {
        ['yuk' => $yuk] = iyzicoBildirimi('T', 'u', $durum);
        expect($saglayici->webhookuCoz($yuk)->basarili)->toBeFalse();
    }

    ['yuk' => $basarili] = iyzicoBildirimi('T', 'u');
    expect($saglayici->webhookuCoz($basarili)->basarili)->toBeTrue();
});

it('★ GERÇEK AYRI ÇAĞRIYLA soruluyor — bildirimde tutar YOK', function () {
    markaKur('iyz-f.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-f.test');

    Http::fake(['*/checkoutform/auth/ecom/detail' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'SUCCESS',
        'paidPrice' => '289.90',
    ])]);

    $sonuc = app(IyzicoProvider::class)->sorgula('IYZ-TOKEN-1');

    // ⚠️ Durum ve tutar İKİSİ DE sorgudan (1E-K9 · 1E-K12).
    expect($sonuc->tutar)->toBe('289.90')
        ->and($sonuc->basarili)->toBeTrue();

    Http::assertSent(fn ($istek) => $istek->data()['token'] === 'IYZ-TOKEN-1');
});

it('★ BAŞARISIZ ödemede de paidPrice DÖNÜYOR — tutara bakıp ödendi denmiyor', function () {
    markaKur('iyz-n.test');
    magazayiHazirla();
    iyzicoluMarka('iyz-n.test');

    /*
    | ⚠️ GERÇEK SANDBOX'TA ÖLÇÜLDÜ: 3DS'i geçemeyen bir ödemede bile
    | `paidPrice: 299.8` dönüyor. Başarı ölçütü tutar olsaydı, doğrulaması
    | başarısız her ödeme "ödendi" sayılırdı.
    */
    Http::fake(['*' => Http::response([
        'status' => 'success',
        'paymentStatus' => 'FAILURE',
        'mdStatus' => 0,
        'paidPrice' => '299.80',
    ])]);

    $sonuc = app(IyzicoProvider::class)->sorgula('IYZ-TOKEN-2');

    /*
    | ⚠️ `hataKodu` artık sağlayıcının HATA GRUBUNU taşıyor, kuru bir
    | "FAILURE" değil: marka "neden alınamadı" sorusunu buradan
    | cevaplıyor. Gerçek koşuda `NOT_SUFFICIENT_FUNDS` geldi.
    */
    expect($sonuc->basarili)->toBeFalse()
        ->and($sonuc->tutar)->toBe('299.80')
        ->and($sonuc->hataKodu)->toBe('unknown');
});

it('★ BAŞARISIZ ödeme: "çağrı hatası" ile "ödeme hatası" AYRI', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('iyz-r.test');
    iyzicoluMarka('iyz-r.test');
    app(StorePublication::class)->yayinla();

    Http::fake([
        '*/checkoutform/initialize/*' => Http::response([
            'status' => 'success', 'token' => 'IYZ-TOKEN-4', 'paymentPageUrl' => 'https://x/',
        ]),

        /*
        | ⚠️ GERÇEK SANDBOX CEVABI (yetersiz bakiye). Dikkat: servis
        | düzeyinde de `status: failure`, `paidPrice` YOK — ama
        | `paymentStatus` VAR. Yani çağrı başarılı, ödeme başarısız.
        |
        | Ayrım yapılmadığında bu bildirim 502 alıyordu: sipariş `pending`
        | kalıyor, bağlı stok 60 dakika kimseye satılamıyor ve müşteri
        | neden reddedildiğini öğrenemiyordu. Gerçek koşuda ölçüldü.
        */
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'failure',
            'errorCode' => '10051',
            'errorMessage' => 'Kart limiti yetersiz, yetersiz bakiye',
            'errorGroup' => 'NOT_SUFFICIENT_FUNDS',
            'paymentStatus' => 'FAILURE',
            'mdStatus' => 1,
            'token' => 'IYZ-TOKEN-4',
        ]),
    ]);

    app(PaymentService::class)->baslat($siparis, 'http://iyz-r.test/odeme/donus');

    $deneme = Payment::firstOrFail();
    ['yuk' => $yuk] = iyzicoBildirimi('IYZ-TOKEN-4', (string) $deneme->uuid, 'FAILURE');

    $this->withHeader('X-Iyz-Signature', '')
        ->postJson('http://iyz-r.test/webhooks/payment', $yuk)
        ->assertOk()
        ->assertJsonPath('result', 'failed');

    /*
    | ⚠️ ASIL SINAV: stok DÜŞMÜYOR (hiç düşmemişti) ama BAĞLI ADET
    | SERBEST KALIYOR — o adetler yeniden satılabilmeli.
    */
    expect($siparis->refresh()->payment_status->value)->toBe('failed')
        ->and($varyant->refresh()->stock)->toBe(5)
        ->and($varyant->committed)->toBe(0)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Released);

    // Marka "neden alınamadı" sorusunu buradan cevaplıyor.
    expect(Payment::firstOrFail()->raw_response['webhook'] ?? null)->not->toBeNull();
});

it('★ UÇTAN UCA: iyzico bildirimi geldi, stok düştü', function () {
    ['siparis' => $siparis, 'varyant' => $varyant] = odemeAsamasiSiparisi('iyz-g.test');
    iyzicoluMarka('iyz-g.test');
    app(StorePublication::class)->yayinla();

    Http::fake([
        '*/checkoutform/initialize/*' => Http::response([
            'status' => 'success', 'token' => 'IYZ-TOKEN-9',
            'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com/?token=IYZ-TOKEN-9',
        ]),
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'success', 'paymentStatus' => 'SUCCESS',
            'paidPrice' => (string) $siparis->grand_total,
        ]),
    ]);

    app(PaymentService::class)->baslat($siparis, 'http://iyz-g.test/odeme/donus');

    $deneme = Payment::firstOrFail();
    ['yuk' => $yuk, 'imza' => $imza] = iyzicoBildirimi('IYZ-TOKEN-9', (string) $deneme->uuid);

    $this->withHeader('X-IYZ-SIGNATURE-V3', $imza)
        ->postJson('http://iyz-g.test/webhooks/payment', $yuk)
        ->assertOk()
        ->assertJsonPath('result', 'paid');

    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($varyant->refresh()->stock)->toBe(3)
        ->and(StockReservation::firstOrFail()->status)->toBe(ReservationStatus::Committed);

    // Tekrar teslim: stok bir kez daha düşmüyor.
    $this->withHeader('X-IYZ-SIGNATURE-V3', $imza)
        ->postJson('http://iyz-g.test/webhooks/payment', $yuk)
        ->assertJsonPath('result', 'already_processed');

    expect($varyant->refresh()->stock)->toBe(3);
});

it('★ SORGUDAKİ TUTAR FARKLIYSA ödeme saymıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-h.test');
    iyzicoluMarka('iyz-h.test');
    app(StorePublication::class)->yayinla();

    Http::fake([
        '*/checkoutform/initialize/*' => Http::response([
            'status' => 'success', 'token' => 'IYZ-TOKEN-8', 'paymentPageUrl' => 'https://x/',
        ]),
        // ⚠️ iyzico 1,00 diyor; sipariş 289,90.
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paidPrice' => '1.00',
        ]),
    ]);

    app(PaymentService::class)->baslat($siparis, 'http://iyz-h.test/odeme/donus');

    $deneme = Payment::firstOrFail();
    ['yuk' => $yuk, 'imza' => $imza] = iyzicoBildirimi('IYZ-TOKEN-8', (string) $deneme->uuid);

    /*
    | ⚠️ İmza GEÇERLİ ama tutar tutmuyor. İmza yükü korur; tutarın doğru
    | siparişe ait olduğunu garanti etmez (1E.4'ün ikinci savunması).
    */
    $this->withHeader('X-IYZ-SIGNATURE-V3', $imza)
        ->postJson('http://iyz-h.test/webhooks/payment', $yuk)
        ->assertStatus(422);

    expect($siparis->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('anahtarları eksik markada iyzico seçilirse ödeme başlamıyor', function () {
    ['siparis' => $siparis] = odemeAsamasiSiparisi('iyz-i.test');

    // Sağlayıcı iyzico ama anahtarlar girilmemiş.
    app(SettingsService::class)->yaz(SettingGroup::Payment, 'provider', 'iyzico');

    expect(fn () => app(PaymentService::class)->baslat($siparis, 'http://iyz-i.test/odeme/donus'))
        ->toThrow(PaymentNotConfiguredException::class);

    expect(Payment::count())->toBe(0);
});
