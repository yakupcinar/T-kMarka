<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Markanın PERSONELİ — panele girenler. (docs/domain-model.md §3)
 *
 * Müşteriler ayrı tabloda (`customers`). Aynı tabloda olsalardı "müşteri bir
 * hatayla panele girebilir mi" sorusu sürekli açık kalırdı; ayrı tabloda bu
 * soru sorulamaz hale geliyor.
 *
 * Tablo adı `users` — Laravel'in varsayılan adı. Ama içeriği bizim:
 * Laravel'in ürettiği migration silindi, bu onun yerine geçiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Dışarıya açılan kimlik — sıra numarası personel sayısını sızdırır.
            $table->uuid('uuid')->unique();

            $table->string('name', 120);

            // ⚠ customers'ın aksine ZORUNLU: personel giriş yapmak zorunda,
            // girişin anahtarı e-posta.
            // citext kullanılmıyor — sebebi customers migration'ında yazılı
            // (kiracı şemasında çalışmıyor). Sınırda küçültme + CHECK.
            $table->string('email', 190)->unique();

            // ⚠ customers'ın aksine ZORUNLU: parolasız personel olmaz.
            $table->string('password');

            // Kurulumda oluşan sahip. Bir ROL değil, EMNİYET KİLİDİ:
            // son yöneticinin kendi yetkisini düşürüp panele kilitlenmesini
            // engellemek için. Silinemez, rolü düşürülemez (1A.3).
            $table->boolean('is_owner')->default(false);

            // ⚠ timestamps() DEĞİL — timestamptz (docs/domain-model.md §0)
            $table->timestampsTz();

            $table->softDeletesTz();
        });

        // Model küçültse bile ham SQL ile büyük harfli kayıt girilebilirdi ve
        // unique kısıtı delinirdi. Bu kontrol veritabanı seviyesinde garanti verir.
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_email_lowercase
             CHECK (email = lower(email))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
