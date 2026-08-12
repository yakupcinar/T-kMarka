<?php

namespace App\Domain\Payment;

/**
 * Sağlayıcıya GİDEN istek — değişmez.
 *
 * ⚠️ Sipariş nesnesi (`Order`) gönderilmiyor, yalnızca sağlayıcının
 * ihtiyacı olan alanlar gönderiliyor. Model verilseydi bir sağlayıcı
 * uyarlaması `$siparis->grand_total`'ı okumak yerine `$siparis->items`
 * üzerinden kendi toplamını hesaplamaya kalkabilirdi — para hesabının
 * `OrderTotals` dışında ikinci bir yeri olurdu (§0).
 */
final readonly class PaymentRequest
{
    /**
     * @param  numeric-string  $tutar  ⚠️ orders.grand_total'dan geliyor; istemciden ASLA
     * @param  string  $idempotanslikAnahtari  sipariş numarası (1E-K4)
     * @param  string  $donusAdresi  müşterinin geri geleceği ekran — KANIT DEĞİL
     * @param  list<array{ad: string, tutar: string}>  $satirlar
     */
    public function __construct(
        public string $siparisNumarasi,
        public string $tutar,
        public string $eposta,
        public string $idempotanslikAnahtari,
        public string $donusAdresi,

        /*
        | ★ EŞLEŞME ANAHTARI — ödeme DENEMESİNİN uuid'si (1E-K8).
        |
        | Sağlayıcı bunu bildirimde geri döndürüyor. Sipariş numarası
        | konabilirdi ama iki sorun vardı: tahmin edilebilir (1D-K4) ve
        | bir siparişin BİRDEN ÇOK denemesi olabiliyor (kart reddedildi →
        | müşteri başka kartla denedi) — iki deneme aynı kimliği taşırdı.
        */
        public string $denemeUuid = '',

        /*
        | ALICI ve SEPET — barındırılan form bunları ZORUNLU istiyor
        | (1E-K7). Kart verisi bize hiç değmediği için formu sağlayıcı
        | çiziyor; çizebilmesi için ne satıldığını bilmesi gerekiyor.
        |
        | ⚠️ Adres SİPARİŞTEN kopyalanıyor, müşteri defterinden değil:
        | sipariş bir fotoğraftır (1D).
        */
        public string $aliciAdi = '',
        public string $aliciTelefon = '',
        public string $aliciSehir = '',
        public string $aliciAdres = '',

        /** @var list<array{ad: string, tutar: string}> */
        public array $satirlar = [],
    ) {}
}
