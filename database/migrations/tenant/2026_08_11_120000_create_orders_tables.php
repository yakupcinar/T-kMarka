<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sipariş ve donmuş satırlar. (docs/domain-model.md §7 · §8)
 *
 * ★ SİPARİŞ BİR FOTOĞRAFTIR. Başlık, sku, fiyat ve KDV oranı ürüne
 * bağlanmıyor, KOPYALANIYOR. Join'lenseydi marka yarın fiyatı
 * değiştirdiğinde geçmiş siparişlerin tutarı da değişirdi.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | Sipariş numarası dizisi: TM-2026-000123 (1D-K4).
        |
        | ⚠️ `MAX(order_number) + 1` DEĞİL. İki eşzamanlı sipariş aynı
        | numarayı okur ve ikisi de aynı numarayı yazmaya çalışırdı.
        | PostgreSQL dizisi (sequence) eşzamanlılıkta güvenli ve
        | transaction geri sarılsa bile numara tekrar KULLANILMAZ —
        | muhasebede numara atlaması, numara tekrarından iyidir.
        |
        | Dizi MARKA ŞEMASINDA: her markanın kendi sayacı var.
        */
        DB::statement('CREATE SEQUENCE order_number_seq START 1');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Müşteriye gösterilen numara.
            $table->string('order_number', 20)->unique();

            // ⚠️ Misafir siparişinde NULL — misafir alışverişi var (M-1).
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            /*
            | ⚠️ HER ZAMAN DOLU. Misafir siparişinin tek iletişim kanalı bu;
            | boş kalırsa müşteriye kargo bilgisi bile gönderilemez.
            */
            $table->string('email', 190);

            // İKİ AYRI EKSEN (§7) — App\Enums\PaymentStatus / FulfillmentStatus
            $table->string('payment_status', 30)->default('pending');
            $table->string('fulfillment_status', 20)->default('unfulfilled');

            /*
            | TUTARLAR — hepsi numeric(12,2). float YASAK (§0):
            | 0.1 + 0.2 !== 0.3 hatası para tutarında kuruş kaydırır.
            */
            $table->decimal('items_total', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);

            /*
            | ⚠️ `tax_total` `grand_total`'a EKLENMEZ (§8.2).
            | Fiyatlar zaten KDV dâhil; bu alan BİLGİ amaçlı, faturada
            | gösteriliyor. Eklenseydi her siparişte müşteriden fazladan
            | KDV tahsil edilirdi — vergi dâhil modelde en sık yapılan hata.
            */
            $table->decimal('tax_total', 12, 2)->default(0);

            $table->decimal('grand_total', 12, 2);

            /*
            | ADRES KOPYALARI — deftere BAĞLANMIYOR (§6/§7).
            | Bağlansaydı müşteri altı ay sonra adresini düzelttiğinde
            | geçmiş siparişlerin "nereye gitti" bilgisi de değişirdi.
            */
            /*
            | ⚠️ Kolonlar TEK TEK yazılıyor, döngüyle DEĞİL.
            |
            | İlk yazımda `foreach (['shipping','billing'] …)` ile
            | üretilmişlerdi — kısa ama statik analiz onları GÖREMEDİ:
            | Larastan model alanlarını migration'ı okuyarak çıkarıyor ve
            | döngüyü çalıştıramıyor. Sonuç: `$order->billing_city`
            | "tanımsız özellik" diye işaretlendi.
            |
            | Zekice yazım, aracı kör etti. Uzun hâli hem analiz için hem
            | `grep` için daha iyi.
            */
            $table->string('shipping_full_name', 120);
            $table->string('shipping_phone', 20);
            $table->string('shipping_city', 60);
            $table->string('shipping_district', 60);
            $table->string('shipping_neighborhood', 100)->nullable();
            $table->string('shipping_line1', 255);
            $table->string('shipping_line2', 255)->nullable();
            $table->string('shipping_postal_code', 10)->nullable();

            $table->string('billing_full_name', 120);
            $table->string('billing_phone', 20);
            $table->string('billing_city', 60);
            $table->string('billing_district', 60);
            $table->string('billing_neighborhood', 100)->nullable();
            $table->string('billing_line1', 255);
            $table->string('billing_line2', 255)->nullable();
            $table->string('billing_postal_code', 10)->nullable();

            // Kurumsal fatura (§8.3). Faz 1'de toplanıyor ve biçimsel
            // doğrulanıyor; e-fatura gönderimi Faz 5.
            $table->string('billing_tax_number', 11)->nullable();
            $table->string('billing_tax_office', 100)->nullable();

            $table->timestampTz('terms_accepted_at');

            /*
            | ★ ONAYLANAN SÖZLEŞMENİN KENDİSİ (1D-K2).
            |
            | Önce `terms_version varchar(20)` planlanmıştı; o satır yasal
            | metinler `settings`'te dururken yazılmıştı. 1A.4'te metinler
            | sürümlü kendi tablosuna alındı.
            |
            | `restrictOnDelete`: sürüm satırı zaten silinemiyor (tetik,
            | 1A.4) — bu ikinci savunma hattı.
            |
            | ⚠️ Sipariş GÖSTERİLEN sürüme bağlanır, o anki güncele değil.
            | "En son sürüm" demek, kişinin görmediği bir metne imza
            | attırmaktır.
            */
            $table->foreignId('legal_version_id')
                ->constrained('legal_document_versions')->restrictOnDelete();

            $table->timestampTz('placed_at');
            $table->timestampsTz();

            $table->index(['payment_status', 'placed_at']);
            $table->index('customer_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            /*
            | ⚠️ Yalnızca REFERANS ve NULL olabilir. Varyant silinse bile
            | satır yaşamaya devam ediyor — altındaki kopya alanlar
            | siparişin ne olduğunu tek başına anlatıyor.
            */
            $table->foreignId('variant_id')->nullable()
                ->constrained('product_variants')->nullOnDelete();

            // ★ KOPYALAR — hepsi satın alma anındaki hâliyle donuyor.
            $table->string('product_title', 200);
            $table->jsonb('variant_options');
            $table->string('sku', 64);
            $table->decimal('unit_price', 12, 2);

            $table->integer('quantity');
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            // ★ KDV oranı da KOPYA: marka oranı değiştirse bile eski
            // siparişin faturası değişmemeli.
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 12, 2);

            $table->timestampsTz();

            $table->index('order_id');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_adet_pozitif CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        DB::statement('DROP SEQUENCE IF EXISTS order_number_seq');
    }
};
