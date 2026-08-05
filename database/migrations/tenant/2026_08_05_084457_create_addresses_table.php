<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müşterinin ADRES DEFTERİ. (docs/domain-model.md §6)
 *
 * ⚠️ BU TABLO SİPARİŞ ADRESİ DEĞİL.
 *
 * Sipariş verilirken adres `orders` tablosuna KOPYALANIR, buraya BAĞLANMAZ.
 *
 *   YANLIŞ   orders.address_id → addresses.id
 *            müşteri altı ay sonra adresini düzeltir
 *            → geçmiş siparişlerin "nereye gitti" bilgisi de değişir
 *            → kargo takibi, fatura, iade adresi hepsi bozulur
 *
 *   DOĞRU    orders.shipping_city, shipping_line1, ...  (kopya)
 *            adres defteri değişse de sipariş fotoğrafı sabit kalır
 *
 * Aynı kural fiyat, ürün adı ve KDV oranı için de geçerli (§7).
 *
 * Misafir müşterinin adres defteri yok — adresi doğrudan siparişe yazılır.
 * Bu yüzden `customer_id` zorunlu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // "Ev", "İş", "Annemler" — müşterinin kendi verdiği etiket.
            $table->string('title', 60);

            // Adresteki kişi müşteriden farklı olabilir (hediye gönderimi).
            $table->string('full_name', 120);
            $table->string('phone', 20);

            // Türkiye adres yapısı: il → ilçe → mahalle
            $table->string('city', 60);
            $table->string('district', 60);
            $table->string('neighborhood', 100)->nullable();

            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('postal_code', 10)->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            // Müşterinin adreslerini listelemek en sık yapılacak sorgu.
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
