# TıkMarka — Özet

> Tek bakışta proje. Ayrıntı: `PLAN.md` · `pre-setup.md` · `domain-model.md`

## Ne

Tek markanın kendi müşterisine sattığı e-ticaret (D2C). Çok kiracılı SaaS —
**aynı kod N markaya hizmet eder**, her marka kendi alan adında.

## Servisler

```
tarayıcı
   │ https
   ▼
 caddy ────────▶ app (php-fpm 8.4) ──┬──▶ postgres 17   tek db, N şema
 TLS · public/   Laravel 12          ├──▶ redis         cache + kuyruk
                                     └──▶ mailpit       yerel mail
                 worker ─────────────┘
                 queue:work · aynı imaj
```

## Akış

```
istek → public/index.php → middleware: KİRACI ÇÖZ (host → şema)
      → route → controller (ince) → app/Domain (iş mantığı) → model → db
      → cevap
```

## Kod katmanları

```
app/Platform   merkez db: kiracı, alan adı, abonelik
app/Tenancy    kiracılığın TAMAMI, tek yerde
app/Domain     iş mantığı — kiracıdan HABERSİZ
app/Http       Storefront · Panel · Platform
```

## Kararlar

```
M-1  abonelik SaaS · biz barındırıyoruz · kaynak kod teslimi yok
M-2  marka başına ayrı PostgreSQL şeması · tenant_id kolonu YOK
M-3  arayüz Faz 4'e ertelendi · iş mantığı servis katmanında kalacak
M-4  ters vekil Caddy · sebep: on-demand TLS (özel alan adı)
```

## Kurallar

```
para        numeric(12,2) + bcmath · float YASAK
sipariş     fotoğraftır — fiyat/KDV/başlık kopyalanır
ürün liste  tek kapı: ProductQuery (forStorefront / forPanel)
kiracılık   5 tuzak: kuyruk · cache · dosya · zamanlanmış iş · search_path
test        arayüz yok → testler gözümüz
```

## Fazlar

```
0 temel + kiracılık   1 çekirdek mağaza   2 olgunlaşma
3 satılabilirlik      4 arayüz            5 entegrasyon   6 dağıtım
```

---

## Yapılanlar

