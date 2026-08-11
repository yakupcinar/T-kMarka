<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün · ürünün kullandığı eksenler · varyantlar. (PLAN.md 1B-K1…K5)
 *
 * ⚠️ Ürünün SAHİBİ YOK. Pazaryerindeki `seller_id` burada yok — şemanın
 * tamamı zaten tek markaya ait. M-2'nin iş mantığı üzerindeki en görünür
 * kazancı bu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            /*
            | Tek kategori (1B-K7). Çoklu üyelik koleksiyonun işi (Faz 2).
            |
            | `restrictOnDelete`: içinde ürün olan kategori silinemez.
            | `nullOnDelete` olsaydı marka kategoriyi silince 300 ürün
            | sessizce kategorisiz kalır, menüden düşer ve kimse fark etmezdi.
            */
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('title', 200);

            // Adres düz: /urun/{slug} — kategori yolu içermiyor (1B-K9).
            $table->string('slug', 220)->unique();

            $table->text('description')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();

            // Kategoriye özel serbest alanlar: {"kumaş":"pamuk","yaka":"bisiklet"}
            $table->jsonb('attributes')->nullable();

            /*
            | KDV oranı ÜRÜNDE, varyantta değil (1B-K2): aynı ürünün farklı
            | bedeni farklı vergiye tabi olmaz.
            |
            | ⚠️ NOT NULL ve varsayılansız: servis, boş bırakılırsa
            | `settings.tax.default_rate`'ten dolduruyor. Kolonda varsayılan
            | olsaydı marka oranı değiştirdiğinde eski ürünler eski oranda
            | kalır mı yoksa değişir mi belirsizleşirdi — sipariş fotoğrafı
            | ilkesi gereği ürünün kendi oranı yazılı olmalı.
            */
            $table->decimal('tax_rate', 5, 2);

            // App\Enums\ProductStatus
            $table->string('status', 20)->default('draft');

            $table->timestampsTz();
            $table->softDeletesTz();

            // Vitrin listesi hep bu ikisiyle daralıyor.
            $table->index(['status', 'category_id']);
        });

        // Kategoriye özel alanlarda arama/filtre için (Faz 2).
        DB::statement('CREATE INDEX products_attributes_gin ON products USING gin (attributes)');

        /*
        | ÜRÜN ↔ EKSEN — ürün hangi eksenleri kullanıyor ve hangi sırada.
        |
        | Eksenin kendisi MAĞAZA seviyesinde (1B-K3); bu tablo yalnızca
        | "bu ürün Renk ve Beden kullanıyor, önce Renk gösterilsin" diyor.
        */
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // `restrictOnDelete`: ürünlerde kullanılan eksen silinemez.
            // (1B.1'deki TODO burada kapanıyor.)
            $table->foreignId('option_id')->constrained()->restrictOnDelete();

            // Ürün sayfasında seçicilerin sırası — eksenin genel sırasını ezer.
            $table->smallInteger('position')->default(0);

            $table->unique(['product_id', 'option_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Stok kodu — marka genelinde benzersiz.
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable();

            /*
            | Eksen → DEĞER SLUG'I:  {"renk":"kirmizi","beden":"m"}
            |
            | ⚠️ `jsonb`, `json` DEĞİL. Ölçüldü:
            |     jsonb: {"renk":"K","beden":"M"} = {"beden":"M","renk":"K"} → TRUE
            |     json : aynı karşılaştırma                                  → FALSE
            | `json` olsaydı aşağıdaki UNIQUE kısıtı, anahtar sırası farklı
            | yazılmış bir kopyayı YAKALAMAZDI.
            |
            | ⚠️ Gösterim değeri değil SLUG saklanıyor ("Kırmızı" değil
            | "kirmizi"): jsonb karşılaştırması büyük/küçük harf duyarlı
            | ({"renk":"K"} ≠ {"renk":"k"}), ayrıca filtre adresi de slug
            | kullanıyor (?renk=kirmizi).
            |
            | Tek seçenekli üründe `{}` — varyant yine de VAR (1B-K1).
            */
            $table->jsonb('options');

            // Tüketiciye gösterilen, KDV DÂHİL tutar (domain-model §8).
            $table->decimal('price', 12, 2);

            // Üstü çizili fiyat.
            $table->decimal('compare_at_price', 12, 2)->nullable();

            // ⚠️ Maliyet — kâr raporu için. VİTRİNE ASLA ÇIKMAZ (1B-K10).
            $table->decimal('cost_price', 12, 2)->nullable();

            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestampsTz();
            $table->softDeletesTz();

            /*
            | Aynı kombinasyondan ikinci varyant olamaz (1B-K5).
            | Olsaydı "Kırmızı/M" seçen müşteri hangi stoğu düşürdüğünü
            | bilemezdi.
            */
            $table->unique(['product_id', 'options']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('products');
    }
};
