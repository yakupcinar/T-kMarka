<?php

namespace App\Domain\Payment;

/**
 * Ödemenin gerçek hâli SAĞLAYICIYA SORULARAK öğrenilebilen sağlayıcı.
 * (1E-K12)
 *
 * ★ NEDEN AYRI ARAYÜZ:
 *
 * Bildirime güvenmenin iki yolu var ve her sağlayıcı ikisini de sunmuyor:
 *
 *   İMZA    "bu mesaj gerçekten senden mi geldi?"   → mesaja güven
 *   SORGU   "şu ödeme ne oldu?"                     → KAYNAĞA güven
 *
 * iyzico sandbox'ta imza GÖNDERMİYOR (ölçüldü: `X-Iyz-Signature` başlığı
 * boş geliyor; özellik hesapta ayrıca aktive ediliyor). İmzasız mesaj bir
 * yabancının yazdığı kâğıttan farksız — ona bakarak sipariş ödenmiş
 * sayılamaz.
 *
 * Çözüm: bildirimi KAPI ZİLİ saymak. Gövdesinden yalnızca referans
 * okunuyor, içindeki "başarılı" yazısına BAKILMIYOR; ne olduğu sağlayıcıya
 * sorularak öğreniliyor.
 *
 * ⚠️ Sahte bildirim işe yaramıyor: saldırganın yapabileceği tek şey bize
 * ZATEN BİZDE OLAN bir referansı hatırlatmak. Cevabı yine sağlayıcı
 * veriyor, saldırgan değil.
 *
 * ⚠️ Bu arayüzü uygulamayan sağlayıcı için imzasız bildirim REDDEDİLİR.
 * Genel bir gevşetme DEĞİL — sağlayıcı başına, açıkça beyan edilen bir
 * yetenek. Sahte sağlayıcı imzalamaya devam ediyor ve imzasız bildirimi
 * kabul etmiyor.
 */
interface QueryablePaymentProvider extends PaymentProvider
{
    /**
     * Ödemenin sağlayıcıdaki GERÇEK hâli — durum ve tutar birlikte.
     *
     * ⚠️ AĞA ÇIKAR. Çağrı düşerse istisna yükselir; webhook ucu 2xx
     * dönmez ve sağlayıcı tekrar dener — doğru davranış.
     */
    public function sorgula(string $referans): PaymentOutcome;
}
