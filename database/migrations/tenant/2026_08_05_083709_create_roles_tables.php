<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rol ve izin sistemi — üç tablo tek dosyada. (docs/domain-model.md §3)
 *
 * Üçü birlikte anlamlı ve birbirine bağlı: `role_user` ve `role_permissions`
 * `roles` olmadan var olamaz. Ayrı dosyalara bölünseydi sıralarının doğru
 * kalmasına güvenmek zorunda kalırdık.
 *
 * ⚠️ Neden sabit rol listesi (enum) değil de tablo:
 *   "Depocu siparişi görsün ama iade yapamasın" isteği ilk aydan gelir.
 *   Enum'da bu her seferinde KOD değişikliği; tabloda bir SATIR.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Roller ──────────────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->string('name', 60)->unique();

            // Kurulumda gelen dört rol (Sahip · Yönetici · Katalog ·
            // Sipariş & Destek) silinemesin diye. Marka kendi rolünü
            // ekleyebilir, sistem rollerini kaldıramaz. (1A.3'te uygulanacak)
            $table->boolean('is_system')->default(false);

            $table->timestampsTz();
        });

        // ── Personel ↔ Rol (pivot) ──────────────────────────────────────
        // ÇOKTAN ÇOĞA: bir personelin birden çok rolü, bir rolde birden çok
        // personel olabilir. İlişkiyi ancak arada bir tablo ifade edebilir.
        Schema::create('role_user', function (Blueprint $table) {
            // foreignId + constrained(): kolonu açar VE veritabanı seviyesinde
            // yabancı anahtar kısıtı kurar. Tablo adını kolon adından çıkarır
            // (role_id → roles, user_id → users).
            //
            // Kısıtın anlamı: olmayan bir role/kullanıcıya atıf YAPILAMAZ.
            // Uygulama hata yapsa bile veritabanı reddeder.
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // cascadeOnDelete: rol silinirse ona ait eşleşmeler de silinir.
            // Olmasaydı "artık var olmayan role atıf" satırları kalırdı.
            //
            // ⚠️ users SOFT DELETE kullanıyor — `delete()` satırı gerçekten
            // silmiyor, sadece deleted_at doldurulıyor. Yani kullanıcı tarafı
            // için cascade FİİLEN çalışmıyor; personel "silindiğinde" rol
            // bağları duruyor. Doğru davranış bu: personel geri alınırsa
            // rolleri de geri gelir. Kalıcı silme yapılırsa cascade devreye girer.

            // Aynı kişiye aynı rol iki kez atanamasın.
            // Bileşik birincil anahtar: hem benzersizliği hem hızlı aramayı verir.
            $table->primary(['role_id', 'user_id']);
        });

        // ── Rol → İzinler ───────────────────────────────────────────────
        // BİRDEN ÇOĞA: bir rolün çok izni var, her izin satırı tek role ait.
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            // İzin adı serbest metin DEĞİL — kodda tanımlı sabit listeden
            // gelir (product.view, order.fulfill, settings.write ...).
            // Panelden yeni izin TÜRÜ üretilemez; sadece role atanır.
            // (domain-model §3 kapsam sınırı)
            $table->string('permission', 60);

            $table->primary(['role_id', 'permission']);
        });
    }

    public function down(): void
    {
        // ⚠️ Ters sırada: önce bağlı olanlar, sonra bağlandıkları.
        // roles önce silinseydi yabancı anahtar kısıtı buna izin vermezdi.
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
