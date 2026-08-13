<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terk edilmiş ödeme hatırlatması. (2F-K3)
 *
 * ★ Hatırlatma BİR KEZ gider; gönderildiği an buraya yazılıyor.
 *
 * ⚠️ İşaretlenmeseydi zamanlanmış görev her koşumunda aynı müşteriye
 * tekrar mail atardı — hata vermeden, saatte bir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $tablo): void {
            $tablo->timestampTz('abandoned_reminded_at')->nullable();

            /*
            | ⚠️ Kısmi indeks: yalnızca HENÜZ HATIRLATILMAMIŞ satırlar.
            | Tam indeks, zamanla büyüyen "gönderildi" yığınını da taşırdı;
            | sorgunun aradığı şey ise hep `null` olanlar.
            */
            $tablo->index(['payment_status', 'created_at'], 'orders_abandoned_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $tablo): void {
            $tablo->dropIndex('orders_abandoned_idx');
            $tablo->dropColumn('abandoned_reminded_at');
        });
    }
};
