<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İade talebi ve para iadesi. (docs/domain-model.md §10 · 2B)
 *
 * ★ İKİ AYRI TABLO, İKİ AYRI AKIŞ (2B-K1):
 *
 *   returns   ürün nerede?   müşteri → marka
 *   refunds   para nerede?   marka → müşteri
 *
 * ⚠️ Tek tabloya sıkıştırılsaydı "onaylandı" ne demek olurdu — ürün geldi
 * mi, para gitti mi? Magento da ayırmış: kredi notunda "stoğa geri" ayrı
 * bir onay kutusu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            // App\Enums\ReturnStatus
            $table->string('status', 20)->default('requested');

            /*
            | ⚠️ CAYMA HAKKI mı, KUSURLU ÜRÜN mü?
            |
            | Cayma 14 günle sınırlı; kusurlu ürün DEĞİL. Ayrılmasaydı
            | ya kusurlu ürün 15. günde reddedilirdi ya cayma süresiz
            | açık kalırdı.
            */
            $table->boolean('is_withdrawal')->default(true);

            $table->string('reason', 255)->nullable();

            /** Marka reddettiyse sebebi — müşteriye gösteriliyor. */
            $table->string('decision_note', 255)->nullable();

            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('received_at')->nullable();

            $table->timestampsTz();

            $table->index(['order_id', 'status']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();

            $table->integer('quantity');

            $table->timestampsTz();

            /*
            | ⚠️ Aynı satır aynı talepte iki kez olamaz. Olsaydı "2 adet"
            | ile "1+1 adet" farklı toplamlar üretir ve aşırı iade
            | kontrolü şaşardı.
            */
            $table->unique(['return_id', 'order_item_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();

            /*
            | ⚠️ Hangi ÖDEMEDEN geri veriliyor. Bir siparişin birden çok
            | ödeme denemesi olabiliyor (1E.1); iade, paranın gerçekten
            | çekildiği denemeye bağlanmak zorunda.
            */
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();

            /*
            | TUTAR ÜÇE AYRILIYOR — tek alan olsaydı faturada neyin geri
            | döndüğü bilinemezdi (§8).
            */
            $table->decimal('items_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);

            /** ⚠️ Bilgi amaçlı: `items_amount`'ın İÇİNDE (§8.2). */
            $table->decimal('tax_amount', 12, 2)->default(0);

            /** Müşteriye giden toplam = items + shipping. */
            $table->decimal('amount', 12, 2);

            $table->string('status', 20)->default('pending');
            $table->string('reason', 255)->nullable();

            $table->string('provider_ref', 190)->nullable();

            /*
            | ★ 2B-K7 — İDEMPOTANSLIK. Ödemedeki (1E-K4) desenin aynısı,
            | ama para GERİ giderken. İki kez iade, iki kez tahsilattan
            | beter: müşteriye fazladan para gider ve geri istemek gerekir.
            */
            $table->string('idempotency_key', 64);

            $table->jsonb('raw_response')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->timestampsTz();

            $table->unique(['order_id', 'idempotency_key']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
