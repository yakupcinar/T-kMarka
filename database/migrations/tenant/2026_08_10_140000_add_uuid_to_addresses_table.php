<?php

use App\Models\Address;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adreslere dışarıya açılan kimlik ekler.
 *
 * ⚠️ Neden id yetmiyor: adres uçları `/api/addresses/{adres}` biçiminde.
 * Ardışık id ile müşteri 41, 42, 43 diye tarayıp mağazadaki toplam adres
 * sayısını kabaca çıkarabilir. Veri sızmıyor (sorgu zaten müşteriye
 * daraltılmış, başkasının adresi 404 dönüyor) ama SAYI sızıyor.
 *
 * `id` yerine geçmiyor, YANINA ekleniyor: id içeride hızlı ve küçük kalıyor,
 * uuid dışarıya açılan tahmin edilemez kimlik oluyor — `customers` ve
 * `users` ile aynı desen (docs/domain-model.md §0).
 *
 * UUIDv7 kullanılıyor (Laravel'in `HasUuids` özelliği `Str::uuid7()`
 * üretiyor): zaman sıralı olduğu için indeks yaprakları sona ekleniyor,
 * v4'ün rastgele dağılımındaki indeks parçalanması olmuyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | ÜÇ ADIMDA ekleniyor. Tek adımda `->unique()->nullable(false)`
        | denseydi mevcut satırı olan bir markada migration çökerdi:
        | var olan satırların uuid'si NULL olurdu ve NOT NULL kısıtı
        | anında ihlal edilirdi.
        */
        Schema::table('addresses', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Mevcut satırlar — PHP tarafında üretiliyor ki bunlar da v7 olsun.
        // PostgreSQL'in `gen_random_uuid()` fonksiyonu v4 üretir; karışık
        // sürümlü bir kolon istemiyoruz.
        Address::withTrashed()->whereNull('uuid')->each(function (Address $adres) {
            $adres->uuid = (string) Str::uuid7();
            $adres->saveQuietly();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
