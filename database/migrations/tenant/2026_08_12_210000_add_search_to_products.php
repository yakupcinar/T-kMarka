<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün araması. (2C)
 *
 * ★ İKİ KOLON, İKİ FARKLI İŞ:
 *
 *   search_vector  PostgreSQL'in TÜRKÇE sözlüğüyle kök bulma
 *                  ("tişörtler" → "tişört")
 *   search_text    ASCII'ye indirgenmiş düz metin — yazım hatası
 *                  toleransı (trigram) bunun üzerinde çalışıyor
 *
 * ⚠️ Neden iki tane: ÖLÇÜLDÜ.
 *   public.similarity('tisort','tişört') = 0,27  → eşik 0,3'ün ALTINDA
 *   public.similarity('tisort','tisort') = 1,00
 * Türkçe karakter, trigram benzerliğini eşiğin altına düşürüyor. Her iki
 * tarafı da ASCII'ye indirmeden "yazım hatası toleransı" çalışmıyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('search_text')->nullable();
        });

        /*
        | ⚠️ `tsvector` Laravel şema yapıcısında yok — ham SQL.
        |
        | GENERATED kolon KULLANILMIYOR: içerik `title + brand + model +
        | sku`'dan geliyor ve sku varyantta. Tek satırdan türetilemediği
        | için servis dolduruyor.
        */
        DB::statement('ALTER TABLE products ADD COLUMN search_vector tsvector');

        /*
        | ⚠️ TÜRKÇE sözlük PostgreSQL'de HAZIR ve `pg_catalog`'ta —
        | yani marka şemasından görünüyor. citext/pg_trgm'in aksine
        | nitelendirmeye gerek yok. (Ölçüldü.)
        */
        DB::statement('CREATE INDEX products_search_vector_idx ON products USING gin (search_vector)');

        /*
        | ⚠️ ★ `public.gin_trgm_ops` — NİTELİKLİ yazılmak ZORUNDA.
        |
        | Eklenti `public`'te, marka `search_path`'i onu görmüyor.
        | citext (1A) ve ltree (1B) ile aynı tuzak, ÜÇÜNCÜ KEZ.
        | Niteliksiz yazılsaydı migration "operator class does not exist"
        | ile düşerdi — bu sefer gürültülü, ama yine de aynı sebep.
        */
        DB::statement('CREATE INDEX products_search_text_trgm_idx ON products USING gin (search_text public.gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_search_text_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS products_search_vector_idx');
        DB::statement('ALTER TABLE products DROP COLUMN IF EXISTS search_vector');

        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('search_text'));
    }
};
