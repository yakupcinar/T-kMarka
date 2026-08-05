<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Markanın MÜŞTERİLERİ. (docs/domain-model.md §3)
 *
 * Personel ayrı tabloda (`users`) tutulacak — aynı tabloda olsalardı
 * "müşteri bir hatayla panele girebilir mi" sorusu sürekli açık kalırdı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Dışarıya (URL, API) açılan kimlik. Sıra numarası verilseydi
            // müşteri sayısı ve kayıt hızı dışarıdan tahmin edilebilirdi.
            $table->uuid('uuid')->unique();

            $table->string('name', 120);

            // NULL olabilir → MİSAFİR sipariş (domain-model §6).
            // PostgreSQL'de birden çok NULL benzersizliği bozmaz, yani
            // sınırsız misafir müşteri olabilir.
            //
            // ⚠ citext KULLANILMIYOR — denendi, marka şemasında ÇALIŞMIYOR:
            //   eklenti public şemasında duruyor, marka bağlantısının
            //   search_path'i ise yalnızca tenant_xxx. Operatörler
            //   bulunamayınca PostgreSQL sessizce düz metin karşılaştırmasına
            //   düşüyor ve "Ali@site.com" ile "ali@site.com" FARKLI sayılıyor.
            //   Hata vermiyor — sadece yanlış sonuç veriyor.
            //   public'i search_path'e eklemek de çözüm değil: o zaman marka
            //   şemasında olmayan tablo sessizce merkezdekine düşer.
            //
            // Çözüm: e-posta SINIRDA küçültülüyor (Customer modelinde),
            // alan adlarında kullandığımız desenin aynısı.
            $table->string('email', 190)->nullable()->unique();

            // Misafirde parola yok.
            $table->string('password')->nullable();

            $table->string('phone', 20)->nullable();

            // KVKK: pazarlama izni AÇIK RIZA ile alınır → varsayılan kapalı.
            $table->boolean('accepts_marketing')->default(false);

            $table->timestampTz('email_verified_at')->nullable();

            // ⚠ timestamps() DEĞİL. Laravel'in varsayılanı
            // "timestamp without time zone" üretiyor; docs/domain-model.md §0
            // timestamptz (UTC) diyor. Saat dilimi taşımayan bir damga farklı
            // sunucularda ve yaz saati geçişinde kayar. (0.4b bulgusu)
            $table->timestampsTz();

            $table->softDeletesTz();
        });

        // Model küçültse bile ham SQL ile büyük harfli kayıt girilebilirdi ve
        // o zaman unique kısıtı delinirdi. Bu kontrol veritabanı seviyesinde
        // garanti veriyor: e-posta küçük harf değilse kayıt REDDEDİLİR.
        DB::statement(
            'ALTER TABLE customers ADD CONSTRAINT customers_email_lowercase
             CHECK (email IS NULL OR email = lower(email))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
