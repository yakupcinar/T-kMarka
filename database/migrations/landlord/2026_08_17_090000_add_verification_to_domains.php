<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Özel alan adı doğrulaması. (3H)
 *
 * ★ 6 NUMARALI KARAR: DNS'i MARKA ekler, BİZ kontrol ederiz.
 *
 * ⚠️ Doğrulama olmadan on-demand TLS AÇILAMAZ. Açılsaydı marka paneline
 * `google.com` yazan biri yüzünden Caddy o alan adı için sertifika istemeye
 * çalışır, ACME doğrulaması düşer ve Let's Encrypt kotamız yanardı —
 * haftada 50 sertifikayla sınırlıyız (3-K5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $tablo): void {
            /*
            | ⚠️ `null` = doğrulanmamış. Boolean yerine TARİH: "ne zaman
            | doğrulandı" sorusu destek için gerekli ve boolean onu
            | cevaplayamazdı.
            */
            $tablo->timestampTz('verified_at')->nullable();

            /*
            | Markanın DNS'ine ekleyeceği doğrulama belirteci.
            |
            | ⚠️ Alan adı başına AYRI ve rastgele: sabit olsaydı bir markanın
            | belirtecini gören başkası kendi alan adını doğrulatabilirdi.
            */
            $tablo->string('verification_token')->nullable();

            $tablo->index('verified_at');
        });

        /*
        | ★ GERİYE DÖNÜK DOLDURMA — dördüncü kez (2C · 2F · 3B).
        |
        | ⚠️ Mevcut alan adları `tenant:create` ile açıldı ve zaten bizim
        | denetimimizde. Doldurulmasaydı bugünkü iki marka "doğrulanmamış"
        | sayılır, ask ucu onlara 404 döner ve sertifika alamazlardı —
        | yani ÇALIŞAN SİTELER on-demand TLS açıldığı an düşerdi.
        */
        DB::statement('UPDATE domains SET verified_at = created_at WHERE verified_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $tablo): void {
            $tablo->dropIndex(['verified_at']);
            $tablo->dropColumn(['verified_at', 'verification_token']);
        });
    }
};
