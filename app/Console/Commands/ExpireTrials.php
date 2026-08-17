<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Platform\Models\Tenant;
use App\Platform\TenantLifecycle;
use Illuminate\Console\Command;

/**
 * Denemesi biten markaları askıya alır. (3E)
 *
 * ⚠️ MERKEZ bağlamda çalışıyor — `tenants:run` ile SARILMAZ. Diğer
 * komutlarımızın tersi: onlar marka verisine dokunuyordu, bu merkeze.
 *
 * ★ 3B'nin kolonları olmasaydı bu sorgu YAZILAMAZDI: `trial_ends_at`
 * `data` json'ının içindeyken `where(...)` hiçbir şey bulmuyordu — hata
 * da vermiyordu.
 */
class ExpireTrials extends Command
{
    protected $signature = 'abonelik:deneme-denetle {--kuru : Yalnızca göster}';

    protected $description = 'Denemesi biten markaları askıya alır (merkez bağlamda).';

    public function handle(TenantLifecycle $yasam): int
    {
        /*
        | ⚠️ `subscription_ref IS NULL` şartı KRİTİK: kart girip abone olan
        | markanın `trial_ends_at`'i temizleniyor ama bu şart olmadan geçmiş
        | bir tarih kalıntısı ödeyen markayı askıya aldırabilirdi.
        */
        $bekleyenler = Tenant::query()
            ->where('status', TenantStatus::Trial)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereNull('subscription_ref')
            ->orderBy('id')
            ->get()
            /*
            | ⚠️ `all()` şart: `get()` paketin `TenantCollection`'ını
            | döndürüyor ve statik analiz eleman tipini `Tenant` olarak
            | çıkaramıyor (3C'de aynı sorun `whereHas`'te çıkmıştı).
            */
            ->all();

        if ($bekleyenler === []) {
            return self::SUCCESS;
        }

        /** @var Tenant $marka */
        foreach ($bekleyenler as $marka) {
            $this->line(sprintf('  %s (%s) — denemesi bitti', $marka->name ?? '?', $marka->id));

            if ($this->option('kuru')) {
                continue;
            }

            /*
            | ⚠️ `suspended` — `closed` DEĞİL. Kapatma verinin 1 yıl sonra
            | silinmesini başlatıyor; denemesi biten marka henüz ayrılmadı,
            | kart girerse geri dönebilir.
            */
            $yasam->gecir($marka, TenantStatus::Suspended);
        }

        $this->info(sprintf('%d marka %s.', count($bekleyenler), $this->option('kuru') ? 'bulundu (kuru)' : 'askıya alındı'));

        return self::SUCCESS;
    }
}
