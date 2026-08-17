<?php

namespace App\Platform;

use App\Domain\Quota\QuotaExceededException;
use App\Domain\Quota\QuotaGuard;
use App\Enums\TenantStatus;
use App\Platform\Models\Plan;
use App\Platform\Models\Tenant;

/**
 * Plan sınırlarını uygulayan taraf. (3F)
 *
 * ★ Arayüz `app/Domain/Quota/`'da, uygulama BURADA: iş mantığı "kotam var
 * mı" diye soruyor, planın merkez kayıttan geldiğini bilmiyor (M-2.7).
 *
 * ⚠️ Bu sınıf kiracıyı BİLİYOR ve bilmek zorunda — `app/Platform/` zaten
 * merkez tarafı. Ölçüm `app/Domain/` için geçerli.
 */
class PlanQuotaGuard implements QuotaGuard
{
    /**
     * ★ DENEME SINIRLARI — plan seçilmeden önce geçerli.
     *
     * ⚠️ Deneme SINIRSIZ olsaydı biri deneme hesabıyla yüz binlerce ürün
     * yükleyip 14 gün sonra terk ederdi; şemayı ve yedeklemeyi biz taşırdık.
     *
     * ⚠️ Ama çok dar da olamaz: marka ürününü yükleyip denemeden satın
     * alma kararı veremez. En düşük planın (100 ürün) yarısı değil, aynısı
     * seçildi — deneme gerçek kullanımı temsil etmeli.
     */
    public const DENEME_URUN = 100;

    /**
     * ⚠️ 1 DEĞİL 3 — ve bunu mevcut bir test ortaya çıkardı.
     *
     * İlk yazımda en düşük planla aynı (1) yapılmıştı ve `IzinTest`'teki
     * "sahip personel davet edebiliyor" testi kırıldı: deneme markasında
     * sahip zaten 1 kişi olduğu için kimse davet edilemiyordu.
     *
     * ★ Yani marka, satın alma kararını vereceği 14 gün boyunca personel
     * yönetimini HİÇ DENEYEMİYORDU — tam da satın alma sebebi olan bir
     * özelliği.
     *
     * ⚠️ Plana geçince sınır düşse bile var olan personel SİLİNMİYOR
     * (kota yeni eklemeyi engelliyor, geçmişi değil).
     */
    public const DENEME_PERSONEL = 3;

    /**
     * ⚠️ Denemede TÜM özellikler AÇIK. Kapalı olsaydı marka satın almadan
     * önce ne aldığını göremezdi — ve zaten kapalı özelliği görmek satın
     * alma sebebidir.
     */
    public const DENEME_OZELLIKLER = ['collections' => true, 'reviews' => true];

    public function urunEklenebilirMi(int $mevcutAdet): void
    {
        if ($this->kotaDisi()) {
            return;
        }

        $limit = $this->limit('max_products', self::DENEME_URUN);

        if ($this->asildi($limit, $mevcutAdet)) {
            throw new QuotaExceededException(
                sprintf('Planınızın ürün sınırına ulaştınız (%d). Daha fazla ürün için planınızı yükseltin.', (int) $limit),
                tur: 'products',
                limit: $limit,
            );
        }
    }

    public function personelEklenebilirMi(int $mevcutAdet): void
    {
        if ($this->kotaDisi()) {
            return;
        }

        $limit = $this->limit('max_staff', self::DENEME_PERSONEL);

        if ($this->asildi($limit, $mevcutAdet)) {
            throw new QuotaExceededException(
                sprintf('Planınızın personel sınırına ulaştınız (%d). Daha fazla personel için planınızı yükseltin.', (int) $limit),
                tur: 'staff',
                limit: $limit,
            );
        }
    }

    public function ozellikAcikMi(string $ozellik): bool
    {
        if ($this->kotaDisi()) {
            return true;
        }

        $plan = $this->plan();

        if ($plan === null) {
            return (bool) (self::DENEME_OZELLIKLER[$ozellik] ?? false);
        }

        $ozellikler = $plan->features;

        /*
        | ⚠️ Tanımsız özellik KAPALI sayılıyor, açık değil. Açık sayılsaydı
        | plana yeni bir özellik eklendiğinde eski planlar onu SESSİZCE
        | kazanırdı — hiçbir yerde görünmeden.
        */
        return (bool) ($ozellikler[$ozellik] ?? false);
    }

    public function ozelligiDogrula(string $ozellik): void
    {
        if ($this->ozellikAcikMi($ozellik)) {
            return;
        }

        throw new QuotaExceededException(
            'Bu özellik planınızda yok. Kullanmak için planınızı yükseltin.',
            tur: $ozellik,
        );
    }

    /**
     * Markanın planındaki sınır — plan yoksa deneme sınırı.
     */
    private function limit(string $alan, int $denemeVarsayilani): ?int
    {
        $plan = $this->plan();

        if ($plan === null) {
            /*
            | ⚠️ Plan yoksa DENEME sınırı geçerli. `null` (sınırsız)
            | dönseydi ödeme yapmamış marka sınırsız kullanırdı.
            */
            return $denemeVarsayilani;
        }

        /** @var int|null $deger */
        $deger = $plan->{$alan};

        return $deger;
    }

    /**
     * ⚠️ `null` = SINIRSIZ. `Plan::asildiMi()` ile aynı kural, tek yerde:
     * her çağrı yerinde `$limit === null` kontrolü tekrarlansaydı biri
     * unutur ve sınırsız plan sıfır kotalı olurdu.
     */
    private function asildi(?int $limit, int $mevcut): bool
    {
        return (new Plan)->asildiMi($limit, $mevcut);
    }

    /**
     * Merkez bağlamda mıyız? — kota hiç uygulanmıyor.
     *
     * ★ AYRI METOT, ve bunu bir TEST YAKALADI.
     *
     * ⚠️ Önce `plan()` içinde `null` döndürülüyordu ve iki AYRI ANLAM aynı
     * değere biniyordu:
     *   · kiracı YOK   → kota hiç uygulanmamalı
     *   · plan yok/deneme → DENEME sınırı uygulanmalı
     *
     * Sonuç: merkez bağlamda çalışan bakım komutları deneme sınırına
     * takılıyordu. `tenants:run` ile koşan her komut, tohumlayıcı ve veri
     * taşıma 100 üründen sonra kırılırdı.
     */
    private function kotaDisi(): bool
    {
        return ! tenant() instanceof Tenant;
    }

    private function plan(): ?Plan
    {
        $marka = tenant();

        if (! $marka instanceof Tenant) {
            return null;
        }

        /*
        | ⚠️ ASKIDAKİ marka için de plan okunuyor — kota kontrolü askıyı
        | ilgilendirmiyor. Askı ayrı bir kapı (`marka-aktif`, 3C) ve zaten
        | paneli kapatıyor; burada tekrar kontrol etmek iki yerde aynı
        | kuralı tutmak olurdu.
        */
        if ($marka->status === TenantStatus::Trial) {
            /*
            | ⚠️ Denemede plan ATANMIŞ OLSA BİLE deneme sınırları geçerli.
            | Plan okunsaydı, kontrol düzleminden plan atanan bir deneme
            | markası ödemeden o planın sınırlarını kullanırdı.
            */
            return null;
        }

        return $marka->plan;
    }
}
