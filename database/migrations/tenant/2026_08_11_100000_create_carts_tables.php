<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sepet. (docs/domain-model.md §6 · PLAN.md 1C-K1…K5)
 *
 * ⚠️ MİSAFİR SEPETİ VAR — pazaryerindeki "giriş şart" kararının tersi.
 * Markanın kendi sitesinde zorunlu üyelik dönüşümü doğrudan düşürür; bu bir
 * "özellik eksiği" değil, satın alma engelidir (M-1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();

            /*
            | Misafir sepetinin kimliği. İstemci `X-Cart-Token` başlığında
            | taşıyor (1C-K1).
            |
            | ⚠️ KRİPTOGRAFİK RASTGELE olmak zorunda. Ardışık ya da tahmin
            | edilebilir olsaydı biri başkasının sepetini okurdu — adres yok
            | ama ne aldığı görünür.
            */
            $table->string('session_token', 64)->nullable()->unique();

            // App\Enums\CartStatus
            $table->string('status', 20)->default('active');

            // Terk edilmiş sepet hatırlatması için (Faz 3).
            $table->timestampTz('last_activity_at')->nullable();

            $table->timestampsTz();
        });

        /*
        | ⚠️ TAM OLARAK BİRİ dolu olmalı.
        |
        | Uygulama katmanına bırakılsaydı bir gün ikisi de boş bir sepet
        | oluşur ve kime ait olduğu bilinemezdi — hata da vermezdi.
        | `<>` burada XOR görevi görüyor: iki mantıksal değer farklıysa doğru.
        */
        DB::statement(<<<'SQL'
            ALTER TABLE carts ADD CONSTRAINT carts_sahip_tekil
                CHECK ((customer_id IS NOT NULL) <> (session_token IS NOT NULL))
        SQL);

        /*
        | ⚠️ Müşteri başına TEK aktif sepet (1C-K4) — KISMİ indeks.
        |
        | Düz unique olsaydı müşterinin geçmişteki `converted` sepetleri de
        | çakışırdı; sipariş verdikten sonra ikinci kez alışveriş yapamazdı.
        | Kısmi indeks yalnızca `active` satırları kapsıyor.
        */
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX carts_musteri_tek_aktif
                ON carts (customer_id) WHERE status = 'active' AND customer_id IS NOT NULL
        SQL);

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();

            /*
            | ⚠️ `restrictOnDelete`: varyant zaten yumuşak siliniyor (1B.3),
            | yani bu satır asla öksüz kalmıyor. Sert silme denenirse
            | veritabanı durduruyor.
            */
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->integer('quantity');

            $table->timestampsTz();

            /*
            | Aynı varyant sepette iki satır olamaz — adet artırılır.
            | Olsaydı "3 + 2 mi, hangisi geçerli" sorusu doğardı.
            */
            $table->unique(['cart_id', 'variant_id']);
        });

        /*
        | ⚠️ Adet en az 1. `0` bir satır değil, satırın yokluğudur; sıfır
        | adetli satır ödeme adımında "bedava ürün" gibi görünürdü.
        */
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_adet_pozitif CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
