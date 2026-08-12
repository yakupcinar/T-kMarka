<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Olay kaydı. (docs/domain-model.md §11 · 1F)
 *
 * ⚠️ Bu tablo İŞİN KENDİSİ DEĞİL, işin izidir. Yazılamaması siparişi
 * bozmamalı (1F-K3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            /*
            | ⚠️ `nullOnDelete` — müşteri silinse de olay YAŞIYOR.
            | Cascade olsaydı KVKK silme talebi (Faz 2) geçmiş satış
            | istatistiğini de silerdi; oysa istenen kişisel bağın
            | kopması, sayının kaybolması değil.
            */
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            /*
            | ⚠️ ŞİMDİLİK HEP BOŞ (1F-K2). Misafiri tanımanın yolu vitrin
            | teknolojisi seçilince belli olacak (M-3, Faz 4). Kolon
            | şimdiden açık çünkü sonradan eklemek, iki farklı kimlik
            | biçimini birleştirmek demek.
            */
            $table->uuid('anon_id')->nullable();

            // App\Enums\EventType
            $table->string('type', 40);

            /*
            | ⚠️ KİŞİSEL VERİ GİRMEZ (1F-K4): ad, e-posta, adres yok —
            | yalnızca kimlikler ve sayılar. Faz 2'deki anonimleştirme
            | işinin bu tabloyu taraması gerekmesin diye.
            */
            $table->jsonb('payload')->nullable();

            /*
            | ⚠️ `created_at` DEĞİL: olayın OLDUĞU an ile KAYDEDİLDİĞİ an
            | farklı. Kuyruk gecikirse ikisi dakikalarca ayrışır ve
            | rapor yanlış saati gösterir.
            */
            $table->timestampTz('occurred_at');

            $table->timestampsTz();

            // Rapor sorgusu: "şu tipte, şu tarih aralığında kaç olay".
            $table->index(['type', 'occurred_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
