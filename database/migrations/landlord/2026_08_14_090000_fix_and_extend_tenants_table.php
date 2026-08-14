<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merkez tabloların düzeltilmesi ve abonelik alanları. (3B)
 *
 * ★ ÜÇ İŞ BİR ARADA:
 *   1  `timestamps()` → `timestampsTz()`   (CLAUDE.md'nin 2. kuralı)
 *   2  `json` → `jsonb`                    (indekslenebilir olsun)
 *   3  abonelik/yaşam döngüsü kolonları
 *
 * ⚠️ MERKEZ ŞEMA — `--path=database/migrations/landlord`. Marka tablosu
 * değil: burada bütün markaların ortak lobisi var (M-2.7).
 *
 * ⚠️ 1 ve 2, paketin kendi migration'ından geliyordu. Marka şemalarında
 * `timestampsTz()` disiplinini uyguladık ama merkez tabloyu hiç açmamıştık —
 * yani kendi kuralımız kendi evimizde ihlal ediliyordu.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | 1 — SAAT DİLİMİ.
        |
        | ⚠️ `AT TIME ZONE 'UTC'` ZORUNLU. Ofissiz damgalar PostgreSQL
        | tarafından OTURUMUN saat dilimine göre yorumlanıyor; belirtmezsek
        | dönüşüm sunucunun o anki ayarına göre kayar ve marka açılış
        | tarihleri sessizce saatlerce oynardı (CLAUDE.md'nin 3. kuralı).
        |
        | Değerler UTC olarak yazılmıştı: `config/database.php`'de
        | `'timezone' => 'UTC'` ve sunucu varsayılanı da UTC (1A.1'de ölçüldü).
        */
        foreach (['tenants', 'domains'] as $tablo) {
            foreach (['created_at', 'updated_at'] as $kolon) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE timestamptz USING %s AT TIME ZONE \'UTC\'',
                    $tablo,
                    $kolon,
                    $kolon,
                ));
            }
        }

        /*
        | 2 — jsonb.
        |
        | ⚠️ `json` metin olarak saklanıyor ve İNDEKSLENEMİYOR. Kontrol
        | düzlemi (3C) markayı adına göre arayacak; `data->>'name'` üzerinden
        | arama her satırı tarardı.
        |
        | ⚠️ Dönüşüm veri kaybetmiyor — `::jsonb` mevcut metni ayrıştırıyor.
        */
        DB::statement('ALTER TABLE tenants ALTER COLUMN data TYPE jsonb USING data::jsonb');

        /*
        | 3 — ABONELİK ve YAŞAM DÖNGÜSÜ.
        |
        | ⚠️ Hepsi GERÇEK KOLON, `data` json'ının içinde değil. İçinde
        | kalsalardı "ödemesi gecikmiş markalar" ya da "denemesi bugün biten
        | markalar" sorgusu yazılamazdı — oysa zamanlanmış görevlerin tamamı
        | tam olarak bunu soracak.
        */
        Schema::create('plans', function (Blueprint $tablo): void {
            $tablo->id();

            // ⚠️ Kod DEĞİŞMEZ kimlik: fiyat/ad değişse de abonelikler buna bakar.
            $tablo->string('code')->unique();
            $tablo->string('name');

            $tablo->decimal('price', 12, 2);
            $tablo->string('currency', 3)->default('TRY');
            $tablo->string('interval')->default('monthly');

            /*
            | ★ SINIRLAR — 3 numaralı karar: ÜRÜN ve PERSONEL sayısı.
            |
            | ⚠️ Aylık SİPARİŞ sınırı BİLEREK YOK. İkas ve Shopify'da da yok;
            | sipariş kısıtlamak markanın satışını durdurmak, yani en iyi
            | gününde sistemi ona kapatmak demek.
            |
            | ⚠️ `null` = SINIRSIZ. `0` kullanılsaydı "sıfır ürün" ile
            | "sınırsız" aynı değerle anlatılırdı ve bir gün biri
            | `>= $limit` yazıp bütün kataloğu kilitlerdi.
            */
            $tablo->integer('max_products')->nullable();
            $tablo->integer('max_staff')->nullable();

            // Özellik bayrakları — plana göre açılıp kapanan yetenekler.
            $tablo->jsonb('features')->default('{}');

            $tablo->boolean('is_active')->default(true);
            $tablo->integer('position')->default(0);

            $tablo->timestampsTz();
        });

        Schema::table('tenants', function (Blueprint $tablo): void {
            /*
            | ⚠️ `name` GERÇEK KOLONA taşınıyor. Bugün `data` json'ının
            | içinde (ölçüldü) — yani "adı X olan markayı bul" sorgusu
            | tam tarama demek. Kontrol düzleminin ilk ihtiyacı bu.
            |
            | Nullable açılıyor çünkü mevcut satırlar aşağıda dolduruluyor;
            | boş bırakılsaydı NOT NULL kısıtı migration'ı düşürürdü.
            */
            $tablo->string('name')->nullable();

            /*
            | ⚠️ VARSAYILAN YOK — bilinçli.
            |
            | `default('active')` yazılsaydı durum vermeyi unutan her yol
            | sessizce "ödeyen müşteri" üretirdi. `null` ise gürültülü:
            | denetimde hemen görünür.
            |
            | ⚠️ Kolon varsayılanı zaten modele ULAŞMIYOR (CLAUDE.md, beş kez
            | ısırdı) — güvenilecek yer model tarafındaki `$attributes`.
            */
            $tablo->string('status')->nullable();

            $tablo->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();

            /*
            | ★ DENEME BİZDE (3 numaralı karar). iyzico aboneliği başlatmak
            | kart istiyor; kartsız deneme istediğimiz için deneme süresi
            | burada tutuluyor ve abonelik ancak deneme bitince başlıyor.
            */
            $tablo->timestampTz('trial_ends_at')->nullable();

            /*
            | ★ ÖDEME BAŞARISIZ (4 numaralı karar):
            |   0-7 gün    her şey açık          → grace_ends_at ileride
            |   7-14 gün   panel salt-okunur
            |   14+ gün    askı                  → suspended_at dolar
            */
            $tablo->timestampTz('grace_ends_at')->nullable();
            $tablo->timestampTz('suspended_at')->nullable();

            /*
            | ★ KAPATMA (7 numaralı karar): 1 yıl dokunulmadan saklanıyor,
            | sonra şema siliniyor. Silme tarihi buradan hesaplanıyor.
            */
            $tablo->timestampTz('closed_at')->nullable();

            // iyzico abonelik referans kodu — sorgulama ve iptal için.
            $tablo->string('subscription_ref')->nullable();

            /*
            | ⚠️ Zamanlanmış görevlerin tarayacağı kolonlar indeksli:
            | "denemesi bugün biten", "nezaket süresi dolmuş", "silinecek
            | markalar" sorgularının hepsi bunlara bakıyor.
            */
            $tablo->index('status');
            $tablo->index('trial_ends_at');
            $tablo->index('closed_at');
        });

        /*
        | ★ GERİYE DÖNÜK DOLDURMA — ZORUNLU.
        |
        | ⚠️ Kolon sonradan eklendiğinde mevcut satırlar BOŞ kalıyor ve bu
        | hata VERMİYOR. 2C'de arama hiçbir eski ürünü bulmuyordu, 2F'de
        | üst sınır konmasa geçmişe mail giderdi — bu üçüncü örnek.
        |
        | `name` json'dan alınıyor; `status` ise mevcut markalar için
        | `active`, çünkü bugün ödeme/deneme kavramı yokken açıldılar ve
        | hepsi çalışır durumda.
        */
        /*
        | ⚠️ `data ? 'name'` YAZILAMAZ. PostgreSQL'de `?` jsonb'nin "bu
        | anahtar var mı" operatörü, ama PDO onu PARAMETRE YER TUTUCUSU
        | sanıyor ve sorgu `syntax error at or near "$1"` ile düşüyor.
        | Ölçüldü. Fonksiyon biçimi (`jsonb_exists`) aynı işi yapıyor.
        */
        DB::statement("UPDATE tenants SET name = data->>'name' WHERE name IS NULL AND jsonb_exists(data, 'name')");
        DB::statement("UPDATE tenants SET status = 'active' WHERE status IS NULL");

        /*
        | ★ VE `data`'DAN SİLİNİYOR — KOPYALAMAK YETMİYOR.
        |
        | ⚠️ ÖLÇÜLDÜ, ve sonucu şaşırtıcı: iki yerde birden duran bir alanda
        | MODEL `data`'YI OKUYOR, kolonu değil.
        |
        | ```
        | kolon: 'KOLON DEGERI'      ← SQL sorgusunun gördüğü
        | data : {"name":"A Markası"}
        | $tenant->name → 'A Markası' ← modelin gördüğü
        | ```
        |
        | Silinmeseydi iki kaynak SESSİZCE AYRIŞIRDI: panel adı değiştirir
        | (kolona yazılır), model eski adı okumaya devam ederdi. Marka
        | "adımı değiştirdim ama değişmedi" derdi ve hiçbir yerde hata
        | görünmezdi.
        */
        DB::statement("UPDATE tenants SET data = data - 'name' WHERE jsonb_exists(data, 'name')");
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $tablo): void {
            $tablo->dropIndex(['status']);
            $tablo->dropIndex(['trial_ends_at']);
            $tablo->dropIndex(['closed_at']);

            $tablo->dropConstrainedForeignId('plan_id');
            $tablo->dropColumn([
                'name', 'status', 'trial_ends_at', 'grace_ends_at',
                'suspended_at', 'closed_at', 'subscription_ref',
            ]);
        });

        Schema::dropIfExists('plans');

        DB::statement('ALTER TABLE tenants ALTER COLUMN data TYPE json USING data::json');

        foreach (['tenants', 'domains'] as $tablo) {
            foreach (['created_at', 'updated_at'] as $kolon) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE timestamp USING %s AT TIME ZONE \'UTC\'',
                    $tablo,
                    $kolon,
                    $kolon,
                ));
            }
        }
    }
};
