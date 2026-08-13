<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koleksiyonlar. (2D)
 *
 * ★ Kategori "bu nedir", koleksiyon "nerede göstereyim" (1B-K7).
 * Ürün TEK kategoride, ÇOK koleksiyonda.
 *
 * ⚠️ Kurallı koleksiyonun üyeleri BU TABLOLARDA YOK — sorgu anında
 * hesaplanıyor (2D-K2). Saklansaydı fiyat değişince bayatlar ve kimse
 * fark etmezdi: "250₺ altı" koleksiyonunda 400₺'lik ürün dururdu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $tablo): void {
            $tablo->id();
            $tablo->uuid('uuid')->unique();

            $tablo->string('title');
            $tablo->string('slug')->unique();
            $tablo->text('description')->nullable();

            /*
            | ⚠️ Tip DEĞİŞTİRİLEBİLİR ama iki dünya KARIŞMAZ:
            | `manual` koleksiyonun `rules`'u null, `rule` koleksiyonun
            | pivot satırı yok. Karışsaydı "bu ürün neden burada" sorusu
            | cevapsız kalırdı — biri listeden, biri kuraldan gelirdi.
            */
            $tablo->string('type');
            $tablo->jsonb('rules')->nullable();

            $tablo->integer('position')->default(0);
            $tablo->boolean('is_active')->default(true);

            $tablo->timestampsTz();
            $tablo->softDeletesTz();
        });

        Schema::create('collection_product', function (Blueprint $tablo): void {
            $tablo->id();

            $tablo->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $tablo->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // Manuel koleksiyonun sırası MARKANIN kararı — vitrin bunu koruyor.
            $tablo->integer('position')->default(0);

            $tablo->timestampsTz();

            /*
            | ⚠️ Aynı ürün aynı koleksiyona iki kez giremez. Kısıt
            | olmasaydı ürün listede iki kez görünür ve sayım şişerdi.
            */
            $tablo->unique(['collection_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collections');
    }
};
