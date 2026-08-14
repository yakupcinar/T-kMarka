<?php

namespace App\Platform;

use App\Enums\TenantStatus;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;

/**
 * Markanın durum geçişleri. (3C)
 *
 * ★ İZİN VERİLEN GEÇİŞLER:
 *
 * ```
 * provisioning ─▶ trial ─▶ active ⇄ past_due ─▶ suspended
 *                   │        │          │           │
 *                   └────────┴──────────┴──▶ closed ┘
 *                                              │
 *                                       (geri dönüş: active)
 * ```
 *
 * ⚠️ Geçiş kuralları CONTROLLER'A YAZILMIYOR: aynı geçişler zamanlanmış
 * görevlerden de (deneme bitişi, ödeme başarısızlığı) tetiklenecek ve
 * HTTP'den geçmeyecekler (CLAUDE.md — iş kuralı controller'a yazılmaz).
 */
class TenantLifecycle
{
    /**
     * Hangi durumdan hangilerine geçilebilir.
     *
     * ⚠️ AÇIK LİSTE. "Her geçiş serbest" olsaydı kapatılmış bir marka
     * kazara `trial`'a döndürülebilir, denemesi bitmiş marka `provisioning`
     * yapılabilirdi — ikisi de hata vermeden.
     *
     * @var array<string, list<TenantStatus>>
     */
    public const GECISLER = [
        'provisioning' => [TenantStatus::Trial, TenantStatus::Active, TenantStatus::Closed],
        'trial' => [TenantStatus::Active, TenantStatus::Suspended, TenantStatus::Closed],
        'active' => [TenantStatus::PastDue, TenantStatus::Suspended, TenantStatus::Closed],
        'past_due' => [TenantStatus::Active, TenantStatus::Suspended, TenantStatus::Closed],
        'suspended' => [TenantStatus::Active, TenantStatus::Closed],

        /*
        | ⚠️ Kapatılmış marka YALNIZCA `active`'e dönebiliyor — `trial`'a
        | DEĞİL. Denemeye dönebilseydi marka kapatıp yeniden açarak sonsuz
        | ücretsiz kullanım elde ederdi.
        */
        'closed' => [TenantStatus::Active],
    ];

    /**
     * Durum değiştirir.
     *
     * @throws InvalidTransitionException
     */
    public function gecir(Tenant $marka, TenantStatus $yeni): Tenant
    {
        $mevcut = $marka->status;

        if ($mevcut === $yeni) {
            /*
            | ⚠️ Aynı duruma geçiş SESSİZCE kabul ediliyor, hata değil:
            | zamanlanmış görev aynı markayı iki kez görebilir ve ikinci
            | çağrı bir arıza değil.
            |
            | ⚠️ Ama tarih alanları YENİDEN yazılmıyor — `suspended_at`
            | tazelenseydi "ne zaman askıya alındı" bilgisi her koşuda
            | bugüne kayardı.
            */
            return $marka;
        }

        /*
        | ⚠️ Statik analiz sabitin anahtarlarını TAM biliyor — yani enum'a
        | yeni bir durum eklenip buraya yazılmazsa analiz aşamasında
        | yakalanıyor. Çalışma zamanı savunması gereksiz olurdu.
        */
        $izinli = $mevcut === null ? [] : self::GECISLER[$mevcut->value];

        if (! in_array($yeni, $izinli, true)) {
            throw new InvalidTransitionException(sprintf(
                '%s durumundan %s durumuna geçilemez.',
                $mevcut instanceof TenantStatus ? $mevcut->value : 'bilinmeyen',
                $yeni->value,
            ));
        }

        $marka->status = $yeni;

        /*
        | ⚠️ Tarihler durumla BİRLİKTE yazılıyor. Ayrı çağrılara bırakılsaydı
        | biri unutulur ve "askıda ama askıya alma tarihi yok" gibi bir kayıt
        | oluşurdu — üstelik hata vermeden.
        */
        $marka->suspended_at = $yeni === TenantStatus::Suspended ? now() : null;
        $marka->closed_at = $yeni === TenantStatus::Closed ? now() : null;

        /*
        | ⚠️ `grace_ends_at` YALNIZCA `past_due`'ya girerken kuruluyor.
        | Her geçişte yazılsaydı ödemesi düzelen marka hâlâ nezaket
        | süresindeymiş gibi görünürdü.
        */
        if ($yeni === TenantStatus::PastDue) {
            $marka->grace_ends_at = now()->addDays(self::NEZAKET_GUN);
        }

        if ($yeni === TenantStatus::Active) {
            $marka->grace_ends_at = null;
        }

        $marka->save();

        return $marka;
    }

    /**
     * Ödeme başarısız olduktan sonra tam açık kalınan süre.
     *
     * ⚠️ Çoğu başarısız ödeme KASITLI DEĞİL (kart yenilenmiş, limit dolmuş).
     * İlk günde kapatmak müşteriyi kaybetmenin en hızlı yolu; sektör
     * pratiği 3-7 gün nezaket, 10-14 gün sonra askı diyor.
     */
    public const NEZAKET_GUN = 7;

    /** Markaya plan atar. */
    public function planAta(Tenant $marka, Plan $plan): Tenant
    {
        $marka->plan()->associate($plan);
        $marka->save();

        return $marka;
    }
}
