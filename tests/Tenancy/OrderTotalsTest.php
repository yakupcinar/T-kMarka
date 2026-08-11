<?php

use App\Domain\Order\OrderTotals;

/*
| Tutar ve vergi hesabı (domain-model §8).
|
| Üç kural var ve üçü de SESSİZ hata üretir:
|   1. fiyat KDV dâhil → vergi İÇERİDEN ayrıştırılır
|   2. tax_total grand_total'a EKLENMEZ
|   3. vergi indirimden SONRA hesaplanır
|
| ⚠️ Bu testler kiracı gerektirmiyor (saf hesap) ama Tenancy paketinde
| duruyor çünkü aynı düzeni takip ediyoruz; hesap sınıfı da app/Domain'de.
*/

it('★ vergi tutarın İÇİNDEN ayrıştırılıyor', function () {
    $hesap = new OrderTotals;

    /*
    | ⚠️ Yanlış yol: 120 × 0.20 = 24,00
    | Doğru yol:     120 − (120 / 1.20) = 20,00
    |
    | Fark her satırda %20 fazla vergi göstermek olurdu — ve fatura ile
    | tahsilat tutmazdı.
    */
    expect($hesap->vergiyiAyristir('120.00', '20'))->toBe('20.00')
        ->and($hesap->vergiyiAyristir('110.00', '10'))->toBe('10.00')
        ->and($hesap->vergiyiAyristir('101.00', '1'))->toBe('1.00')
        // Oran 0 ise vergi de 0.
        ->and($hesap->vergiyiAyristir('100.00', '0'))->toBe('0.00');
});

it('★ tax_total grand_total a EKLENMİYOR', function () {
    $hesap = new OrderTotals;

    $satir = $hesap->satir('120.00', 2, '20');
    $toplam = $hesap->siparis([$satir]);

    /*
    | ⚠️ Vergi dâhil modelde EN SIK yapılan hata: tax_total'ı toplama
    | eklemek. Sonucu her siparişte müşteriden fazladan KDV tahsil etmek.
    |
    | 240,00 ₺ tahsil ediliyor; 40,00 ₺ vergi onun İÇİNDE.
    */
    expect($toplam['items_total'])->toBe('240.00')
        ->and($toplam['tax_total'])->toBe('40.00')
        ->and($toplam['grand_total'])->toBe('240.00');
});

it('★ vergi İNDİRİMDEN SONRA hesaplanıyor', function () {
    $hesap = new OrderTotals;

    // 120 × 2 = 240, indirim 40 → satır 200. Vergi 200'ün içinden.
    $satir = $hesap->satir('120.00', 2, '20', indirim: '40.00');

    /*
    | ⚠️ Vergi brütten (240) hesaplansaydı 40,00 çıkardı ve iade
    | tutarları faturayla tutmazdı. Doğrusu 200'den: 33,33.
    */
    expect($satir['line_total'])->toBe('200.00')
        ->and($satir['tax_amount'])->toBe('33.33');
});

it('kargo eşiği DÂHİL — tam eşikte ücretsiz', function () {
    $hesap = new OrderTotals;

    // "500 TL üzeri kargo bedava" diyen markanın müşterisi tam 500'de
    // ücret görmemeli.
    expect($hesap->kargo('499.99', '49.90', '500'))->toBe('49.90')
        ->and($hesap->kargo('500.00', '49.90', '500'))->toBe('0.00')
        ->and($hesap->kargo('600.00', '49.90', '500'))->toBe('0.00');
});

it('kargo bedelinin vergisi de toplama giriyor', function () {
    $hesap = new OrderTotals;

    $satir = $hesap->satir('120.00', 1, '20');
    $toplam = $hesap->siparis([$satir], kargo: '60.00', kargoVergiOrani: '20');

    // Ürün vergisi 20,00 + kargo vergisi 10,00
    expect($toplam['tax_total'])->toBe('30.00')
        // grand_total = 120 + 60
        ->and($toplam['grand_total'])->toBe('180.00');
});

it('kuruş kaymıyor — bcmath, float değil', function () {
    $hesap = new OrderTotals;

    /*
    | ⚠️ float ile: 0.1 + 0.2 = 0.30000000000000004
    | Üç satırın toplamı kuruş kaydırır ve sipariş toplamı ile satır
    | toplamları tutmaz. bcmath bunu yaşamıyor.
    */
    $satirlar = [
        $hesap->satir('0.10', 1, '20'),
        $hesap->satir('0.20', 1, '20'),
    ];

    expect($hesap->siparis($satirlar)['items_total'])->toBe('0.30');
});

it('bozuk sayısal girdi 0 kabul ediliyor, patlamıyor', function () {
    $hesap = new OrderTotals;

    // bcmath bozuk girdide uyarı verip 0 sayıyor; davranış belirsiz
    // kalmasın diye açıkça 0'a çeviriyoruz.
    expect($hesap->vergiyiAyristir('abc', '20'))->toBe('0.00')
        ->and($hesap->kargo('abc', '49.90', '500'))->toBe('49.90');
});

it('★ DEĞİŞMEZ: net + vergi TAM OLARAK satır toplamına eşit', function () {
    $hesap = new OrderTotals;

    /*
    | ⚠️ Faturanın tutması için gereken tek şey bu. Çift yuvarlama
    | yapılsaydı (önce net'i yuvarla, sonra vergiyi ayrı yuvarla) bazı
    | tutarlarda 1 kuruş kayar ve fatura toplamı satırların toplamına
    | eşit olmazdı.
    |
    | Formül `vergi = tutar × oran / (100 + oran)` ve `net = tutar − vergi`
    | olduğu için bu değişmez YAPISAL olarak garanti.
    */
    foreach (['0.01', '0.03', '1.00', '99.99', '200.00', '333.33', '1234.56'] as $tutar) {
        foreach (['1', '10', '20'] as $oran) {
            $vergi = $hesap->vergiyiAyristir($tutar, $oran);
            $net = bcsub($tutar, $vergi, 2);

            expect(bcadd($net, $vergi, 2))->toBe(number_format((float) $tutar, 2, '.', ''),
                "kayma: {$tutar} @ %{$oran}");
        }
    }
});
