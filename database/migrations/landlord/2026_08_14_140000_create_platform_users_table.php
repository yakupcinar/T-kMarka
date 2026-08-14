<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform yöneticileri — ÜÇÜNCÜ kimlik alanı. (3C)
 *
 * ★ Ne müşteri ne personel: TıkMarka'yı işleten kişi.
 *
 * ```
 * customer  markanın müşterisi      marka şeması
 * staff     markanın personeli      marka şeması
 * platform  BİZ                     MERKEZ şema   ← bu
 * ```
 *
 * ⚠️ Bu kimliğin yetkisi BÜTÜN MARKALARA uzanıyor — sistemdeki en tehlikeli
 * yetki. Marka personeliyle aynı tabloda tutulsaydı bir markanın sahibi
 * kendini platform yöneticisi yapabilirdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_users', function (Blueprint $tablo): void {
            $tablo->id();
            $tablo->uuid('uuid')->unique();

            $tablo->string('name');
            $tablo->string('email')->unique();
            $tablo->string('password');

            /*
            | ⚠️ Silmek yerine KAPATMAK: ayrılan yöneticinin kaydı duruyor
            | ama girişi kapanıyor. Silinseydi geçmiş işlemlerin "kim yaptı"
            | bilgisi de giderdi.
            */
            $tablo->boolean('is_active')->default(true);

            $tablo->timestampTz('last_login_at')->nullable();

            $tablo->timestampsTz();
        });

        /*
        | ⚠️ E-posta KÜÇÜK HARF kısıtı — `citext` marka şemasında çalışmıyor
        | diye 1A.1'de bulunmuştu; merkez şemada çalışırdı ama aynı deseni
        | kullanıyoruz ki iki taraf ayrışmasın. Kısıt olmadan `Admin@x.com`
        | ve `admin@x.com` iki ayrı hesap olurdu.
        */
        DB::statement('ALTER TABLE platform_users ADD CONSTRAINT platform_users_email_lowercase CHECK (email = lower(email))');

        /*
        | ★ TOKEN TABLOSU MERKEZ ŞEMADA DA GEREKİYOR — ölçüldü, yoktu.
        |
        | ⚠️ `personal_access_tokens` yalnızca MARKA şemalarında vardı (1A.2).
        | Platform yöneticisi merkez bağlamda giriş yapıyor; tablo olmadan
        | token üretilemez ve giriş "tablo yok" hatasıyla düşerdi.
        |
        | ⚠️ Aynı ada sahip iki tablo, iki AYRI şemada duruyor ve bu
        | bilinçli: marka token'ı merkez token'ıyla aynı yerde tutulsaydı
        | bir markanın token'ı merkez uçlarda denenebilirdi.
        */
        Schema::create('personal_access_tokens', function (Blueprint $tablo): void {
            $tablo->id();
            $tablo->morphs('tokenable');
            $tablo->string('name');
            $tablo->string('token', 64)->unique();
            $tablo->text('abilities')->nullable();
            $tablo->timestampTz('last_used_at')->nullable();
            $tablo->timestampTz('expires_at')->nullable();
            $tablo->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('platform_users');
    }
};
