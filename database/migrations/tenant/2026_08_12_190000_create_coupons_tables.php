<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kupon ve kullanım kayıtları. (2A)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            /*
            | ⚠️ Kod BÜYÜK HARF ve ASCII saklanıyor.
            |
            | Türkçe büyütme tuzağı (1B'de ölçüldü): `i` → `İ`, `ı` → `I`.
            | Marka "indirim" yazsa `İNDİRİM` olur; müşteri klavyesinden
            | `INDIRIM` yazar ve kupon BULUNAMAZ — hata da vermez, sadece
            | "geçersiz kupon" der.
            |
            | `CHECK` ile veritabanı da zorluyor: uygulamadan kaçan tek
            | satır bile bozuk kod yazamıyor.
            */
            $table->string('code', 40)->unique();

            // App\Enums\CouponType
            $table->string('type', 20);

            /** Yüzde ya da tutar — türüne göre. */
            $table->decimal('value', 12, 2)->default(0);

            /** Bu tutarın altındaki sepette geçmiyor. */
            $table->decimal('min_subtotal', 12, 2)->default(0);

            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            /*
            | ⚠️ `used_count` MATERYALLEŞTİRİLMİŞ sayaç — `committed`
            | (1D-K1) gibi. Bedeli aynı: yarışa karşı SATIR KİLİDİ
            | gerekiyor (2A-K3) ve gece denetimi ister.
            */
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);

            /** Müşteri başına sınır — misafirde uygulanamıyor (kimlik yok). */
            $table->integer('max_uses_per_customer')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestampsTz();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_code_upper_ascii CHECK (code = upper(code) AND code ~ '^[A-Z0-9_-]+$')");

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            /** Gerçekten uygulanan indirim — kuponun değeri DEĞİL. */
            $table->decimal('amount', 12, 2);

            $table->timestampsTz();

            /*
            | ⚠️ Bir sipariş bir kuponu bir kez kullanır (2A-K2: zaten
            | sipariş başına tek kupon). Kısıt olmasaydı çift çağrı
            | sayacı iki kez artırırdı.
            */
            $table->unique(['coupon_id', 'order_id']);
            $table->index('customer_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            /*
            | ★ 2A-K4 — KUPON SİPARİŞE KOPYALANIYOR.
            |
            | ⚠️ "Sipariş bir fotoğraftır" (1D). Kupon sonradan silinse
            | bile sipariş neyle indirildiğini söyleyebilmeli. FK ile
            | bağlansaydı silinen kuponda geçmiş sipariş okunamaz olurdu.
            */
            $table->string('coupon_code', 40)->nullable();
        });

        Schema::table('carts', function (Blueprint $table) {
            /** Sepette uygulanan kupon — siparişe dönüşünce kopyalanıyor. */
            $table->string('coupon_code', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('coupon_code'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('coupon_code'));
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
