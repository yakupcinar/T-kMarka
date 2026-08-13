<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KVKK veri talepleri. (2G)
 *
 * ★ Talebin KENDİSİ kayıt altında (2G-K4): "sildim mi silmedim mi"
 * sorusunun cevabı kalmalı. Ama kayıt kişisel veri TAŞIMAMALI — yoksa
 * silme kaydı, silinen verinin kopyası olurdu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // App\Enums\DataRequestType · DataRequestStatus
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');

            /*
            | ⚠️ GEÇİCİ. Doğrulama maili buraya gidiyor; talep tamamlanınca
            | TEMİZLENİYOR. Kalıcı olsaydı anonimleştirme kaydı, silinen
            | e-postanın kopyasını saklardı.
            */
            $table->string('email', 190)->nullable();

            /*
            | ⚠️ KALICI ama GERİ ÇEVRİLEMEZ iz. Denetimde "bu adres için
            | talep var mıydı" sorusu cevaplanabilsin diye; adresin
            | kendisi okunamıyor.
            */
            $table->string('email_hash', 64);

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            /*
            | ⚠️ Doğrulama jetonu — TAHMİN EDİLEMEZ olmalı. Sipariş
            | numarası ardışık (1D-K4); jeton da öyle olsaydı talep
            | doğrulaması hiçbir şey korumazdı.
            */
            $table->string('token', 64)->unique();
            $table->timestampTz('expires_at');

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->timestampsTz();

            $table->index(['status', 'expires_at']);
            $table->index('email_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
