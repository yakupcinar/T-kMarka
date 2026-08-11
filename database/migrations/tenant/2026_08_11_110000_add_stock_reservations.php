<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stok rezervasyonu ve `committed` sayacı. (PLAN.md 1D-K1)
 *
 * ⚠️ Shopify'ın envanter modeli birebir bu ve orada da "her konumda TUTMASI
 * GEREKEN özdeşlik" diye tarif ediliyor:
 *
 *     on_hand − committed − damaged − safety_stock = available
 *
 * Bizde `damaged`/`safety_stock` yok (Faz 2+), dolayısıyla:
 *
 *     stock − committed = available
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            /*
            | Siparişe bağlanmış ama henüz sevk edilmemiş adet.
            |
            | ⚠️ Bu sayı MATERYALLEŞTİRİLMİŞ. Alternatifi her sorguda
            | `SUM(aktif rezervasyonlar)` almaktı — o da 1B'de kaçındığımız
            | N+1'in kardeşi olurdu: her ürün listesinde bir alt sorgu.
            |
            | ⚠️ Bedeli: iki yerde tutulan sayının tutarlı kalması gerekiyor.
            | Karşılığı 1D.5'teki gece denetimi — aktif rezervasyonların
            | toplamı bu kolona eşit mi?
            */
            $table->integer('committed')->default(0)->after('stock');
        });

        /*
        | ⚠️ Model varsayılanı da şart: kolon varsayılanı `create()`'ten
        | dönen nesneye ULAŞMIYOR (CLAUDE.md'deki kural, üç kez ısırdı).
        | ProductVariant::$attributes'a ekleniyor.
        */

        // Negatif committed = sayaç bozulmuş demektir; sessizce yaşamasın.
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT variants_committed_pozitif CHECK (committed >= 0)');

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            /*
            | Rezervasyonu hangi sepet tuttu.
            |
            | `nullOnDelete`: sepet siparişe dönüşünce silinebilir ama
            | rezervasyon kaydı DENETİM İZİ olarak kalmalı — "bu stok neden
            | bağlıydı" sorusu sonradan sorulacak.
            */
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();

            $table->integer('quantity');

            // App\Enums\ReservationStatus
            $table->string('status', 20)->default('held');

            /*
            | 15 dakika (1D-K3). Süresi dolanı ZAMANLANMIŞ GÖREV düşürüyor.
            |
            | ⚠️ O görev `tenants:run` ile sarılmak ZORUNDA (0.5, 5. tuzak).
            | Doğrudan yazılırsa merkez bağlamda koşar, hiçbir şey yapmaz;
            | rezervasyonlar asla düşmez, stok sonsuza kadar bağlı kalır ve
            | HATA DA VERMEZ.
            */
            $table->timestampTz('expires_at');

            $table->timestampsTz();

            // Süre dolumu görevi bu ikisiyle tarıyor.
            $table->index(['status', 'expires_at']);
        });

        DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT reservations_adet_pozitif CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('committed');
        });
    }
};
