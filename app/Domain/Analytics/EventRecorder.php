<?php

namespace App\Domain\Analytics;

use App\Enums\EventType;
use App\Jobs\RecordEvent;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Olay kaydeden tek kapı. (1F-K1)
 *
 * ★ Olaylar DOMAIN'de doğuyor, controller'da değil: projenin kuralı
 * "bir kontrol HTTP dışından atlanabiliyorsa domain'e girer". Sipariş
 * bir tohumlayıcıdan da oluşabiliyor ve olayı yine doğmalı.
 *
 * ⚠️ Tek istisna `product_viewed` — iş mantığı yok, saf bir görüntüleme.
 * Onu domain'e taşımak "ürüne bakıldı" diye bir iş kuralı uydurmak olurdu.
 *
 * ⚠️ Bu sınıf kiracıdan HABERSİZ (M-2.7): hangi markada olduğunu sormuyor.
 * Kimliği kuyruk altyapısı taşıyor.
 */
class EventRecorder
{
    /**
     * Olayı kuyruğa atar.
     *
     * ★ 1F-K5 — `afterCommit`. En kritik satır.
     *
     * ⚠️ Sipariş oluşturma bir transaction İÇİNDE koşuyor. İş oracıkta
     * kuyruğa atılsaydı ve transaction sonradan geri sarılsaydı, sipariş
     * HİÇ VAR OLMAZ ama olay Redis'e girmiş olurdu. Worker onu alır ve
     * olmayan bir siparişin `order_placed` olayını yazardı.
     *
     * `afterCommit()` işi COMMIT'e kadar tutuyor; geri sarılırsa iş hiç
     * atılmıyor. Transaction dışında çağrıldığında ise davranış değişmiyor,
     * iş hemen gidiyor.
     *
     * @param  array<string, mixed>  $payload  ⚠️ KİŞİSEL VERİ GİRMEZ (1F-K4)
     */
    public function kaydet(EventType $tip, array $payload = [], ?Customer $musteri = null): void
    {
        /*
        | ⚠️ Olay kaydı İŞİ BOZMAZ (1F-K3). Kuyruk sürücüsü erişilemezse
        | istisna yükselip siparişi düşürmesin diye yutuluyor.
        |
        | Yutulan tek şey KUYRUĞA ATAMAMA. İşin kendisi düşerse kuyruk
        | zaten tekrar deniyor.
        */
        try {
            RecordEvent::dispatch($tip, $payload, $musteri?->id, now())->afterCommit();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Transaction içinde miyiz? Yalnızca testler ve teşhis için.
     *
     * ⚠️ Karar vermek için KULLANILMIYOR — `afterCommit()` bunu kendisi
     * hallediyor. Koşullu davranış yazılsaydı iki yol oluşur ve biri
     * sınanmadan kalırdı.
     */
    public function transactionIcindeMi(): bool
    {
        return DB::transactionLevel() > 0;
    }
}
