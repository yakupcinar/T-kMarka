<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rezervasyonu siparişe bağlar.
 *
 * ⚠️ Rezervasyon önce SEPETE bağlıydı (1D.1). Sipariş oluştuktan sonra
 * sepet `converted` oluyor ve ödemenin sonucuna göre rezervasyonları
 * kesinleştirmek/serbest bırakmak gerekiyor — o an elimizde SİPARİŞ var,
 * sepet değil. Bağ olmasaydı "bu siparişin rezervasyonları hangileri"
 * sorusunun cevabı yoktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            // `nullOnDelete` değil `restrictOnDelete`: sipariş silinemez
            // zaten (silinmemeli), ama denetim izi de kopmamalı.
            $table->foreignId('order_id')->nullable()->after('cart_id')
                ->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
