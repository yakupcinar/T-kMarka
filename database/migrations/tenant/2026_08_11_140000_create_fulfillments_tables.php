<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sevkiyat — ORTADAKİ KATMAN. (docs/domain-model.md §7)
 *
 * ⚠️ Bu katmanı silme dürtüsüne BİLEREK direniyoruz. Tek markalı bir
 * sipariş de birden çok pakette çıkabilir: bir ürün stokta, biri
 * tedarikte; ya da farklı depolardan. Katman silinseydi kısmi sevkiyat,
 * kısmi iptal ve kısmi iade kodu bir daha temizlenmemek üzere bozulurdu.
 *
 * `orders → fulfillments → fulfillment_items → order_items`
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // App\Enums\ShipmentStatus
            $table->string('status', 20)->default('pending');

            $table->string('carrier', 60)->nullable();
            $table->string('tracking_number', 100)->nullable();

            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();

            $table->timestampsTz();

            $table->index(['order_id', 'status']);
        });

        Schema::create('fulfillment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fulfillment_id')->constrained()->cascadeOnDelete();

            /*
            | ⚠️ `restrictOnDelete`: sipariş satırı silinemez zaten
            | (silinmemeli) — ama bu paket ona işaret ettiği sürece
            | veritabanı da izin vermiyor.
            */
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();

            $table->integer('quantity');

            $table->timestampsTz();

            /*
            | Aynı satır aynı pakette iki kez olamaz — adet artırılır.
            | Olsaydı "3 + 2 mi, hangisi geçerli" sorusu doğardı.
            */
            $table->unique(['fulfillment_id', 'order_item_id']);
        });

        DB::statement('ALTER TABLE fulfillment_items ADD CONSTRAINT fulfillment_items_adet_pozitif CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_items');
        Schema::dropIfExists('fulfillments');
    }
};
