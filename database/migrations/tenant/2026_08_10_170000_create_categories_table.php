<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori ağacı. (PLAN.md 1B-K6)
 *
 * İki alan birlikte tutuluyor, biri diğerinin eksiğini kapatıyor:
 *   `parent_id` → ağacı DÜZENLEMEK için doğru yapı
 *   `path`      → SORGU için tekrarlı veri
 *
 * "Giyim ve altındaki her şey" en sık çalışacak vitrin sorgusu. `parent_id`
 * tek başına özyinelemeli CTE gerektirir (her seviye ayrı tur); `path` ile
 * tek ön ek taraması yetiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            /*
            | Üst kategori. Kök kategorilerde null.
            |
            | ⚠️ `cascadeOnDelete` YOK — bilerek. Alt kategorisi olan bir
            | kategori silinemiyor (CategoryService). Cascade olsaydı marka
            | "Giyim"i silince altındaki 40 kategori ve onlara bağlı ürün
            | ilişkileri de sessizce giderdi.
            */
            $table->foreignId('parent_id')->nullable()->constrained('categories');

            $table->string('name', 120);

            /*
            | Adres: /k/{slug} — kategori YOLU İÇERMİYOR (1B-K9 ile aynı
            | gerekçe: kategori taşınınca adres kırılmasın).
            | Bu yüzden slug mağaza genelinde benzersiz.
            |
            | ⚠️ Benzersizlik SLUG üzerinden, küçük harf üzerinden DEĞİL:
            | Türkçe'de küçük harf çevrimi bölünüyor ('İç Giyim' → 'i̇ç giyim'
            | ama 'IÇ GIYIM' → 'iç giyim'). 1B.1'de ölçülüp karara bağlandı.
            */
            $table->string('slug', 140)->unique();

            /*
            | Atalarının id zinciri, kendisi dahil: "/1/5/12/"
            |
            | Baştaki ve sondaki eğik çizgi bilerek: "/1/5/" ön eki yalnızca
            | 5'in altındakileri yakalıyor. Sonda çizgi olmasaydı "/1/5"
            | ön eki "/1/50/" ile de eşleşirdi — ve bu HATA VERMEZDİ.
            |
            | ⚠️ SLUG DEĞİL ID zinciri (Magento da öyle: path = "1/2/3").
            | Slug tutulsaydı marka 'tisort' → 't-shirt' düzeltmesi yapınca
            | alt ağacın TAMAMININ path'i yeniden yazılırdı.
            */
            $table->string('path', 255);

            // Derinlik: kök = 0. Menüde "2 seviye göster" sorusunu path
            // ayrıştırmadan cevaplıyor.
            $table->smallInteger('level')->default(0);

            // Aynı seviyedeki kardeşler arası sıra.
            $table->smallInteger('position')->default(0);

            $table->timestampsTz();
        });

        /*
        | ⚠️ `text_pattern_ops` ŞART.
        |
        | Türkçe collation altında düz btree indeksi `LIKE '/1/5/%'` için
        | KULLANILMIYOR — sorgu çalışır, sadece her seferinde tam tarama
        | yapar. 100 kategoride fark edilmez, 2000'de edilir. Sessiz
        | yavaşlığın tipik kaynağı.
        */
        DB::statement('CREATE INDEX categories_path_prefix ON categories (path text_pattern_ops)');

        // Kardeşleri sırayla çekmek en sık ikinci sorgu (menü).
        DB::statement('CREATE INDEX categories_parent_position ON categories (parent_id, position)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
