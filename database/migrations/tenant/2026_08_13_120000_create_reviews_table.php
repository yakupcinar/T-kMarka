<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün yorumları ve puanları. (2E)
 *
 * ★ Yalnızca SATIN ALAN yazabilir (2E-K1) ve yorum ONAY BEKLER (2E-K2).
 *
 * ⚠️ `products.rating_avg` / `rating_count` MATERYALLEŞTİRİLMİŞ sayaç —
 * `committed`'ın (1D-K1) aynısı. Bedeli gecelik denetim; kendiliğinden
 * düzelmiyor ve bozulursa vitrinde yanlış puan görünmeye devam ederdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $tablo): void {
            $tablo->id();
            $tablo->uuid('uuid')->unique();

            $tablo->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $tablo->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            /*
            | ★ SATIN ALMA KANITI. Yorumun dayandığı sipariş satırı.
            |
            | ⚠️ `nullOnDelete` DEĞİL: satır silinirse yorumun dayanağı
            | kaybolur ve "doğrulanmış alıcı" iddiası kanıtsız kalırdı.
            | Sipariş satırları zaten silinmiyor (sipariş bir fotoğraf).
            */
            $tablo->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            $tablo->smallInteger('rating');
            $tablo->string('title')->nullable();
            $tablo->text('body');

            $tablo->string('status');

            /*
            | ⚠️ Reddetme gerekçesi PERSONEL İÇİN, müşteriye gösterilmiyor.
            | Vitrine çıksaydı moderasyon notu herkese açık olurdu.
            */
            $tablo->text('moderation_note')->nullable();
            $tablo->timestampTz('moderated_at')->nullable();

            $tablo->timestampsTz();
            $tablo->softDeletesTz();

            /*
            | ⚠️ ÜRÜN BAŞINA TEK YORUM. Kısıt olmasaydı aynı müşteri aynı
            | ürüne defalarca 5 yıldız verip ortalamayı tek başına
            | belirleyebilirdi — hata vermeden.
            |
            | ⚠️ `order_item_id` üzerinden değil: aynı ürünü iki kez alan
            | müşteri iki yorum yazabilirdi ve sınır delinirdi.
            */
            $tablo->unique(['product_id', 'customer_id']);

            $tablo->index(['product_id', 'status']);
        });

        /*
        | ⚠️ Puan aralığı VERİTABANINDA da kısıtlı. Yalnızca uygulamada
        | doğrulansaydı tohumlayıcı, artisan komutu ya da elle yazılan bir
        | satır 7 yıldızlı yorum sokabilirdi ve ortalama sessizce bozulurdu.
        */
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range CHECK (rating BETWEEN 1 AND 5)');

        Schema::table('products', function (Blueprint $tablo): void {
            /*
            | ⚠️ `decimal` — float DEĞİL. Para değil ama aynı gerekçe:
            | float ortalama her hesapta biraz kayar ve iki farklı yerde
            | farklı görünürdü.
            */
            $tablo->decimal('rating_avg', 3, 2)->nullable();
            $tablo->integer('rating_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $tablo): void {
            $tablo->dropColumn(['rating_avg', 'rating_count']);
        });

        Schema::dropIfExists('reviews');
    }
};
