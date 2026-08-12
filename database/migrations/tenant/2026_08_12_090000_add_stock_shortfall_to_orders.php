<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ 1E-K5: "PARA GELDİ, MAL YOK" işareti.
 *
 * Senaryo kaçınılmaz — sağlayıcıyı 60 dakikaya zorlayamıyoruz:
 *
 *   10:00  sipariş verildi, 3 tişört rezerve
 *   11:05  rezervasyon öldü (60 dk), stok serbest kaldı
 *   11:06  başka müşteri o 3 tişörtü aldı
 *   11:08  webhook: "ödeme başarılı"          ← PARA ÇEKİLDİ, MAL YOK
 *
 * Karar (1E-K5): ödemeyi REDDETMİYORUZ, siparişi KABUL EDİP İŞARETLİYORUZ.
 * Tedarik edebilen marka müşteriyi kaybetmesin; edemeyen iade etsin.
 * Karar teknik değil TİCARİ, o yüzden markaya bırakılıyor.
 *
 * ⚠️ Shopify'ın uyarısı bu kolonun VARLIK SEBEBİ: sorun eksi stoğa izin
 * vermek değil, HABER VERMEDEN izin vermek. Bu yüzden alan yalnızca
 * veritabanında durmuyor — panelde sipariş listesinde de görünüyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('stock_shortfall')->default(false);

            /*
            | Kısmi indeks: panelde "sorunlu siparişler" sorgusu yalnızca
            | işaretli satırlara bakıyor. Düz indeks olsaydı milyonlarca
            | sağlam siparişi de indekslerdik — bunlar istisna olmalı.
            */
            $table->index('stock_shortfall');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['stock_shortfall']);
            $table->dropColumn('stock_shortfall');
        });
    }
};
