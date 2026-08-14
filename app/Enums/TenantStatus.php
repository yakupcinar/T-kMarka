<?php

namespace App\Enums;

/**
 * Markanın yaşam döngüsündeki yeri. (3B)
 *
 * ```
 * provisioning ──▶ trial ──▶ active ⇄ pastDue ──▶ suspended ──▶ closed
 *                    │                                │            │
 *                    └────────── closed ◀─────────────┘      1 yıl sonra
 *                                                             ŞEMA SİLİNİR
 * ```
 */
enum TenantStatus: string
{
    /**
     * Şeması kuruluyor.
     *
     * ⚠️ Bu durumda marka HENÜZ ÇALIŞMIYOR. Şema oluşturma ve migration
     * uzun sürüyor (3D'de kuyruğa alınacak); arada gelen istek "marka yok"
     * değil "hazırlanıyor" cevabı almalı.
     */
    case Provisioning = 'provisioning';

    /**
     * Ücretsiz deneme — KART İSTENMEDEN.
     *
     * ⚠️ Deneme BİZDE tutuluyor, iyzico'da değil: abonelik başlatmak kart
     * istiyor ve kartsız kayıt istiyoruz. iyzico aboneliği ancak deneme
     * bitip kart girilince başlıyor.
     */
    case Trial = 'trial';

    /** Aboneliği yürüyor, ödemeleri alınıyor. */
    case Active = 'active';

    /**
     * Ödeme başarısız — nezaket süresi işliyor.
     *
     * ⚠️ Bu durumda mağaza HÂLÂ AÇIK. Çoğu başarısız ödeme kasıtlı değil
     * (kart yenilenmiş, limit dolmuş); ilk günde kapatmak müşteriyi
     * kaybetmenin en hızlı yolu.
     */
    case PastDue = 'past_due';

    /**
     * Askıda — panel kapalı.
     *
     * ⚠️ VİTRİN AÇIK KALIYOR (4 numaralı karar). Vitrini kapatmak markayı
     * değil markanın MÜŞTERİLERİNİ vuruyor: siparişini takip edemeyen,
     * iade açamayan, parasını ödemiş insanlar. Onların bizimle hiçbir
     * sözleşmesi yok.
     */
    case Suspended = 'suspended';

    /**
     * Kapatılmış — 1 yıl saklanıp silinecek.
     *
     * ⚠️ Veri DOKUNULMADAN duruyor: marka geri dönerse kataloğu, müşterileri
     * ve siparişleri yerinde bulur. Süre sözleşmede yazılı olmak ZORUNDA
     * (KVKK: veri işleyen, sözleşme bitince veriyi iade edip siler).
     */
    case Closed = 'closed';

    /** Marka bu durumdayken paneline girebiliyor mu? */
    public function panelAcikMi(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    /**
     * Vitrin satış yapabiliyor mu?
     *
     * ⚠️ `Suspended` burada TRUE değil ama vitrin yine de erişilebilir
     * olacak (sipariş takibi, iade) — o ayrım 3G'de uçlara yazılıyor.
     */
    public function satisAcikMi(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }
}
