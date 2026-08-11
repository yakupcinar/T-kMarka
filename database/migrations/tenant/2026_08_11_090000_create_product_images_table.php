<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün görselleri. (domain-model §5)
 *
 * ⚠️ Dosyanın kendisi MARKA KLASÖRÜNDE: `storage/tenant<id>/app/public/`.
 * Paketin dosya sistemi bootstrapper'ı `storage_path()`'i kiracıya çeviriyor
 * (M-2.4/3). Burada yalnızca göreli yol duruyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /*
            | Dolu ise görsel O VARYANTA ait ("kırmızı olanın fotoğrafı").
            | Boş ise ürünün genel görseli.
            |
            | `cascadeOnDelete`: varyant silinince ona özel görsel de gider.
            | Ürünün genel görselleri etkilenmiyor çünkü onların
            | `variant_id`'si zaten null.
            */
            $table->foreignId('variant_id')->nullable()
                ->constrained('product_variants')->cascadeOnDelete();

            // Marka diskine GÖRELİ yol: "products/<uuid>/<ad>.jpg"
            // Mutlak yol yazılsaydı marka klasörü taşınınca hepsi kırılırdı.
            $table->string('path', 255);

            // Erişilebilirlik ve SEO için. Boş bırakılabilir ama boş
            // bırakılırsa vitrin ürün başlığını kullanacak (1B.5).
            $table->string('alt', 200)->nullable();

            $table->smallInteger('position')->default(0);

            $table->timestampsTz();

            // Ürün sayfası görselleri sırayla çekiyor — en sık sorgu.
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
