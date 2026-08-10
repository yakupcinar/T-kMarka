<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Varyant eksenleri ve değerleri — MAĞAZA seviyesinde. (PLAN.md 1B-K3)
 *
 * "Renk" bir kez tanımlanır, bütün ürünler aynı listeden seçer.
 *
 * ⚠️ Neden ürüne ait değil: fark ürün eklerken değil, KATEGORİ FİLTRESİNDE
 * görünüyor. Ürüne ait olsaydı 200 üründen sonra 200 ayrı "Renk" ekseni
 * birikir ve "Renk: Kırmızı" filtresi dört ayrı seçenek gösterirdi
 * (Kırmızı · kırmızı · KIRMIZI · "Kırmızı "). Magento'nun super attribute
 * modeli; Shopify da 2024'te serbest alandan tanım tablosuna geçti.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | EKSEN — "Renk", "Beden", "Boy"
        */
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Markanın yazdığı hâl: "Renk". Panelde ve vitrinde bu görünür.
            $table->string('name', 60);

            /*
            | ⚠️ BENZERSİZLİK ANAHTARI SLUG — küçük harf DEĞİL.
            |
            | Ölçüldü: Türkçe'de küçük harf çevrimi bölünüyor.
            |   'Kırmızı' → 'kırmızı'   ama  'KIRMIZI' → 'kirmizi'
            |   'İnce'    → 'i̇nce'      ama  'INCE'    → 'ince'
            | Yani 1A.1'de e-posta için kullandığımız "küçült ve karşılaştır"
            | deseni burada SESSİZCE iki ayrı eksen üretirdi.
            |
            | `Str::slug` hepsini birleştiriyor: Kırmızı · KIRMIZI · kırmızı
            | → 'kirmizi'. Ayrıca filtre adresinde de bu lazım (?renk=kirmizi).
            */
            $table->string('slug', 60)->unique();

            // Ürün sayfasında hangi seçici önce çıkacak — varsayılan sıra.
            // Ürün bazında değiştirilebilir (product_options.position, 1B.3).
            $table->smallInteger('position')->default(0);

            $table->timestampsTz();
        });

        /*
        | DEĞER — "Kırmızı", "M", "42"
        */
        Schema::create('option_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('option_id')->constrained()->cascadeOnDelete();

            $table->string('value', 60);
            $table->string('slug', 60);

            /*
            | Renk kodu (#c00) ya da kumaş görseli yolu — yalnızca renk gibi
            | eksenlerde dolu.
            |
            | ⚠️ Değerin YANINDA duruyor, üründe değil: eksen mağaza
            | seviyesinde olduğu için renk kodu da BİR KEZ yazılıyor. Ürüne
            | ait olsaydı her üründe tekrar girilir ve tonlar tutmazdı.
            | (Shopify'ın yeni modele "metafield bağı" eklemesinin sebebi bu.)
            */
            $table->string('swatch', 40)->nullable();

            $table->smallInteger('position')->default(0);

            $table->timestampsTz();

            // Aynı eksende iki "Kırmızı" olamaz. Slug üzerinden, çünkü
            // yazım farkı (KIRMIZI) aynı değeri kastediyor.
            $table->unique(['option_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_values');
        Schema::dropIfExists('options');
    }
};
