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

1A.4 ✅  mağaza ayarları · yasal metinler · yayın durumu — 24 test
         SettingsService: grup bazlı okuma/yazma + grup bazlı önbellek
         ⚠ şifreli ayar YAZILIR ama OKUNMAZ → panele {"is_set":true}
           (anahtarı okumaya gerek yok; düz metin dönseydi tarayıcı geçmişi,
            log, ekran görüntüsü hepsi sızdıran kanal olurdu)
         önbellek marka bilgisi TAŞIMIYOR — 0.5'in etiketli cache'i bedava

         YASAL METİNLER settings'ten ÇIKTI → sürümlü kendi tablosuna
           gerekçe: ayar "şu an geçerli değer"dir, geçmişi yok
                    yasal metnin geçmişi olmak ZORUNDA
                    15 Mart siparişi 20 Mart'ta değişen metne bağlanamaz
           legal_document_drafts    değişken, yarım kalabilir, dışarı çıkmaz
           legal_document_versions  yalnızca INSERT · yayınla = YENİ SATIR
           DEĞİŞMEZLİK VERİTABANINDA: UPDATE/DELETE/TRUNCATE tetikle yasak
           BULGU: satır tetiği TRUNCATE'i GÖRMÜYOR → ayrı tetik eklendi
           published_by FK YOK — olsaydı personel çıkarınca ON DELETE
             SET NULL satırı UPDATE etmeye çalışır, tetik çökertirdi

         YAYIN DURUMU (planda yoktu, eklendi): marka KAPALI doğuyor
           KAPALI --yayinla(denetim)--> YAYINDA --kapat()--> KAPALI
           model: "önce kapat, sonra düzenle"
             alternatifi (yayındayken tek tek engelle) alanı BOŞALTMAYI
             yasaklar ama YANLIŞ YAZMAYI yasaklayamaz
           kilit sınırı: "bu değer sözleşmenin içine giriyor mu?"
             kilitli  unvan · vergi no/dairesi · adres · telefon · eposta
             serbest  KDV (kanunla değişir) · kargo (kampanya) · vitrin adı
           taslağa YAZMAK serbest (görünmüyor) · YAYINLAMAK 409
           409 seçildi: 403 değil (yetki var), 422 değil (veri geçerli) — ZAMAN
           vitrin kapısı 503 + Retry-After (çıplak 503'ü arama motoru
             kalıcı bozukluk sayabilir) · panele TAKILMIYOR

         ★ YER TUTUCULAR YAYIN ANINDA DOLDURULUYOR
           iskelet metinler {{unvan}} {{vergi_no}} … ile doğuyor
           yayınlarken mağaza bilgilerinden dolduruluyor
           biri eksik kalırsa 422, SÜRÜM OLUŞMUYOR
           → müşteri hiçbir koşulda süslü parantez göremez
           yan fayda: metin o günkü bilgilerle DONUYOR (sipariş fotoğrafı)

         tenant:create son TODO kapandı — varsayılan KDV/kargo/misafir
         contact_email BİLEREK BOŞ (sahibin kişisel adresi sözleşmeye basılmasın)
         settings.write 1A.3'ten beri boş etiketti, ilk kez kapı bekliyor
         KIRMIZI: yer tutucu + kilit denetimi bozuldu → 4 test kırıldı

