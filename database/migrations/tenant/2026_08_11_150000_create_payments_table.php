<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ödeme denemeleri. (docs/domain-model.md §10 · PLAN 1E-K3)
 *
 * ★ Bu tablonun ASIL İŞİ ödeme kaydetmek değil, AYNI ÖDEMEYİ İKİ KEZ
 * İŞLEMEYİ ENGELLEMEK.
 *
 * Webhook teslimi "en az bir kez"dir: aynı bildirim iki, üç kez gelir ve
 * bu arıza değil tasarımdır (iyzico 15 dk arayla 3 kez daha yolluyor).
 * Kod tarafında "acaba işledim mi" diye bakmak yarışı çözmez — iki istek
 * aynı anda bakar, ikisi de "hayır" görür.
 *
 *   payments (provider, provider_ref) UNIQUE   ← ikincisini VERİTABANI reddeder
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            // 'fake' · 'iyzico' · 'paytr'
            $table->string('provider', 30);

            /*
            | Sağlayıcının işlem numarası — idempotanslığın dayanağı.
            |
            | ⚠️ NULL olabilir: deneme kaydı sağlayıcıya İSTEK GİTMEDEN
            | önce açılıyor, numara cevapla geliyor. NULL'lar UNIQUE'i
            | tetiklemez (PostgreSQL'de NULL ≠ NULL) — yani yarım kalmış
            | denemeler birbirini engellemiyor, bu istenen davranış.
            */
            $table->string('provider_ref', 190)->nullable();

            /*
            | ★ İdempotanslık anahtarı — sağlayıcıya GİDERKEN taşınıyor.
            |
            | `provider_ref` gelen tarafı korur (aynı webhook iki kez),
            | bu alan GİDEN tarafı korur (müşteri "öde"ye iki kez basar).
            | İkisi ayrı problem: ikinci tıklamada sağlayıcı iki FARKLI
            | işlem numarası üretirdi ve UNIQUE hiçbir şey yakalamazdı.
            */
            $table->string('idempotency_key', 64);

            /*
            | Tutar KAYDEDİLİYOR — `orders.grand_total`'a bakılmıyor.
            | Sipariş sonradan düzeltilirse (iade, kısmi iptal) o an ne
            | tahsil edildiği burada donmuş kalmalı.
            */
            $table->decimal('amount', 12, 2);

            $table->string('status', 20)->default('pending');

            /*
            | Denetim izi — MASKELENMİŞ.
            |
            | ⚠️ Kart numarası, CVC, tam ad gibi alanlar buraya YAZILMAZ.
            | Kart verisi hiçbir zaman sisteme girmiyor; sağlayıcı cevabında
            | maskeli parça gelse bile ham cevap süzülerek saklanıyor.
            */
            $table->jsonb('raw_response')->nullable();

            $table->timestampTz('completed_at')->nullable();

            // ⚠️ timestampsTz — saat dilimi taşımayan damga yasak (§0).
            $table->timestampsTz();

            /*
            | ★ 1E-K3'ün kalbi. İkinci webhook buraya çarpıyor.
            |
            | Bunu uygulamada `if (zatenIslendiMi())` ile yapmak yarış
            | koşuluna açık: iki webhook aynı anda gelir, ikisi de kaydı
            | bulamaz, ikisi de stoğu düşürür. Kısıt bunu imkânsız kılıyor.
            */
            $table->unique(['provider', 'provider_ref']);

            /*
            | Aynı sipariş için aynı anahtarla ikinci deneme açılamaz.
            | Çift tıklamada ikinci istek buraya çarpıp ilk denemeyi
            | bulur ve onun sonucunu bekler.
            */
            $table->unique(['order_id', 'idempotency_key']);

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