```
0.1  ✅  git · Laravel 12 · PHP 8.4 sabitlendi · .gitignore · .env.example
0.2  ✅  docker: caddy app worker postgres(citext) redis mailpit
         caddy→app→PHP 200 ✓ · worker kuyruğu tüketiyor ✓ · storage ortak ✓
         host portu 5433 (5432 doluydu)
0.3  ✅  Pint (biçim) + Larastan (analiz) · komutlar: lint · analyse · test
         Larastan SEVİYE 8 — plan 5 diyordu, kod boşken 8 bedava
         seviye 8 = null erişimini yakalar ($user->name, $user null olabilir)
         sail kaldırıldı · anlamsız ExampleTest silindi
         üçü de yeşil: lint 25 dosya · analyse 0 hata · test 1 geçti
0.4  ✅  Pest kuruldu · test db AYRI: tikmarka_test
         testler PostgreSQL'de — SQLite'ta şema/citext/jsonb/FOR UPDATE yok
         RefreshDatabase: her test transaction + rollback → izole
         TUZAK: docker env_file → $_SERVER → phpunit ezemiyor
                → app/worker'dan env_file kaldırıldı, Laravel .env'i dosyadan okuyor
         5 test yeşil
0.4b ✅  ALIŞTIRMA — Note: migration + model + test, sonra silindi
         migration = yapı (DDL) · model/Eloquent = veri (DML, ORM budur)
         konvansiyon: Note sınıfı → notes tablosu, kayıt gerekmiyor
         migrate --pretend → SQL'i çalıştırmadan gösterir
         test bilerek kırmızıya düşürüldü — 4 testten 1'i kırıldı
         BULGU: timestamps() → timestamptz DEĞİL, 1A.1'e uyarı yazıldı
0.5  ✅  kiracılık zemini — stancl/tenancy 3.10, ŞEMA bazlı
         landlord/ + tenant/ migration ayrımı · kök bilerek boş
         kapı görevlisi: host → domains → search_path (routes/tenant.php)
         BEŞ TUZAK ölçülerek doğrulandı; belgedeki 3 tarif yanlıştı, düzeltildi
           search_path: bağlantı purge · cache: TAG (Redis şart)
           dosya: storage/tenant<id>/ · kuyruk: tenant_id iş gövdesinde
           zamanlanmış: tenants:run + scheduler servisi (ikisi de yoktu)
         GERÇEK SIZINTI: bayat worker → işler merkez klasöre yazdı, hata yok
         tenant:create komutu · Caddy domain-check ucu
         tests/Tenancy/ ayrı paket (RefreshDatabase transaction'ı şemayı bozuyor)
         20 test yeşil · kırmızı görüldü (bootstrapper kapatınca 1 test kırıldı)
0.6  ✅  CI — GitHub Actions (.github/workflows/ci.yml)
         her push + PR: lint:check · analyse · test
         postgres 17 + redis servisleri · citext elle kuruluyor
         phpunit.xml'de DB_HOST force KALDIRILDI (yerel: postgres, CI: 127.0.0.1)
         if: always() → üç kontrol de çalışır, biçim hatası testleri gizlemesin
         KIRMIZI GÖRÜLDÜ: ✅→❌→❌→✅
           Pint ✗ biçim · Larastan ✓ tip doğru · Pest ✗ mantık yanlış
           → statik analiz iş kuralı hatasını göremez, yalnızca test yakalar
0.7  ✅  README (0.4b'den önce yazılmıştı) + CI rozeti

════ FAZ 0 BİTTİ ════  iş mantığı hâlâ SIFIR

1A.1 ✅  marka şeması tabloları + modeller + factory'ler + enum
         customers  email NULL olabilir → misafir sipariş
         users      personel · is_owner emniyet kilidi
         roles + role_user + role_permissions  ilk FK'ler, pivot
         settings   anahtar-değer + jsonb · is_encrypted (ödeme anahtarları)
         addresses  DEFTER — sipariş adresi değil, siparişe KOPYALANIR
         BULGU: citext marka şemasında çalışmıyor (eklenti public'te,
                search_path görmüyor, sessizce düz metne düşüyor)
                → sınırda küçültme + CHECK (email = lower(email))
         domains.domain'e de aynı CHECK eklendi (tutarlılık)
         tenant:create yarıda kalırsa artık arkasını topluyor
         DESEN: $fillable = "neyi ASLA dışarıdan almam" listesi
                Address.customer_id · User.is_owner · Role.is_system
1A.2 ✅  kimlik doğrulama — 16 test
         Sanctum · personal_access_tokens MARKA şemasında
         iki guard: customer (Customer) · staff (User)
         KANIT: müşteri token'ı staff guard'ından REDDEDİLİYOR
                (Guard.php:145 → $tokenable instanceof $model)
         uçlar: /api/{register,login,logout,me} · /panel/{login,logout,me}
                panelde KAYIT UCU YOK — personel davetle gelir
         'api' middleware grubu, 'web' değil (CSRF token istemcisini kırardı)
         yanlış parola = olmayan hesap → AYNI mesaj (hesap sayımı engeli)
         hız sınırı: giris 5/dk (e-posta+IP) · kayit 10/saat (IP)
         BULGU 1: accepts_marketing API'de null dönüyordu → refresh()
         BULGU 2: doğrulama mesajları "validation.required" görünüyordu
                  (APP_LOCALE=tr, fallback de tr, Türkçe dosya yok)
                  → lang/tr/validation.php
         TEST YAPAYLIĞI: testte guard önbelleği istekler arası sızıyor,
                  gerçek HTTP'de sorun yok (curl ile doğrulandı)
                  → guardOnbelleginiTemizle()
         EK TEST: A'nın müşterisi B'de giriş yapamıyor · A'nın token'ı B'de geçersiz

1A.3 ✅  izin sistemi ve personel yönetimi — 13 test
         Permission enum: 9 izin, kodda SABİT liste
         User::hasPermission() tek kapı · izinler rollerden, istek başına önbellek
         ⚠ SAHİP her izne otomatik sahip — olmasaydı kendi rolünden
           staff.manage'i kaldırınca markasına kilitlenirdi
         3 sistem rolü: Yönetici · Katalog · Sipariş & Destek
           "Sahip" ROL DEĞİL → users.is_owner bayrağı
           Sipariş & Destek'te İADE izni yok (depocu örneği)
           ⚠ staff.manage HİÇBİR rolde yok → pratikte yalnızca sahipte
             (personel davet = yetki yükseltmeye en yakın işlem)
         izin: middleware — Laravel'in can:/Gate'i KULLANILMADI
               (Gate varsayılan guard'a bakıyor, bizde varsayılan customer)
         /panel/staff (GET·POST·DELETE) · URL'de uuid · roller İSİMLE
         3 EMNİYET KİLİDİ: is_owner $fillable dışında · sahip çıkarılamaz ·
                           kimse kendini çıkaramaz
         çıkarılan personelin token'ları da iptal ediliyor
         tenant:create artık rol + sahip kullanıcı da kuruyor
         KIRMIZI: sahip muafiyeti kaldırılınca 6 test kırıldı

════ TOPLAM: 49 test · lint · analyse (seviye 8) yeşil ════

SIRADAKİ: 1A.4 mağaza ayarları servisi + /panel/settings
          (tenant:create'in son TODO'su: varsayılan KDV/kargo/yasal metinler)
```