1A.5 ✅  adres defteri — /api/addresses (GET·POST·PUT·DELETE) — 10 test
         ★ DESEN: sahiplik kontrolü ayrı bir "if" DEĞİL, SORGUNUN KENDİSİ
             $musteri->addresses()->where('uuid',$u)->firstOrFail()
           yükle-sonra-kontrol olsaydı satır belleğe gelirdi ve kontrolü
           yazmayı unutan uç başkasının adresini döndürürdü, hatasız
           search_path ilkesinin aynısı: ayıklamak değil ERİŞİLEMEZ kılmak
           → 1B ürün · 1C sepet · 1D sipariş · Faz 2 iade hep bunu kullanacak

         404 seçildi (plan 403 diyordu) — 403 "var ama senin değil" demek,
           varlık bilgisi sızdırır; daraltılmış sorgunun doğal sonucu da 404

         uuid EKLENDİ (planda yoktu, UUIDv7): ardışık id ile müşteri komşu
           numaraları tarayıp mağazadaki adres SAYISINI çıkarabiliyordu
           id içeride kaldı, uuid dışarı açılan kimlik (customers/users deseni)
           migration 3 adım: nullable → PHP'de backfill → not null+unique
           (backfill PHP'de: gen_random_uuid() v4 üretir, karışık kolon olmasın)

         HATA DÜZELTİLDİ: önce örtük rota bağlaması (Address $adres) yazmıştım
           o uuid'yi TÜM tabloda arıyor → başkasının satırı belleğe geliyor
           "hiç yükleme" ilkesinin tersi; rota artık düz uuid alıyor

         customer_id $fillable dışında + ilişki üzerinden create → kütle atama yok
         yumuşak silme: sipariş adresi zaten KOPYALIYOR, defter geri gelebilir
         KIRMIZI: sahiplik daraltması kaldırıldı → 2 test kırıldı

1A.6 ✅  blok kapanışı — rol yönetimi · tohumlayıcı · doğrulama — 15 test
         ROL YÖNETİMİ /panel/roles — kapı `sahip` middleware'i, İZİN DEĞİL
           role.manage izni olsaydı sahibi kendine settings.write'lı rol
           kurup atardı → "yetki dağıtan işlem yetkiyle dağıtılmaz"
           marka kendi rolünü kurabiliyor: katı liste güvenlik değil
             AŞIRI YETKİ üretir ("sadece finans" yoksa Yönetici verilir)
           sınırlar: izinler enum'dan · is_system yazılamaz ·
             sistem rolü silinemez ama DÜZENLENEBİLİR ·
             üzerinde personel olan rol silinemez (409 + sayı)
           BULGU: yeni rolde is_system null dönüyordu — değer DB
             varsayılanından geliyor, bellekteki nesnede yok → refresh()
             (1A.2'deki accepts_marketing tuzağının aynısı)

         TOHUMLAYICI — merkez/marka AYRILDI
           Laravel'in DatabaseSeeder'ı User::factory() çağırıp MERKEZDE
             koşuyordu; users merkezde YOK. tenants:seed de aynı sınıfı
             çağırıyordu → "hangi şemadayım" belirsiz
           DatabaseSeeder (merkez, veri yok) · TenantDemoSeeder (marka)
           rol+sahip tohumda YOK — onlar tenant:create'in işi
           3 savunma: canlı reddi · bağlam yoksa hata · rol yoksa hata

         İKİ KİRACIDA DOĞRULAMA (gerçek HTTP, 6 başlık) — hepsi geçti
           A token'ı B'de 401 · aynı e-posta iki markada ayrı kişi ·
           A'nın adres uuid'si B'de 404 · katalogcu 403 sahip 200 ·
           kargo A 11.11 B 99.99 · A yayında B kapalı

         ★★ CI 20 KOŞUDUR KIRMIZIYMIŞ — 1A.2/1'den beri, fark edilmeden
            sebep: Customer.php class_attributes_separation (1 boş satır)
            DERS 1: yerel kapı yalan söyledi — lint:check yerelde PASS,
              CI'da FAIL, AYNI içerikte. Dosya tek başına denetlenince
              yerelde de FAIL, tüm projede PASS. Sebep kesinleşmedi
              (paralellik değil); Pint önbelleği tahmini, kanıt değil
            DERS 2: kural vardı, kimse bakmadı — rozet + plan kuralı
              dururken 19 commit kırmızı üstüne atıldı
              KURAL, BAKILMADIĞI SÜRECE KURAL DEĞİLDİR
            günlükler yönetici yetkisi istiyor → .github/ci-kontrol.sh
              hatayı ANOTASYONA basıyor (anotasyonlar herkese açık)

         BULGU: eski markalar varsayılanları ALMIYOR — tenant:create yeni
           markaya kuruyor ama önceden açılmışlara kimse gitmiyor
           → Faz 3'e geri-doldurma komutu maddesi

════ FAZ 1A BİTTİ · 98 test · lint · analyse · CI hepsi yeşil ════

1A'NIN BIRAKTIĞI DESENLER (sonraki bloklar kullanacak)
  $fillable = "asla dışarıdan almam" listesi          1A.1
  daraltılmış sorgu = sahiplik kontrolü               1A.5 → 1B·1C·1D
  sürümlü + değişmez kayıt (tetikle zorlanan)         1A.4 → 1E
  kayıt bir fotoğraftır (kopyala, bağlama)            1A.1·1A.4 → 1D
  yetki dağıtan işlem yetkiyle dağıtılmaz             1A.3·1A.6
  emniyeti bozup kırmızı görmeden yeşile güvenme      0.4b'den beri

SIRADAKİ: 1B katalog — kategori · ürün · varyant · görsel
```
