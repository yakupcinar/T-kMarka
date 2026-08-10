<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yasal metinler — taslak (değişken) ve sürüm (değişmez) olarak İKİ tablo.
 *
 * Neden `settings` yetmiyor: ayar "şu an geçerli değer"dir, geçmişi yoktur.
 * Yasal metnin geçmişi olmak zorunda — 15 Mart'ta verilen sipariş, 20 Mart'ta
 * değiştirilen sözleşmeye değil, KENDİ günündeki metne bağlı kalmalı.
 * Aksi hâlde müşterinin onaylamadığı bir metin, onaylamış gibi görünür.
 *
 * Aynı ilkenin fiyat tarafındaki karşılığı `docs/domain-model.md` §7:
 * sipariş bir fotoğraftır. Fark yalnızca kopyalama biçiminde:
 *   fiyat  → 8 bayt, satır satır farklı → siparişin İÇİNE kopyalanır
 *   metin  → ~15 KB, 10.000 sipariş aynısını paylaşır → SÜRÜME işaret edilir
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | TASLAK — tür başına tek satır, serbestçe güncellenir.
        |
        | Yarım kalabilmesi ÖZELLİĞİDİR: marka metni birkaç oturumda yazar.
        | Bu yüzden burada hiçbir zorunluluk denetimi yok; denetim yayınlama
        | anında koşuyor (StoreReadiness).
        */
        Schema::create('legal_document_drafts', function (Blueprint $table) {
            $table->id();

            // Enum: App\Enums\LegalDocumentType
            $table->string('type', 40)->unique();

            // nullable: "belge tanımlı ama henüz yazılmadı" ile "hiç yok"
            // ayrımı korunuyor (settings'teki `value` ile aynı gerekçe).
            $table->text('content')->nullable();

            $table->timestampsTz();
        });

        /*
        | SÜRÜM — yalnızca INSERT.
        |
        | Yayınlamak metni DEĞİŞTİRMEZ, yeni satır DOĞURUR. Eski satır
        | olduğu yerde kalır çünkü ona bağlı siparişler var.
        */
        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();

            $table->string('type', 40);

            // Tür içinde 1'den başlayıp artar. Panelde ve müşteriye
            // "Sözleşme v3" diye gösterilecek insan okur numara.
            $table->unsignedInteger('version_no');

            // Sürüm yayınlandıysa metni VARDIR — boş sürüm anlamsız.
            $table->text('content');

            // ⚠️ `timestampsTz()` KULLANILMIYOR. O çift `updated_at` getirir;
            // hiç güncellenmeyen bir tabloda "güncellenme zamanı" kolonu
            // bulundurmak, satırın güncellenebileceğini ima eder.
            $table->timestampTz('published_at');

            // ⚠️ Yayınlayan personele FK VERİLMEDİ.
            //
            // Verilseydi: personel işten ayrılıp `users` satırı silindiğinde
            // ON DELETE SET NULL bu satırı UPDATE etmeye çalışır, aşağıdaki
            // değişmezlik tetiği bunu reddeder ve personel çıkarma işlemi
            // çöker. FK yerine o anki değerler KOPYALANIYOR — satırın hiçbir
            // zaman güncellenmesi gerekmiyor.
            $table->uuid('published_by_uuid')->nullable();
            $table->string('published_by_name', 120)->nullable();

            // Aynı tür için aynı numara iki kez üretilemez. Eşzamanlı iki
            // yayınlama isteğinde ikincisi burada patlar — sessizce aynı
            // numarayla iki farklı metin oluşmasındansa hata iyidir.
            $table->unique(['type', 'version_no']);
        });

        /*
        | DEĞİŞMEZLİK — veritabanı seviyesinde.
        |
        | Kodda "burayı güncellemiyoruz" demek yeterli değil: bir gün biri
        | iyi niyetle `->update(['content' => ...])` yazar, hata almaz ve
        | geçmiş siparişlerin dayanağı sessizce değişir. Tetik bunu
        | imkânsız kılıyor — son savunma hattı.
        |
        | Tetik marka şemasında oluşuyor (migration `search_path` marka
        | üzerindeyken koşuyor), yani her markanın kendi tetiği var.
        */
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION legal_document_versions_degismez() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION
                    'legal_document_versions salt-ekleme bir tablodur; '
                    'UPDATE/DELETE yapilamaz. Yeni surum icin INSERT kullanin.';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER legal_document_versions_degismez
                BEFORE UPDATE OR DELETE ON legal_document_versions
                FOR EACH ROW EXECUTE FUNCTION legal_document_versions_degismez();

            -- ⚠️ TRUNCATE ayrı tetik ister. PostgreSQL'de TRUNCATE satırları
            -- tek tek silmez, dosyayı boşaltır; FOR EACH ROW hiç çalışmaz.
            -- Ölçüldü: yukarıdaki tetik dururken TRUNCATE tabloyu boşalttı.
            CREATE TRIGGER legal_document_versions_degismez_truncate
                BEFORE TRUNCATE ON legal_document_versions
                FOR EACH STATEMENT EXECUTE FUNCTION legal_document_versions_degismez();
        SQL);
    }

    public function down(): void
    {
        // Tablo düşerken tetik de düşüyor; fonksiyon ayrı duruyor.
        Schema::dropIfExists('legal_document_versions');
        DB::unprepared('DROP FUNCTION IF EXISTS legal_document_versions_degismez()');
        Schema::dropIfExists('legal_document_drafts');
    }
};
