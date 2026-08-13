# TıkMarka — Geliştirme Planı

> **Bu dosya projenin tek yol haritasıdır.** Tüm geliştirme buna göre ilerler.
> Kararların gerekçeleri `docs/pre-setup.md`'de, veri modeli `docs/domain-model.md`'de.
> Son güncelleme: **2026-08-11**

```
┌─ YOL HARİTASI ─────────────────────────────── şu an: 1C  ───┐
│                                                                │
│  0 · TEMEL         git → docker → test → KİRACILIK → ci        │
│                    ╰ çıktı: iki kiracı, verileri karışmıyor    │
│                                                                │
│  1 · ÇEKİRDEK      1A kimlik+yetki+ayarlar                     │
│                    1B katalog → 1C sepet                       │
│                    1D stok+sipariş+sevkiyat  ← en zor          │
│                    1E ödeme → 1F olay kaydı                    │
│                    ╰ çıktı: misafir müşteri sipariş verebiliyor│
│                                                                │
│  2 · OLGUNLAŞMA    bildirim · kvkk · iade · kupon · arama      │
│  3 · SATILABİLİRLİK kontrol düzlemi · abonelik · gerçek TLS    │
│  4 · ARAYÜZ        ← teknoloji burada seçilir (M-3)            │
│  5 · ENTEGRASYON   kargo · e-fatura                            │
│  6 · DAĞITIM       yayın · yedekleme · izleme                  │
│                                                                │
│  Kural: bir blok bitmeden sonrakine geçilmez.                  │
└────────────────────────────────────────────────────────────────┘
```

---

## Bu plan nasıl kullanılır

**Kurallar:**

1. **Sırayla gidilir.** Bir blok bitmeden sonrakine geçilmez. Sıra keyfî değil — her blok
   bir öncekinin ürettiği şeye dayanıyor.
2. **Her madde küçük ve doğrulanabilir.** "Katalog yap" değil, "ürün oluşturma ucu çalışıyor
   ve testi geçiyor". Bir maddeyi bitirdiğinde işaretleyebiliyorsan doğru boyuttadır.
3. **Blok sonunda test yeşil olmadan blok bitmez.** Arayüz olmadığı için (M-3) testler
   bizim gözümüz. Test yoksa "çalışıyor" diyemeyiz.
4. **Plan canlıdır.** Yeni bir karar alındığında önce `docs/pre-setup.md`'ye yazılır, sonra
   bu dosyaya yansıtılır. Plan gerçekle çelişiyorsa plan güncellenir, gerçek zorlanmaz.
5. **Kapsam genişletme yasağı.** Yeni özellik fikri geldiğinde "Sonraya" listesine yazılır,
   plana girmez.
6. **Her blok iki kiracıda doğrulanır.** Tek kiracıda çalışan kod, kiracılık hatasını
   göstermez (M-2.4). Blok kapanış testi ikinci bir kiracıyla çalıştırılır.

**İşaretleme:** `- [ ]` yapılmadı · `- [x]` bitti · `- [~]` başlandı

---

## Faz haritası — tek bakışta

| Faz | Ne | Sonunda elimizde ne olur |
|-----|-----|--------------------------|
| **0** | Temel kurulum + kiracılık zemini | Boş ama çalışan, test edilebilir, **çok kiracılı** Laravel projesi |
| **1** | Çekirdek mağaza | Bir müşteri gerçekten sipariş verebiliyor (API üzerinden) |
| **2** | Olgunlaştırma | Bildirim, KVKK, iade, kupon, arama, koleksiyon, yorum |
| **3** | Satılabilirlik | Kontrol düzlemi, abonelik, marka açma akışının tamamı |
| **4** | Arayüz | Vitrin + yönetim paneli (M-3) |
| **5** | Entegrasyonlar | Kargo, e-fatura |
| **6** | Dağıtım | Yayında çalışan sistem |

**Faz 1 alt blokları** (asıl iş burada):

```
1A Kimlik, yetki, mağaza ayarları  →  kim kimdir, kim ne yapabilir
1B Katalog                         →  satılacak şeyler var
1C Sepet                           →  seçilebiliyor (misafir dahil)
1D Stok + Sipariş + Sevkiyat       →  satın alınabiliyor   ← en zor blok
1E Ödeme                           →  parası ödenebiliyor
1F Olay kaydı                      →  ne olduğu kaydediliyor
```

Her blok bir öncekinin üstüne biniyor: ürün yoksa sepet olmaz, sepet yoksa sipariş olmaz.

> 📌 **Neden arayüz Faz 4'te:** M-3 kararı. Backend çalışır hâle gelmeden arayüz
> teknolojisi seçilmeyecek. Şartı da orada: iş mantığı servis katmanında durur, controller
> ve şablonda değil.

> ✅ **M-1 zorunlulukları plana dağıtıldı** (`docs/pre-setup.md` §3/0 kapandı):
>
> | Zorunluluk | Nerede |
> |---|---|
> | Yasal metinler (KVKK aydınlatma, iade politikası, mesafeli satış) | ✅ 1A.4 — `legal_document_versions` (sürümlü, değişmez). `settings` DEĞİL: sipariş kendi günündeki metne bağlı kalmalı |
> | **Mesafeli satış sözleşmesi onayı** | **1D · 1E** — `orders.legal_version_id` + onay kanıtı. ⚠️ GÖSTERİLEN sürüme bağlanır, o anki güncele değil (1A.4) |
> | Cayma hakkı (14 gün iade) | Faz 2 — iade akışı |
> | **KVKK veri silme / anonimleştirme** | **Faz 2** |
> | Müşterinin kendi verisini indirmesi | Faz 2 |
> | Marka açma akışı + özel alan adı | 0.5 · 1A.6 · Faz 3 |
> | Abonelik ve plan verisi | Faz 3 |
> | e-Fatura entegrasyonu | Faz 5 |
> | Yedekleme | Faz 6 |

---

## Faz 0 — Temel kurulum + kiracılık zemini

**Amacı:** tek satır iş mantığı yazmadan önce iki şeyi güvence altına almak — "çalışıyor mu?"
sorusunu güvenilir biçimde cevaplayabilmek, ve **kiracılığın altyapısını iş mantığından önce
kurmak.**

> **Neden kiracılık Faz 0'da:** M-2.4'teki beş tuzak (kuyruk, cache, dosya, zamanlanmış iş,
> `search_path`) iş mantığı yazılmadan **önce** çözülmek zorunda. İlk kuyruk işi veya ilk
> önbelleklenmiş sorgu yazıldıktan sonra bunları geriye dönük eklemek, o kodun tamamını
> tekrar gözden geçirmek demektir. TıkRota'nın Faz 0'ından tek farkımız bu blok.

**Bitiş ölçütü:** `docker compose up` diyince proje ayağa kalkıyor; iki ayrı kiracı
oluşturulabiliyor; birinde yaratılan veri diğerinden görünmüyor ve bu **testle
kanıtlanıyor**; CI yeşil dönüyor.

### 0.1 Depo ve proje iskeleti

- [x] Git deposu başlat, `main` dalı oluştur
- [x] `.gitignore` — `vendor/`, `node_modules/`, `.env`, `storage/` içerikleri
- [x] Laravel projesi kur — **Laravel 12 / PHP 8.4**
- [x] `composer.json`'da PHP sürümünü sabitle (`"php": "^8.4"`)
  > **Neden:** konteyner ile yerel kurulumun farklı PHP sürümü çalıştırması, ancak
  > üretimde patlayan hatalar üretir. Sürüm tek yerde beyan edilir.
  >
  > 📌 **Uygulamada karşılığını gördük:** kurulum PHP 8.5 imajıyla yapıldı ve bazı Symfony
  > paketleri 8.5'e özel sürümlerini çekti. `config.platform.php = 8.4.0` konup kilit
  > yeniden üretilince bunlar 8.4 uyumlu sürümlere düştü. Sabitlenmeseydi 8.4 konteynerinde
  > çalışmayan paketlerle başlamış olurduk.
- [x] `.env.example` hazırla ve depoya ekle
  > **Neden:** `.env` sırlar içerdiği için depoya girmez. `.env.example` "hangi değişkenler
  > gerekli" bilgisini taşır — projeyi kuran herkes ondan kopyalayarak başlar.
- [x] İlk commit

> ⚠️ **0.2'ye devreden not:** Laravel 12 varsayılan olarak `laravel/sail` ile geliyor.
> Biz kendi Compose dosyamızı ve Caddy'yi kullanacağımız için (M-4) Sail kaldırılacak —
> iki ayrı geliştirme ortamı yaklaşımı bir arada durmamalı.

### 0.2 Docker ortamı

- [x] `docker-compose.yml` — **altı servis**: `app` (PHP-FPM), `worker` (kuyruk), `caddy`,
      `postgres`, `redis`
- [x] `docker/php/Dockerfile` — PHP 8.4 imajı + uzantılar: `pdo_pgsql`, `redis`, `intl`,
      `bcmath`, `gd`, `zip` · composer imaja dahil
  > `bcmath` gerekçesi doğrulandı: float ile `0.1 + 0.2 = 0.30000000000000004`.
  > PostgreSQL `numeric(12,2)` veriyi kayıpsız **saklar**, `bcmath` hesabı kayıpsız
  > **yapar** — ikisi de gerekli (`docs/domain-model.md` §0).
  >
  > 📌 Geliştirme imajı 802 MB. Üretim imajının inceltilmesi (çok aşamalı build, composer
  > çıkarılır, opcache açılır) **Faz 6**'ya bırakıldı — geliştirmede sorun değil.
- [x] `worker` servisi — `app` ile **aynı imaj**, farklı komut (`queue:work`)
  > **Neden ayrı konteyner:** web isteği ile arka plan işi farklı hızlarda ölçeklenir ve
  > uzun süren bir iş, istek karşılayan süreçleri meşgul etmemeli. Ayrıca kuyruk çöktüğünde
  > sitenin ayakta kalması gerekir.
  >
  > ⚠️ **Tuzak 1 — bayat kod.** Kuyruk işçisi kodu **belleğe alır**; yeni sürüm deploy
  > edildiğinde işçi eski kodu çalıştırmaya devam eder. Deploy adımlarına `queue:restart`
  > eklenmezse "düzelttim ama hâlâ eski hata geliyor" durumu yaşanır ve sebebi çok geç
  > anlaşılır.
  >
  > ⚠️ **Tuzak 2 — paylaşılan depolama.** İşçi görsel işleme gibi dosyaya dokunan işleri
  > yapacak. `app` ve `worker` **aynı `storage/` hacmini** görmezse yüklenen görsel bir
  > konteynerde var diğerinde yok olur. Kiracı dosya kökü de buradan besleniyor (M-2.4).
- [x] Mailpit servisi ekle — yerelde giden e-postaları yakalamak için
- [x] **Caddy** yapılandırması (M-4): FPM'e **TCP** ile bağlan, adres ortam değişkeninden
      okunsun
  > **Neden TCP:** Unix soketi yerine TCP — ileride konteyner ayrımına gidilirse kodda
  > hiçbir şey değişmez.
- [x] **Caddy `/data` dizinine kalıcı volume ver** (M-4.1/2)
  > **Neden:** sertifikalar orada durur. Volume yoksa her yeniden başlatmada sertifikalar
  > sıfırdan istenir ve birkaç deploy sonra Let's Encrypt hız limitine takılırsın.
- [x] Yerel geliştirmede `tls internal` kullan
  > **Neden:** Let's Encrypt yerel alan adına sertifika veremez. Gerçek on-demand TLS akışı
  > ancak Faz 3'te, kontrol düzlemiyle birlikte test edilebilir.
- [x] PostgreSQL sürümünü sabitle (`postgres:17`), **`citext` eklentisini etkinleştir**
  > **Neden:** `citext`, e-posta gibi alanlarda büyük/küçük harf duyarsız benzersizlik
  > sağlıyor (`docs/domain-model.md` §0).
- [x] Redis'i cache + queue sürücüsü olarak bağla
- [x] **Yerel alan adları:** `marka-a.localhost` ve `marka-b.localhost`
  > **Neden `.localhost`:** kiracılık alan adına bağlı olduğu için (M-2.2) yerelde en az
  > iki alan adı gerekiyor. Tarayıcılar `.localhost` uzantısını `/etc/hosts` düzenlemeden
  > çözer — `dnsmasq` kurmaya gerek yok.
- [x] `docker compose up` ile ayağa kalktığını doğrula
- [x] `worker` konteynerinin kuyruğu gerçekten tükettiğini doğrula (deneme işi at, çalışsın)

### 0.3 Kod kalitesi araçları

- [x] **Pint** (kod biçimlendirme) kur ve yapılandır — `pint.json`, preset `laravel`
  > **Neden:** biçim tartışmasını tamamen ortadan kaldırır.
- [x] **Larastan** (statik analiz) kur — **seviye 8** (`phpstan.neon`)
  > ⚠️ **Plandan sapma:** 5 yazıyordu, 8 yapıldı. "Düşükten başla" kuralı *mevcut kodda
  > yüzlerce uyarı çıkmasın* diye vardır; bizim kod tabanımız boş ve seviye 8'de sıfır
  > uyarı veriyor. Sonradan yükseltmek, o güne kadar yazılmış her koda geri dönmek demek.
  >
  > Seviye 8'in kazandırdığı: **null olabilecek değere kontrolsüz erişim.** Denendi —
  > `User::find($id)` sonrası `->name` yazmak seviye 5'te sessiz geçiyor, 8'de yakalanıyor.
  > 9-10 sonraya bırakıldı (Laravel'in `request()->input()` gibi uçları doğal olarak
  > `mixed` döndürüyor).
  > **Neden:** kodu çalıştırmadan hata bulur — yanlış tip, olmayan metot, null olabilecek
  > değer. Seviye kademeli artırılır.
- [x] `composer.json`'a kısayol komutları: `lint`, `lint:check`, `analyse`, `test`
- [x] `laravel/sail` kaldırıldı (0.1'den devreden not) — kendi Compose'umuz var
- [x] Laravel'in `tests/Unit/ExampleTest.php` yer tutucusu silindi
  > Larastan'ın bulduğu ilk gerçek hata buydu: `assertTrue(true)` — her zaman geçen,
  > hiçbir şey doğrulamayan test.

### 0.4 Test altyapısı

- [x] **Pest** kur (`tests/Pest.php`), örnek test yaz ve geçtiğini gör
  > PHPUnit'in sınıf/metot töreni yerine `it('...', function () { ... })`. Arkada yine
  > PHPUnit çalışıyor, fark yalnızca yazım kolaylığı.
- [x] Test veritabanını ayır — **`tikmarka_test`, PostgreSQL üzerinde**
  > ⚠️ **Plandan sapma değil ama önemli bir düzeltme:** Laravel'in varsayılan
  > `phpunit.xml`'i testleri **SQLite bellek içinde** koşturuyordu. Bizde çalışmaz —
  > şema (kiracılığın tamamı), `citext`, `jsonb` ve `SELECT FOR UPDATE` SQLite'ta yok.
  > SQLite'ta test etmek "yeşil test, patlayan üretim" demekti.
- [x] `RefreshDatabase` davranışını doğrula: her test temiz veritabanıyla başlasın
  > **Neden:** testler birbirinin verisine bulaşırsa, tek başına geçen test paket hâlinde
  > patlar. Teşhisi çok zordur, baştan engellenir.
  >
  > **Nasıl çalışıyor:** migration'lar bir kez koşar; her test `BEGIN TRANSACTION` ile
  > başlar, bitince `ROLLBACK`. Migration tekrar çalışmadığı için hızlı.
- [x] **Yaşanan tuzak — `env_file` testleri geliştirme veritabanına yönlendiriyordu**
  > `docker-compose.yml`'de `env_file: .env` vardı; bu, değerleri konteynerin **process
  > ortamına** enjekte ediyor ve PHP onları `$_SERVER`'a koyuyor. Laravel'in `env()`
  > helper'ı `$_SERVER`'ı önce okuduğu için `phpunit.xml`'deki `force="true"` bile
  > yetmedi — testler **geliştirme veritabanında** koştu ve oraya migration attı.
  >
  > **Çözüm:** `app` ve `worker`'dan `env_file` kaldırıldı. Laravel `.env`'i zaten
  > dosyadan okuyor (proje dizini konteynere bağlı). Ölçüldü: `$_SERVER` artık boş,
  > `env()` doğru veritabanını görüyor.
- [x] `phpunit.xml`'deki `DB_*` satırlarına `force="true"` eklendi

### 0.4b Atılacak alıştırma — Laravel'in temel döngüsü *(öğrenme adımı)*

- [x] Bir `Note` migration'ı, bir `Note` modeli, bir test yaz — çalıştır, testi yeşil gör
- [x] **Sonra hepsini sil**

> **Alıştırmadan çıkanlar:**
>
> | Gördüğümüz | Nasıl |
> |---|---|
> | Migration'ın ürettiği SQL | `php artisan migrate --pretend` — çalıştırmadan gösteriyor |
> | Modelin ürettiği SQL | `DB::listen()` ile INSERT cümlesi yakalandı |
> | Konvansiyon | `Note` sınıfı → `notes` tablosu; hiçbir yere kayıt yapılmıyor |
> | `$fillable` koruması | listede olmayan alan sessizce yok sayılıyor |
> | `down()` ne işe yarar | silme adımında gerçekten kullanıldı |
> | Testin kırmızıya düşmesi | `$fillable`'dan `body` çıkarılınca 4 testten **yalnızca 1'i** kırıldı — küçük ve tek konulu test, problemin yerini söylüyor |
> | `RefreshDatabase` | dört test de not oluşturuyor ama ilki "tam 1 tane" diyerek geçiyor |
>
> **Yakalanan gerçek sorun:** Laravel'in `timestamps()` metodu `timestamp without
> time zone` üretiyor, bizim kararımız `timestamptz`. Uyarı 1A.1'e yazıldı.

> **Neden bu adım var:** 0.5'teki çok kiracılık, "normal" Laravel'in **üstüne kurulan**
> bir katman. Altındaki katmanı bir kere kendi elinle yapmadan üstündekini anlamak zor —
> her şey aynı anda yeni olur. Bu alıştırmadan sonra 0.5, "bunun üstüne ne ekleniyor"
> sorusuna dönüşür.
>
> Plan kuralı 1'i (iş mantığından önce kiracılık) bozmuyor: bu proje kodu değil, silinecek
> bir egzersiz. Depoya commit edilmez.

### 0.5 Kiracılık zemini ← **bu bloğun kalbi**

- [x] **Kiracı testleri için düzen kur** — ayrı test paketi: `tests/Tenancy/`
  > **Neden 0.4'ten buraya taşındı:** bu düzen `stancl/tenancy`'ye dayanıyor, paket
  > kurulmadan yazılamaz. 0.4'te planlanmıştı, gerçekle çeliştiği için taşındı (kural 4).
  >
  > ⚠️ **`RefreshDatabase` ile çalışmıyor, denendi ve hata alındı:**
  > `SQLSTATE[3F000] Invalid schema name`. Sebep: `RefreshDatabase` testi transaction'a
  > sarıyor, `Tenant::create()` içindeki `CREATE SCHEMA` commit edilmemiş oluyor, marka
  > migration'ları ise **ayrı bir bağlantıda** (`tenant`) koştuğu için o şemayı göremiyor.
  >
  > **Çözüm:** `tests/Tenancy/` ayrı `phpunit.xml` paketi, transaction yok, temizlik
  > `afterEach`'te (kiracıları sil → şemalar düşer, cache temizle).
- [~] `stancl/tenancy` kur, **şema bazlı** (`PostgreSQLSchemaManager`) yapılandır (M-2)
  - [x] paket kuruldu — **v3.10.0**
  - [x] **Laravel 12 uyumu doğrulandı:** paketin `composer.json`'ı
        `illuminate/support: ^10|^11|^12|^13` istiyor, bizde Laravel 12.64.0.
        Tahmin değil, ilan edilmiş destek.
  - [x] **Şema modu doğrulandı:** `PostgreSQLSchemaManager` sınıfı pakette mevcut
        (`PostgreSQLDatabaseManager` da var — M-2'deki "geçiş tek satır yapılandırma"
        iddiası böylece kanıtlandı)
  - [x] ayar dosyaları projeye çıkarıldı (`vendor:publish`)
  - [x] impersonation migration'ı silindi — kapsam dışı özellik (kural 5)
  - [ ] `config/tenancy.php` şema moduna göre düzenlenecek ← 2. adım
  > ⚠️ **Önce Laravel 12 uyumluluğunu doğrula.** Paket framework'ün başlatma sürecine
  > giriyor (M-2.6), dolayısıyla sürüm uyumu ilan edilmiş olmalı — "muhtemelen çalışır"
  > kabul edilmez. Uyumlu sürüm yoksa karar M-2.6'ya geri döner (elle yazmak), plan
  > buna göre güncellenir.
- [x] Migration'ları ikiye ayır: `database/migrations/landlord` ve `database/migrations/tenant`
  > **Kök klasör bilerek BOŞ bırakıldı.** Laravel migration'ları ararken alt klasörlere
  > bakmıyor (`glob($path.'/*_*.php')`, özyinelemeli değil). Kök dolu olsaydı
  > `make:migration` ile üretilen her yeni dosya oraya düşer ve **kazara merkez şemaya**
  > giderdi — oysa bundan sonra yazacağımız tabloların çoğu marka tablosu. Kök boş olunca
  > `php artisan migrate` hiçbir şey bulamıyor, yani sessizce yanlış iş yapmıyor.
  >
  > Kısayollar `composer.json`'a eklendi: `migrate:landlord`, `migrate:fresh:landlord`.
  > Marka tarafı için paketin kendi komutu var: `tenants:migrate`.
- [x] Merkez şema migration'ları: `tenants`, `domains` (`docs/domain-model.md` §2)
  > ⚠️ **Paketin `tenants` tablosu domain modelimizden farklı geldi.** Paket "virtual
  > column" yaklaşımı kullanıyor: `schema_name`, `status`, `name` gibi alanlar ayrı kolon
  > değil, tek bir `data` json kolonunda duruyor. Gerçek kolon eklenirse paket onu
  > kullanıyor — yani sonradan çevrilebilir.
  >
  > **Şimdilik varsayılanla devam:** 0.5'in amacı izolasyonu kanıtlamak. `status` ve
  > abonelik alanları **Faz 3**'ün konusu; gerçek kolonlara orada çevrilecek. Aksi hâlde
  > "SQL ile durumu active olanları getir" sorgusu json içinden yapılmak zorunda kalır.
  > `plans` / `subscriptions` Faz 3'e bırakıldı — Faz 0'da tek bir varsayılan planla yaşanır.
- [x] Alan adı çözümleme middleware'i (M-2.2): host → `domains` → şema
  - [x] `App\Platform\Models\Tenant` yazıldı — paketin hazır modelinde `domains()`
        ilişkisi yok, alan adı yöntemi onsuz çalışmıyor (`HasDomains` + `HasDatabase`)
  - [x] `TenancyServiceProvider` **elle** `bootstrap/providers.php`'ye eklendi
        > `vendor:publish` dosyayı kopyalıyor ama listeye eklemiyor. Migration'lardan
        > farklı: provider'lar taranmaz, **kaydedilir** — sıra önemli olduğu için.
  - [x] `routes/web.php` merkez alan adına kilitlendi
        > İki rota dosyası da `/` tanımlıyordu; sonra yüklenen diğerini gölgeliyor ve
        > merkez adres erişilemez hâle geliyordu. `Route::domain()` ile çözüldü.
  - [x] Tanımsız alan adı **404** dönüyor (`bootstrap/app.php` → `withExceptions`)
        > Varsayılan 500'dü. Yanlış mesaj: sunucu patlamadı, öyle bir marka yok.
  - [x] Şema öneki `tenant` → `tenant_` düzeltildi
        > Ayraçsız hâli `tenantea940248-...` gibi okunmaz adlar üretiyordu.
  - [x] Merkez migration klasörü `AppServiceProvider`'da kaydedildi
        > **PHP trait öncelik kuralı:** `tests/TestCase`'te `migrateFreshUsing()` ezmek
        > işe yaramadı — trait metodu (RefreshDatabase) miras alınan metodu geçiyor.
        > Sıra: sınıfın kendi metodu > trait > üst sınıf. Çözüm `loadMigrationsFrom()`.
  - [x] Doğrulandı: `marka-a` ve `marka-b` kendi kiracı kimliklerini dönüyor, merkez 200

> ⚠️ **Yaşanan tuzak — ölü veritabanı oturumu testleri sonsuza kadar kilitledi.**
> Zaman aşımına uğrayan bir test koşusu `idle in transaction` durumunda bir oturum
> bıraktı; sonraki her `migrate:fresh` o kilidi bekleyip asıldı. Belirti "test takılıyor",
> sebep hiçbir yerde yazmıyor. Teşhis: `pg_stat_activity`'de `wait_event_type = Lock`
> satırlarına bakmak. Çözüm: `pg_terminate_backend`.
>
> ⚠️ **`Tenant::create()` testte kiracı şeması oluşturmaya kalkıyor** (TenantCreated →
> CreateDatabase işi, testlerde `sync`) ve `RefreshDatabase`'in işlemiyle kilitleniyor.
> Bu yüzden altyapı testi kiracı oluşturmuyor. Gerçek kiracı testleri 8. adımda, kendi
> düzeniyle yazılacak.
- [ ] **M-2.4'ün beş tuzağını çöz** — hepsi `app/Tenancy/` altında, tek yerde:
  - [x] **Kuyruk** — paket `tenant_id`'yi işin gövdesine yazıyor, worker `JobProcessing`
        olayında okuyup kiracıyı devreye alıyor
        > ⚠️ **Gerçek sızıntı yaşandı.** İki markanın işi de merkez klasöre yazdı, ikincisi
        > birincinin üstüne bindi ve **hiçbir hata çıkmadı**. Sebep koda değil çalışan
        > sürece aitti: `worker` konteyneri paket kurulmadan önce başlatılmıştı, kodu
        > belleğe aldığı için kiracılık dinleyicisi hiç kaydedilmemişti. 0.2'de kendi
        > yazdığımız "bayat kod" uyarısının gerçekleşmiş hâli.
        > **Sonuç: deploy adımlarında worker yeniden başlatmak izolasyonun şartı.**
  - [x] **Cache** — **etiket (tag)** ile, önek değil. Aynı anahtar A/B/merkezde ayrı değer
        > ⚠️ Şartı var: etiket destekleyen depo. Redis ✓ · `file`/`database` **desteklemez**
  - [x] **Dosya** — `storage_path()` kökü değişiyor: `storage/tenant<kimlik>/app/`
        > `worker` konteynerinin `app`'in yazdığı dosyaları gördüğü de doğrulandı (ortak volume).
        > ⚠️ `.gitignore`'a `/storage/tenant*` eklendi — yoksa markaların yüklediği
        > dosyalar (ürün görselleri dahil) public depoya girecekti.
  - [x] **Zamanlanmış iş** — pakette hazır çözümü **yok**, kural bizde:
        `tenants:run <komut>` (`routes/console.php`'de yazılı)
        > ⚠️ Ayrıca zamanlayıcıyı çalıştıran süreç de yoktu — `docker-compose.yml`'e
        > `scheduler` servisi (`schedule:work`) eklendi. Onsuz hiçbir görev hiç çalışmazdı.
  - [x] **`search_path`** — paket sıfırlamıyor, bağlantıyı **imha ediyor** (`purge`) ve
        her kiracıya `search_path`'i config'inde gömülü yeni bağlantı açıyor
        > 8 dönüşümlü istek + araya merkez isteğiyle ölçüldü, sızıntı yok.
- [x] `php artisan tenant:create` komutu — şema oluştur, migrate et, alan adı bağla
      (M-2.5'in 1–3 ve 6. adımları; varsayılan veri ve sahip kullanıcı 1A'da eklenecek)
  > `app/Tenancy/Commands/CreateTenant.php`. Şema oluşturma ve marka migration'ları
  > paketin olay zincirinde otomatik koşuyor; komut satır + alan adı + doğrulama ekliyor.
  >
  > **Üçüncü kez aynı ders:** Laravel artisan komutlarını yalnızca
  > `app/Console/Commands`'ta kendiliğinden bulur. M-2.7 gereği `app/Tenancy/` altında
  > olduğumuz için `bootstrap/app.php` → `withCommands()` ile klasörü tanıttık.
  > (migration = taranır · provider = kaydedilir · komut = yerindeyse taranır)
  >
  > Doğrulandı: şema + tablolar oluşuyor · yinelenen alan adı ve boş argüman
  > **çıkış kodu 1** ile reddediliyor · kiracı silinince şeması da düşüyor.
  > Eksikler kodda `TODO(1A)` / `TODO(Faz 3)` olarak işaretli.
- [x] Caddy `ask` ucu: `GET /tenancy/domain-check?domain=` → `domains`'e bak, 200/404 dön
  > `app/Http/Platform/DomainCheckController.php`, rota `routes/web.php` içinde merkez
  > alan adına bağlı. Doğrulandı: kayıtlı → 200 · kayıtsız → 404 · boş → 404 ·
  > BÜYÜK harfli → 200 (sınırda küçültme yapılıyor).
  >
  > **Kimlik doğrulaması yok, olamaz** — Caddy kimlik sunamıyor. Sızdırdığı tek bilgi
  > "bu alan adı kayıtlı mı", ki alan adları zaten herkese açık.
  >
  > Caddy tarafı **Faz 3'te** bağlanacak; `docker/Caddyfile` başındaki nota eklenecek
  > yapılandırma hazır yazılı.
  > ⚠️ **M-4.1/1 — bu uç olmadan on-demand TLS açılmaz.** Açılırsa IP'mize yönlendirilen
  > her alan adı için sertifika alınmaya çalışılır ve kotamız yanar.
- [ ] `app/` dizin yapısını kur (M-2.7): `Platform/`, `Tenancy/`, `Domain/`, `Http/`
- [x] **Testler — bu bloğun asıl çıktısı** · `tests/Tenancy/` · **20 test yeşil**
  - [x] İki kiracı oluşturuluyor, ikisinin de şeması geliyor
  - [x] Marka tabloları her şemada ayrı ayrı kuruluyor
  - [x] A kiracısında yaratılan kayıt B kiracısından **görünmüyor**
  - [x] Aynı e-posta iki markada ayrı ayrı kullanılabiliyor (`unique` şema içinde)
  - [x] Tanımsız alan adı **404** dönüyor · merkez adres kiracı çözümlemesi yapmıyor
  - [x] Kuyruğa atılan işin **gövdesinde kiracı kimliği** taşınıyor; merkez bağlamda
        atılan işte taşınmıyor
  - [x] Aynı cache anahtarı iki kiracıda **farklı değer** dönüyor
  - [x] Aynı dosya adı iki kiracıda **ayrı klasöre** yazılıyor
  - [x] Kiracıdan çıkınca merkez bağlama dönülüyor
  - [x] `domain-check` ucu: kayıtlı 200 · kayıtsız 404 · boş 404 · BÜYÜK harfli 200

> **Kırmızı görüldü.** `config/tenancy.php`'den `CacheTenancyBootstrapper` kapatıldığında
> **yalnızca** cache testi kırıldı, diğer 15'i geçmeye devam etti. Testler gerçekten
> koruyor ve kırılan test problemin yerini söylüyor.
>
> ⚠️ **Test ortamı gerçeğe uyduruldu — 0.4'teki SQLite tuzağının aynısı çıktı.**
> `phpunit.xml`'de `CACHE_STORE=array` vardı; o sürücüde veri yöneticinin *içinde*
> durduğu için kiracı değişince paket yöneticiyi değiştirince veri kayboluyor ve test
> **gerçekte olmayan bir hata** gösteriyordu. Testler artık gerçek Redis kullanıyor
> (ayrı veritabanları: cache 15, kuyruk 14 — çalışan `worker` test işlerini kapmasın).
>
> 📌 `phpstan.neon`'a `tests/*` kapsamlı tek istisna eklendi: Pest'te `$this` çalışma
> anında bağlandığı için statik analiz `$this->get()`'i tanımıyor. Uygulama kodunda
> kural aynen geçerli.

### 0.6 Sürekli entegrasyon (CI)

- [x] GitHub Actions iş akışı: her `push` ve PR'da `lint` + `analyse` + `test`
  > `.github/workflows/ci.yml`. `lint` değil **`lint:check`** kullanılıyor — CI dosyayı
  > düzeltmez, yalnızca denetler. Düzeltseydi hatayı gizlemiş olurduk.
  > **Neden:** "bende çalışıyordu" durumunu ortadan kaldırır.
- [x] İş akışında PostgreSQL ve Redis servislerini tanımla
  > Sağlık kontrolleriyle birlikte — yereldeki `healthcheck` ile aynı fikir.
  > `citext` eklentisi elle kuruluyor: `docker/postgres/init.sql` CI servisinde yok.
  >
  > ⚠️ **`phpunit.xml`'de `DB_HOST` artık `force="true"` değil.** Yerelde Docker ağında
  > sunucunun adı `postgres`, CI'da servisler aynı makinede olduğu için `127.0.0.1`.
  > Sabitlenseydi CI yanlış adrese bağlanmaya çalışırdı.
- [x] ~~**Docker Hub girişi**~~ → **gerekmiyor, madde kapatıldı**
  > **Neden düştü:** bu madde TıkRota'dan devralınmıştı ve CI'ın **imaj build ettiği**
  > varsayımına dayanıyordu. Bizim iş akışımız imaj build etmiyor: PHP'yi `setup-php`
  > ile kuruyor, `postgres`/`redis`'i GitHub'ın servis mekanizması yönetiyor.
  > Anonim indirme kotası riski yok. (Plan kuralı 4)
- [x] Bilerek bozuk bir kod atıp CI'ın **kırmızı** döndüğünü doğrula, sonra düzelt
  > **Neden:** hiç kırmızı görmediğin bir CI, gerçekten çalıştığını kanıtlamaz.
  >
  > **Yapıldı:** `DomainCheckController`'daki kayıt kontrolü kaldırıldı, gönderildi.
  > Geçmiş: ✅ → ❌ → ❌ → ✅
  >
  > ⚠️ **İlk kırmızıda bir eksik ortaya çıktı:** adımlar ilk hatada duruyordu, biçim
  > hatası testlere sıra gelmesini engelledi. Önemsiz bir boşluk sorunu asıl mantık
  > hatasını gizliyordu. `if: always()` eklendi — üç kontrol de çalışıyor, iş yine
  > kırmızı dönüyor ama bütün resim tek bakışta görünüyor.
  >
  > **Üç aracın farklı iş yaptığı kanıtlandı:**
  > `Pint ✗` biçim · `Larastan ✓` tip doğru, hata bulamadı · `Pest ✗` mantık yanlış.
  > Kod tip olarak doğru ama iş kuralı yanlıştı — statik analiz bunu **göremez**,
  > yalnızca test yakalar.
- [x] README'ye CI rozeti eklendi

### 0.7 Belgeler

- [x] `README.md` — projenin ne olduğu, mimari, kurulum adımları, komutlar
  > Depo public olduğu için 0.7'yi beklemeden yazıldı (0.4'ten sonra).
- [x] `docs/summary.md` — tek sayfalık özet; her blok bitince kısa kayıt eklenir
- [x] `docs/pre-setup.md` ve `docs/domain-model.md` yazıldı
- [x] `PLAN.md` kökte (günlük kullanılan dosya)

---

## Faz 1 — Çekirdek mağaza

**Amacı:** bir müşterinin gerçekten sipariş verebildiği çalışan bir mağaza.
Arayüz yok — her şey API ve testler üzerinden (M-3).

**Bitiş ölçütü:** tek bir uçtan uca test şunu yapabiliyor: **misafir** kullanıcı ürünü
sepete atar → ödeme yapar → sipariş oluşur → stok düşer → sipariş **kısmi olarak** sevk
edilir → olaylar kaydedilir. Ve aynı akış **ikinci bir kiracıda** da çalışıyor, iki
kiracının verisi birbirine karışmıyor.

> ✅ **FAZ 1 TAMAMLANDI** — 1A'dan 1F'e. **Faz 2 açık**: 2H bildirim ilk iş.

- [x] **1A — Kimlik, yetki ve mağaza ayarları** ✅ ← aşağıda
- [x] 1B — Katalog ✅
- [x] 1C — Sepet ✅
- [x] 1D — Stok + Sipariş + Sevkiyat ✅
- [x] 1E — Ödeme ✅
- [x] 1E.7 — iyzico ✅ *(Faz 5'ten öne çekildi, gerçek sandbox'ta doğrulandı)*
- [x] 1F — Olay kaydı ✅

---

### Blok özetleri — 1B … 1F

> Bunlar **madde listesi değil**, blok haritasıdır. Amacı Faz 1'in tamamını görünür kılmak.
> Her blok için üç şey yazılı: temel akış, dokunduğu tablolar, bitiş ölçütü. Üçü de veri
> modelinden türediği için **eskimez.** Madde listesi blok açılırken yazılır (kural 4).

#### 1B — Katalog

```
PANEL TARAFI                         VİTRİN TARAFI
 eksen tanımla (Renk · Beden)         ProductQuery::forStorefront()
 kategori ağacı kur                     → kategori (alt ağaç dahil)
 ürün oluştur (draft)                   → liste (fiyat: en düşük varyant)
   → ekseni seç, varyant üret           → ürün detay (eksenler + görseller)
   → sku · fiyat · stok
   → görsel yükle (sıralı)
   → status = active
```

**Tablolar:** `options` · `option_values` · `categories` · `products` ·
`product_options` · `product_variants` · `product_images`

---

##### Kararlar (araştırmayla doğrulandı — Shopify · Magento)

**1B-K1 · Her ürünün en az bir varyantı olur. İstisna yok.**

Tek seçenekli üründe (kitap) bile varyant kaydı açılır.

> **Neden:** istisna olsaydı "ürün mü varyant mı" sorusu sepete ekleme, stok düşme,
> sipariş satırı ve fiyat okuma yollarının **her birine** bir `if` olarak dağılırdı — ve
> her biri bir gün yanlış dalı seçerdi. Tek satır fazla veri, yüzlerce satır az kod.

**1B-K2 · Fiyat ve stok VARYANTTA, KDV ve metin ÜRÜNDE.**

Sonucu: *ürünün fiyatı* diye bir alan yok, **türetiliyor** — aktif varyantların en düşüğü
("1.299 TL'den başlayan fiyatlarla"). Her ürün listesi varyantlara bakmak zorunda; N+1
tuzağının doğduğu yer burası ve `ProductQuery`'nin ilk görevi bunu tek yerde çözmek.

**1B-K3 · Varyant eksenleri MAĞAZA seviyesinde tanımlanır (Magento modeli).**

```
options          id · name "Renk" · position                 MAĞAZA seviyesinde
option_values    id · option_id · value "Kırmızı"
                 · swatch (renk kodu, null) · position
product_options  product_id · option_id · position           ürün hangi ekseni
                                                             kullanıyor + SIRA
variants.options jsonb {"renk":"Kırmızı","beden":"M"}         OKUMA KOLAYLIĞI kopyası
                 UNIQUE(product_id, options)
```

Üç model incelendi: Shopify klasik (üründe `option1/2/3` kolonları, denormalize, sabit
3 eksen) · Shopify 2024 (`ProductOption`/`ProductOptionValue` tanım tablosu, **ürüne
ait**) · Magento (super attribute, **mağazaya ait**).

> **Neden serbest jsonb yetmiyor:** Shopify bile kendi modelini tanım tablosuna taşıdı.
> Serbest metinde `{"renk":"Kırmızı"}` ve `{"Renk":"kırmızı"}` iki ayrı varyant olur,
> hata vermez. Aynı gerekçeyle `SettingGroup`, `Permission` ve `LegalDocumentType`
> enum'larını da sabitlemiştik.
>
> **Neden ürüne değil mağazaya ait:** fark ürün eklerken değil, **kategori sayfasında
> filtre yazarken** görünüyor. Ürüne ait olsaydı 200 üründen sonra 200 ayrı "Renk"
> ekseni birikir; "Renk: Kırmızı" filtresi dört ayrı seçenek gösterir. Ayrıca renk kodu
> ve kumaş görseli eksene **bir kez** yazılıyor (Shopify'ın yeni modele "metafield bağı"
> eklemesinin sebebi de bu). Filtreleme Faz 2 planında zaten var ve tutarlı değer
> olmadan çalışmıyor.
>
> **Bedeli:** bir tablo fazla; marka ürün eklerken önce ekseni tanımlamak zorunda.

**1B-K4 · Sınırlar veritabanında değil, DOĞRULAMADA.**

En fazla **3 eksen** · ürün başına en fazla **200 varyant**.

> Shopify 10+ yıl "3 eksen · 100 varyant" ile yaşadı; sebep kombinatorik patlama
> (6×5×4×3 = 360 varyant panelde de sorguda da boğulur). Sınırı DB'ye koymak sonradan
> gevşetmeyi migration'a çevirir; doğrulamada tutmak tek satırlık değişiklik.

**1B-K5 · Varyant benzersizliği: `UNIQUE (product_id, options)`.**

> jsonb üzerinde çalışır çünkü PostgreSQL anahtar sırasını normalize ediyor —
> `{"renk":"K","beden":"M"}` ile `{"beden":"M","renk":"K"}` **aynı** değer.
> ⚠️ Kod yazılmadan ölçülecek. Olmasaydı "Kırmızı/M" seçen müşteri hangi stoğu
> düşürdüğünü bilemezdi.

**1B-K6 · Kategori ağacı: `parent_id` + `path` (id zinciri) + `level`.**

```
categories   id · parent_id · name · slug · path "/1/5/12/" · level · position
```

- `parent_id` → ağacı **düzenlemek** için doğru yapı
- `path` → **sorgu** için tekrarlı veri: "Giyim ve altındaki her şey" tek ön ek taraması
  (`path LIKE '/1/5/%'`), özyinelemeli CTE'ye gerek kalmıyor
- `level` → menüde "2 seviye göster" sorusunu path ayrıştırmadan cevaplıyor

> **`ltree` KULLANILMIYOR — ikinci `citext`.** Ölçüldü: `search_path` marka şemasıyken
> `'giyim'::ltree @> 'giyim.tisort'::ltree` → **operatör bulunamadı hatası**. Eklenti
> `public`'te, marka onu görmüyor (paket `search_path`'i yalnızca şemaya kuruyor:
> `PostgreSQLSchemaManager.php:49`). citext'ten farkı: citext sessizce `false` dönüyordu,
> ltree gürültülü patlıyor — metin için `@>` operatörü olmadığından geri düşecek yer yok.
> Yine de kullanılamaz; `path` düz `varchar`.
>
> **`path` neden id zinciri, slug zinciri değil:** Magento da entity id kullanıyor
> (`path = "1/2/3"`). Slug tutulsaydı marka `tisort` → `t-shirt` düzeltmesi yapınca
> **alt ağacın tamamının** path'i yeniden yazılırdı.
>
> **`children_count` ALINMADI** (Magento'da var): bakımı gereken ikinci tekrarlı veri ve
> Magento belgelerinde bile "bozulursa path'ten yeniden hesaplayın" diye anlatılıyor —
> bozulabilen alan demek. Bizim ölçeğimizde `EXISTS` yeterli.
>
> **İndeks ayrıntısı:** `CREATE INDEX ... ON categories (path text_pattern_ops)`.
> Türkçe collation altında düz btree, `LIKE 'x/%'` için **kullanılmaz**; sorgu çalışır,
> sadece her seferinde tam tarama yapar. 100 kategoride fark edilmez, 2000'de edilir.

**1B-K7 · Ürün ↔ kategori TEK. Çoklu üyelik koleksiyonun işi (Faz 2).**

```
KATEGORİ = sınıflandırma       "bu ürün NEDİR"    ağaç · tek · kırıntı üretir
KOLEKSİYON = vitrin düzenleme  "NEREDE göstereyim" düz · çok · Faz 2
```

> Shopify'ın ayrımı birebir aynı: Category (hiyerarşik, ürün başına tek, veri temizliği
> ve vergi/kanal uyumu için) vs Collection (marka oluşturur, ürün başına çok, alışveriş
> deneyimi için). Tek farkımız: Shopify'da kategori listesi **platform tarafından
> sabit**, bizde marka kendi ağacını kuruyor — tek satıcılı D2C'de menü markanın kimliği.
>
> İkisi tek tabloda birleştirilseydi ürün üç "kategoride" olunca ekmek kırıntısı hangi
> yolu göstereceğini bilemezdi.
>
> **Faz 2 notu:** koleksiyon **manuel + KURALLI** olmalı ("fiyatı 250₺ altındakiler
> otomatik girsin"). Shopify'ın smart collection'ı; ilk planda yalnızca manuel yazılmıştı.

**1B-K8 · Satılamayan ürün vitrinde YOK. Doğrudan bağlantı da 404.**

```
products.status  draft · active · archived      marka: "satıyor muyum"
variants.is_active                              marka: "bu seçeneği satıyor muyum"
variants.stock                                  SİSTEM: kaç adet kaldı
```

Türetilen tek cevap:

```
ürün.status = active  VE  en az bir varyant: is_active AND satın alınabilir
                                                        ▲
                                        1B: stock > 0
                                        1D: stock - rezerve > 0   TEK YERDE değişir
```

> **"Tükendi" saklanan bir durum DEĞİL.** `status = 'sold_out'` kolonu olsaydı: müşteri
> son adedi alınca kim çevirecek, marka stok girince kim geri çevirecek, iade gelince
> kim? Her biri ayrı kod yolu; biri unutulunca "stokta var ama sayfada tükendi yazıyor"
> olur ve **hata vermez**. Tükenmişlik bir durum değil, bir sonuç. (1A'da `is_published`
> sakladık çünkü bir *karar*; "yayına hazır mı"yı saklamadık çünkü bir *hesap*.)
>
> ⚠️ **1D TUZAĞI, şimdiden kapatılıyor:** `stock > 0` kontrolü 1B'de üç-beş yere
> serpiştirilirse 1D'de rezervasyon gelince hepsini bulmak gerekir; biri kaçarsa
> **aşırı satış** olur, hatasız. Kural tek bir yerde yazılacak.
>
> **Doğrudan bağlantı 404:** listede yoksa hiç yok. "Sayfa açılsın ama tükendi yazsın"
> seçeneği iki farklı görünürlük tanımı doğurur ve `ProductQuery`'nin tek kapı olma
> iddiasını çatlatır.
>
> **Faz 2'ye ertelendi — "yakında gelecek" + stok bildirimi.** Marka bir ürünü bilinçli
> olarak "geri gelecek" işaretleyebilmeli; ama bu bir bayrak değil bir **akış**:
> işaretle → müşteri "haber ver" bırakır → stok girilince kuyruk işi e-posta atar.
> Bayrağı tek başına koymak müşteriyi daha kibar bir çıkmaz sokağa götürürdü.

**1B-K9 · Ürün adresi düz: `/urun/{slug}` — kategori yolu İÇERMEZ.**

> Shopify iki adresi de kabul ediyor (`/collections/yaz/products/tisort` ve
> `/products/tisort`) ama canonical **her zaman düz olana** işaret ediyor; tavsiye edilen
> tema kodunda bile düz adrese bağlanmak. Sebep: ürün üç koleksiyondaysa üç adres doğuyor,
> aynı içerik üç kez indeksleniyor ve bağlantı gücü bölünüyor.
>
> İkinci gerekçe bizim: kategori taşınınca (`Tişört`, `Giyim`'den `Erkek`'in altına)
> **adres değişmiyor**, eski bağlantılar kırılmıyor.

**1B-K10 · `ProductQuery` — tek kapı.**

```
forStorefront()                      forPanel()
  status = active                      hepsi (draft · active · archived)
  + satılabilir varyantı olanlar       tükenmiş de görünür
  cost_price ASLA                      cost_price DAHİL
  varyant + görsel DAİMA birlikte      
```

> **İki sızıntı riski var, ikisi de sessiz:** `cost_price` (maliyet) vitrine çıkarsa marka
> rakibine kârını gösterir · taslak ürün vitrine çıkarsa yayınlanmamış kampanya sızar.
> Her uçta ayrı sorgu yazılsaydı 1B'de doğru yazılır, 1C'de sepet ürünü çekerken
> unutulurdu. **`Product::query()` doğrudan kullanılmayacak.**
>
> N+1 de burada çözülüyor: liste fiyat türetmek için varyantlara bakmak zorunda, bu yüzden
> `forStorefront` varyantları ve görselleri **daima** birlikte yüklüyor.

---

##### Bloklar

- [x] **1B.1** eksen tanımları: `options` · `option_values` + panel uçları
- [x] **1B.2** `categories`: ağaç + `path`/`level` bakımı + döngü engeli (`CategoryService`)
- [x] **1B.3** `products` + `product_options` + `product_variants` (üretim + benzersizlik)
- [x] **1B.4** `product_images` — yükleme, sıra, kiracı klasörü (M-2.4/3)
- [x] **1B.5** `ProductQuery` + vitrin uçları (liste · detay · kategori)
- [x] **1B.6** blok kapanışı: tohumlayıcıya katalog · iki kiracıda doğrulama · CI

> **İKİ KİRACIDA DOĞRULANDI** (gerçek HTTP, 7 başlık — hepsi geçti):
> vitrin listesi kimlik doğrulama olmadan 200 · taslak ürüne doğrudan bağlantı 404 ·
> cevapta `cost_price` **hiç geçmiyor** (anahtar da değer de) · `from_price` tükenmiş
> varyantı atlıyor (99.90 yerine 249.90) · kategori filtresi alt ağacı kapsıyor
> (giyim 2, tisort 1) · görsel sahibinden 200 yabancıdan 404 · mağaza kapanınca
> vitrin 503 + `Retry-After`, **panel 200**, ve B markası etkilenmiyor.
>
> **Tohumlayıcıya katalog eklendi:** kategori ağacı · iki eksen (renk kodlarıyla) ·
> 9 varyantlı ürün · tek varyantlı ürün (1B-K1'in canlı örneği) · **bir taslak ürün**.
> Taslak gösterim için değil sınav için: 1C'de "taslak ürün sepete eklenebiliyor mu"
> sorusunu elle veri üretmeden deneyebilelim. Görseller GD ile üretilip gerçek
> dosya olarak marka klasörüne yazılıyor.

---

## ✅ FAZ 1B TAMAMLANDI

```
1B.1 eksenler (mağaza seviyesinde)   1B.2 kategori ağacı (path + level)
1B.3 ürün · eksen bağlama · varyant  1B.4 görseller (kiracı klasörü)
1B.5 ProductQuery + ilk vitrin uçları

161 test · lint · analyse (seviye 8) · CI — hepsi yeşil
```

**1B'nin ölçerek öğrettikleri** — hiçbiri tahmin değil:

| Ölçüm | Sonuç | Olmasaydı |
|---|---|---|
| Türkçe küçük harf | `Kırmızı`→`kırmızı` ama `KIRMIZI`→`kirmizi` | aynı eksen iki satır, filtre ikiye bölünür |
| `jsonb` vs `json` | sıra normalize ediliyor / edilmiyor | `UNIQUE` sıra değişen kopyayı yakalamaz |
| `ltree` marka şemasında | operatör bulunamıyor | ikinci `citext` — ama bu gürültülü patlıyor |
| `substring(text,?)` | metin parametre → REGEX sürümü, `NULL` | alt ağacın tamamı sessizce `NULL` olurdu |
| `text_pattern_ops` | Bitmap Heap Scan 46 · Seq Scan 77 | sorgu çalışır, sessizce tam tarama yapar |
| `Storage::url()` | iki markada AYNI adres | görseller merkez yoldan sunulur, izolasyon yok |

**Bitiş ölçütü:** panelden çok eksenli varyantlı ürün eklenebiliyor · vitrin ucu yalnızca
satılabilir olanları dönüyor · taslak ürünün ve `cost_price`'ın vitrine çıkmadığı testle
kanıtlanıyor · kategori taşınınca alt ağacın `path`'i doğru güncelleniyor · aynı
kombinasyondan ikinci varyant açılamıyor

#### 1C — Sepet

```
sepete ekle isteği
  │
  ├─ giriş yapılmış mı?
  │    evet  → customer_id ile sepet bul/oluştur
  │    hayır → session_token ile sepet bul/oluştur   ← misafir
  │
  ├─ varyant ekle / çıkar / adet değiştir
  │
  └─ fiyat CANLI okunur — cart_items'ta fiyat YOK
       marka fiyatı değiştirirse sepette de değişir

giriş yapıldığında
  → misafir sepeti müşterinin sepetine taşınır
  → aynı varyant iki sepette varsa: adetler TOPLANMAZ, büyük olan alınır
```

> **Neden toplanmıyor:** iki cihazda 2'şer ekleyen kullanıcının niyeti 4 almak değildir.
> Toplama sessizce yanlış siparişe yol açar; büyüğü almak en kötü ihtimalle sepette fazla
> bir adet bırakır ve kullanıcı onu görür.

---

##### Kararlar (araştırmayla doğrulandı — Shopify · Magento · WooCommerce)

**1C-K1 · Misafir kimliği: sunucu üretir, istemci `X-Cart-Token` başlığında taşır.**

```
POST /api/cart      → {"cart_token": "<64 karakter kriptografik rastgele>"}
sonraki istekler    → X-Cart-Token: <token>
```

> **Shopify'ın yolu farklı ve sebebi var:** onlarda sepet kimliği `<token>?key=<secret>`
> biçiminde İKİ PARÇALI ve birinci taraf **çerezde** duruyor. `key`, "alıcının özel
> verisini koruyan" gizli parça; `token` ise adreste/günlükte görünebiliyor.
>
> **Bizde ikiye bölmeye gerek yok:** token yalnızca BAŞLIKTA gidiyor, adreste ve
> günlükte görünmüyor — tek uzun rastgele dizgi aynı işi görüyor.
>
> **Çerez neden değil:** Shopify'ın vitrini kendi ürünü, alan adı ve ödeme aynı yerde.
> Bizde vitrin Faz 4'te ve teknolojisi seçilmedi (M-3); çerez seçersek API'yi henüz var
> olmayan bir istemciye bağlarız. Başlık hem SPA hem SSR için çalışıyor ve kimlik
> doğrulamayla aynı zihin modeli.
>
> ⚠️ Token **kriptografik rastgele** olmak zorunda. Ardışık/tahmin edilebilir olsaydı
> biri başkasının sepetini okurdu — adres yok ama ne aldığı görünür.

**1C-K2 · Satın alınamaz hâle gelen satır SİLİNMEZ, işaretlenir.**

```
satır durur + "artık mevcut değil"  →  ödeme adımı o satır kaldırılana kadar KİLİTLİ
```

> Sessizce silinseydi kullanıcı ne kaybettiğini bilmezdi. 1A.4'teki "sessiz yanlış
> yerine görünür eksik" kararının aynısı. Shopify ve BigCommerce de satırı işaretliyor.

**1C-K3 · Stok: eklerken YUMUŞAK, ödemede SERT.**

> Sepet **rezerve etmiyor** (rezervasyon 1D). Eklerken kontrol yardımcı olsun diye;
> bağlayıcı kontrol ödeme adımında. İkisi de `satinAlinabilirMi()` tek kapısından geçiyor
> (1B-K8).

**1C-K4 · Müşteri başına TEK aktif sepet — kısmi benzersiz indeks.**

> Olmasaydı "hangi sepet" sorusu doğar, iki cihazda iki sepet birikir ve birleştirme
> kuralı hangisine uygulanacağını bilemezdi.

**1C-K5 · Birleştirmeden SONRA da stok kontrolü koşar.**

> ★ Bu madde araştırmadan çıktı. **Magento adetleri TOPLUYOR**
> (`setQty($quoteItem->getQty() + $item->getQty())`) ve bu kayıtlı bir hata kaynağı
> (magento2 **#26981**: "guest cart `assignToCustomer` stok/uygunluk kontrolü YAPMADAN
> birleştiriyor"). **WooCommerce** ise birleştirmeyi bir ara tamamen kaldırmış, topluluk
> baskısıyla geri koymuş.
>
> Bizim kuralımız ikisinin arasında: **topla değil, büyüğü al** — ve birleştirme
> sonucunu da aynı stok kapısından geçir. Magento'nun atladığı adım tam olarak bu.

---

**Tablolar:** `carts` · `cart_items`
**Bitiş ölçütü:** misafir sepete ekleyebiliyor · giriş sonrası sepet birleşiyor ·
`customer_id` ve `session_token`'dan tam olarak birinin dolu olması veritabanı `CHECK`'i
ile zorlanıyor · ölü satır işaretli kalıyor · birleştirme stok kontrolünden geçiyor

#### 1D — Stok + Sipariş + Sevkiyat  ← **en zor blok**

```
CHECKOUT — orkestratör. Kendi iş yapmaz, sırayı yönetir.

 1  sepeti doğrula ............ fiyat değişti mi? varyant hâlâ aktif mi?
 2  adresi KOPYALA ............ siparişe yazılır, deftere bağlanmaz
 3  ┌─ BEGIN TRANSACTION
 4  │   SELECT ... FOR UPDATE .. satır kilidi (aşırı satış engeli)
 5  │   stok yeterli → rezervasyon oluştur (held, +15 dk)
 6  └─ COMMIT
 7  sipariş oluştur ........... payment_status = pending
    satırlar DONAR ............ başlık, sku, fiyat, KDV oranı kopyalanır
    sözleşme onayı yazılır .... terms_accepted_at + terms_version
 8  → 1E ödeme                  ⚠ ÖDEME TRANSACTION'IN DIŞINDA
 9  başarılı → rezervasyon committed · stok düş · payment_status = paid
    başarısız → rezervasyon released · sipariş cancelled

SEVKİYAT — sonradan, panelden. Bir sipariş birden çok pakette çıkabilir.

    fulfillment oluştur → hangi order_item, kaç adet
      └→ orders.fulfillment_status yeniden hesaplanır
           unfulfilled → partial → fulfilled
```

> ⚠️ **Ödeme neden transaction dışında:** dış servis yavaşlarsa veritabanı satırları
> dakikalarca kilitli kalır ve tüm mağaza donar.
>
> ⚠️ **Sözleşme onayı sonradan eklenemez:** marka metni değiştirdiğinde eski siparişler
> eski sürüme bağlı kalmalı. Sipariş bir fotoğraftır — o an yakalanmazsa bir daha
> yakalanamaz (`docs/domain-model.md` §7).

---

##### Kararlar (araştırmayla doğrulandı — Shopify envanter durumları)

**1D-K1 · Stok İKİ KOLON: `stock` (fiziksel) + `committed` (bağlanmış).**

```
variants.stock      = on_hand — fiziksel olarak elde olan
variants.committed  = siparişe bağlanmış, henüz sevk edilmemiş
available           = stock − committed        ← TEK SATIRDA, join yok
stock_reservations  = denetim izi + süre dolumu
```

> **Shopify'ın modeli birebir bu** ve orada da "her konumda **tutması gereken
> özdeşlik**" olarak tarif ediliyor: `on_hand − committed − damaged − safety_stock =
> available`. Bizde `damaged`/`safety_stock` yok (Faz 2+), o yüzden iki terim.
>
> **Neden rezervasyon tablosundan TOPLAMIYORUZ:** `available = stock − SUM(rezervasyon)`
> her ürün listesinde bir alt sorgu demek — 1B'de kaçındığımız N+1'in kardeşi. Sayı
> materyalleştiriliyor, tablo denetim izi olarak duruyor.
>
> **Neden doğrudan stoktan düşmüyoruz:** o zaman "fiziksel stok" bilgisi kaybolur ve
> çöken bir ödeme stoğu sessizce sızdırır.
>
> **Bedeli ve karşılığı:** iki yerde tutulan sayının tutarlı kalması gerekiyor.
> → **Gece denetimi (cron):** aktif rezervasyonların toplamı `committed` kolonuna eşit mi?
> Değilse uyarı. Materyalleştirilmiş sayacın bedeli budur ve ödenmesi gerekir; 1B.5'teki
> "SQL ikizi"ni testle bağlamakla aynı fikir, bu sefer çalışma anında.
>
> ⚠️ **1B'de verilen söz burada ödeniyor:** `satinAlinabilirMi()` ve `scopeSatinAlinabilir()`
> **birlikte** `stock − committed > 0` olacak. İkisini bağlayan test 1B.5'te yazıldı.

**1D-K2 · Sözleşme onayı: `legal_version_id` FK — `terms_version` varchar DEĞİL.**

> `domain-model.md` §7'de `terms_version varchar(20)` yazıyordu; o satır yasal metinler
> **`settings`'te dururken** yazılmıştı. 1A.4'te metinler sürümlü kendi tablosuna alındı.
>
> ```
> varchar "v3"        → metne ulaşamıyorsun; "v3" neydi? numaralandırma
>                        bozulursa kimse fark etmez
> legal_version_id FK → satırın kendisi, metin okunabilir
>                        sürüm satırı zaten SİLİNEMİYOR (tetik, 1A.4)
>                        ON DELETE RESTRICT ikinci savunma hattı
> ```
>
> ⚠️ Sipariş **GÖSTERİLEN** sürüme bağlanır, o anki güncele değil (1A.4'te kararlaştırıldı):
> müşteri 10:00:00'da sürüm 7'yi onayladıysa, 10:00:03'te sürüm 8 yayınlansa bile sipariş
> 7'ye bağlanır. "En son sürüm" demek, kişinin görmediği bir metne imza attırmaktır.
>
> **`domain-model.md` §7 düzeltildi.**

**1D-K3 · Rezervasyon 15 dakika; süresi dolanı ZAMANLANMIŞ GÖREV düşürür.**

> ⚠️ **Beşinci tuzağın ilk gerçek kullanımı.** Marka verisine dokunan görev
> `tenants:run` ile sarılmak zorunda (0.5'te ölçüldü). Doğrudan yazılan görev merkez
> bağlamda koşar ve **hiçbir şey yapmaz** — rezervasyonlar asla düşmez, stok sonsuza
> kadar bağlı kalır ve hata da vermez.

> ### ⟳ 1E araştırmasıyla GÜNCELLENDİ — tek süre YETMİYOR
>
> 15 dakika, "müşteri ödeme sayfasında oyalanıyor" varsayımıyla seçilmişti. 1E
> araştırması varsayımın **yarısının yanlış** olduğunu gösterdi: süre bizim
> elimizde değil, sağlayıcının takviminde.
>
> ```
> iyzico webhook takvimi          bizim rezervasyonumuz
> ilk bildirim   10-15 saniye  →  ✓
> 1. tekrar      +15 dakika    →  TAM SINIRDA
> 2. tekrar      +30 dakika    →  rezervasyon ÖLDÜ
> 3. tekrar      +45 dakika    →  ÖLDÜ
> ```
>
> Bildirim ilk seferde kaçarsa (deploy anı, kısa kesinti, 5xx) ikinci deneme
> rezervasyonun öldüğü dakikaya denk geliyor. Kıyas: **WooCommerce varsayılanı 60
> dakika** — bizim dört katımız; ve o bile PayPal'da yetmediği için "sipariş erken
> iptal edildi" bilinen bir sorun.
>
> **Süreyi 60'a çıkarmak yanlış çözüm:** ödemeye hiç başlamamış terk edilmiş sepet
> stoğu bir saat rehin tutardı. Değişen şey süre değil, **süreyi neyin belirlediği:**
>
> ```
> rezervasyon oluştu           15 dk   sepet bekliyor, süreç BİZDE
>      │
>      └─ ödeme BAŞLATILDI  →  60 dk   süreç DIŞARIDA, geri alamayız
>                                      temizlik görevi buna DOKUNMAZ
> ```
>
> Yani rezervasyonun bir **durumu** oluyor: `held` (sepet) → `paying` (ödeme yolda).
> Uygulaması 1E.2'de; `StockService` ile `stok:rezervasyon-temizle` ikisi de etkileniyor.
>
> ⚠️ Bu değişiklikten sonra bile "para geldi, rezervasyon ölmüş" senaryosu
> **imkânsız olmuyor** — yalnızca nadirleşiyor. 60 dakikayı da aşan bildirim
> gelebilir. O yüzden 1E-K3'teki karşılama davranışı yine de gerekli.

**1D-K4 · Sipariş numarası: `TM-2026-000123` — marka içinde artan.**

> Tahmin edilebilir olması sorun değil: siparişi görüntülemek zaten kimlik doğrulaması
> istiyor (misafirde e-posta + numara). Yıl öneki, markanın kendi muhasebesinde ayırt
> etmesini kolaylaştırıyor.

**1D-K5 · Satır kilidi: `SELECT … FOR UPDATE`; ödeme transaction'ın DIŞINDA.**

```
kilitsiz:  A okur 1 · B okur 1 · ikisi de committed=1  → AŞIRI SATIŞ, hatasız
kilitli:   A kilitler · B BEKLER · A commit · B okur 0 → reddedilir ✓
```

> Ödemenin neden dışarıda olduğu zaten yukarıda: dış servis yavaşlarsa satırlar
> dakikalarca kilitli kalır ve tüm mağaza donar.

**1D-K6 · `lock_timeout = 3s`. Kilit beklemesi sonsuz DEĞİL.**

> PostgreSQL varsayılanda kilit için **sonsuza kadar** bekler. Bir işlem takılırsa
> arkasındaki bütün ödeme istekleri asılı kalır ve mağaza donmuş görünür.
>
> ```
> sonsuz bekle   stok doğru ama takılan tek işlem sırayı kilitler
> NOWAIT         hızlı, ama meşgul anlarda müşteri boşuna reddedilir
> lock_timeout   kısa çekişmeyi bekler, uzun takılmayı keser      ← seçilen
> ```
>
> Zaman aşımında sipariş **oluşmuyor**, müşteri tekrar deniyor. Aşırı satış riski yok:
> kilit kurulamadan hiçbir şey yazılmıyor.

---

##### Kilitleme — hangi kilit nerede (soru üzerine netleştirildi)

Tasarımda **iki ayrı kilit** var ve farklı işler yapıyorlar:

```
1  SATIR KİLİDİ (FOR UPDATE)        süre: mikrosaniye
   korur: committed sayacının okunup yazılması arasındaki an
   yaşar: PostgreSQL satırında

2  REZERVASYON (15 dk)              süre: istekler ARASINDA
   korur: müşteri ödeme sayfasındayken stoğun kapılmamasını
   yaşar: stock_reservations satırında
```

> **Uygulama kopyası sayısı satır kilidini etkilemiyor.** Kilit PHP belleğinde değil,
> paylaşılan kaynağın kendisinde — kaç konteyner/sunucu olursa olsun hepsi aynı
> PostgreSQL satırına gidiyor ve orada sıraya giriyorlar. M-2'nin "tek veritabanı"
> kararı burada işi kolaylaştırıyor: dağıtık transaction (2PC) sorunu hiç doğmuyor.
>
> **Dağıtık kilit BAŞKA yerlerde gerekecek** — ve gerekeceği yerler belli:
>
> | Durum | Çözüm | Nerede |
> |---|---|---|
> | Zamanlanmış görev birden çok düğümde koşuyor | `withoutOverlapping()` (cache/Redis kilidi) | **1D.5** |
> | Dış ödeme servisine çift istek | kilit değil, **idempotanslık anahtarı** | **1E** |
> | Stok önbelleğe alınırsa doğruluk kaynağı Redis olur | dağıtık kilit şart olur | **yapılmıyor** |
> | Mimari ayrı servislere bölünürse | dağıtık kilit / saga | M-2 bunu dışarıda bırakıyor |
>
> **Ölçekte kırılacak iki şey, şimdiden not:** okuma kopyası eklenirse `FOR UPDATE`
> ana sunucuya gitmek **zorunda** (yanlış yönlendirilirse kilit hiç kurulmaz, aşırı
> satış sessizce geri gelir) · pgBouncer `transaction` modunda `search_path` başka
> isteğe geçiyor (M-2.4/5) — ikisi birlikte düşünülmeli.

---

**Tablolar:** `orders` · `order_items` · `fulfillments` · `fulfillment_items` ·
`stock_reservations`
**Bitiş ölçütü:** uçtan uca test — misafir sipariş verir, stok düşer, sipariş **kısmi**
sevk edilir, `fulfillment_status` doğru hesaplanır · eşzamanlı iki siparişte aşırı satış
olmadığı testle kanıtlanır · `committed` ile rezervasyon toplamının tutarlılığı denetimle
doğrulanıyor

---

## ✅ FAZ 1D TAMAMLANDI

```
1D.1 stock + committed · satılabilirlik ikizleri (PHP + SQL)
1D.2 StockService — satır kilidi, sabit kilit sırası, lock_timeout 3s
1D.3 OrderTotals + CheckoutService — sipariş doğuyor, stok bağlanıyor
1D.4 FulfillmentService — kısmi sevk, fulfillment_status TÜRETİLİYOR
1D.5 zamanlanmış görevler — rezervasyon temizliği + sayaç denetimi
1D.6 panel/vitrin uçları · uçtan uca test · iki kiracıda gerçek HTTP

233 test · lint · analyse (seviye 8) — hepsi yeşil
```

**1D'nin ölçerek öğrettikleri:**

| Ölçüm | Sonuç | Olmasaydı |
|---|---|---|
| `bcdiv` ile vergi | kesme yüzünden **bir kuruş** sapma | her siparişte kuruş kayması, muhasebe tutmaz |
| `lockForUpdate()` silindi | **hiçbir test kırılmadı** | aşırı satış korumasız kalır, sessizce |
| Kolon varsayılanı (`fulfillment_status`) | modele ulaşmıyor — **dördüncü kez** | yeni sipariş `null` durumla doğar |
| Larastan + döngüyle üretilen kolon | okuyamıyor | `shipping_*` alanları elle yazıldı |

**★ 1D.6'da iki kiracıda gerçek HTTP doğrulaması İKİ ÖLÜ UÇ buldu — 232 testin
hiçbiri görmemişti:**

```
vitrin ürün detayı              →  varyant `uuid`'si YOK
                                   ama /cart/items onu ZORUNLU istiyor

vitrinde yasal metin ucu        →  HİÇ YOKTU
                                   ama /checkout `legal_version_id` ZORUNLU istiyor
```

> **Neden testler yakalamadı:** testler uca gidiyordu ama uca **verdiği değeri
> modelden okuyordu** (`$varyant->uuid`). Yani "istemci bu değeri nereden bulacak"
> sorusu hiç sorulmamıştı. Gerçek müşteri için sipariş vermek **imkânsızdı.**
>
> **Kural — bundan sonra:** uçtan uca testte bir isteğin gövdesine giren her kimlik,
> bir önceki **uçtan** gelmeli. Modelden okunan kimlik testi yeşil tutar, akışı
> doğrulamaz.
>
> Düzeltme: `variants[].uuid` vitrin yükünde açıldı (`id` değil — sıralı sayı katalog
> büyüklüğünü sızdırır) · `GET /api/legal` + `GET /api/legal/{tur}` eklendi (yalnızca
> yayınlanmış sürüm; taslak çıkmıyor, hiç yayınlanmamışsa 404).

**İki kiracıda doğrulandı:** iki markada da `TM-2026-000001` üretildi (sıralar ayrı
şemalarda, çakışma yok) · her panel yalnızca kendi siparişini gördü.

#### 1E — Ödeme

> ⚠️ **1A.4'ten gelen zorunluluk: sipariş, onaylanan sözleşme SÜRÜMÜNE bağlanacak.**
>
> ```
> 10:00:00  müşteri ödeme sayfasında, sürüm 7 metnini OKUDU ve onayladı
> 10:00:03  marka sürüm 8'i yayınladı
> 10:00:05  "siparişi tamamla" isteği geldi   →  sipariş hangi sürüme bağlanmalı?
>
>           7'ye. Çünkü müşterinin ONAYLADIĞI oydu.
>           "yayındaki en son sürüm" demek, kişinin görmediği bir metne
>           imza attırmak olur.
> ```
>
> Yani ödeme formu, gösterdiği `legal_document_versions.id` değerini yanında taşır ve
> sipariş **gösterilen** sürüme bağlanır, o anki güncel olana değil.
> `orders.legal_version_id` → `ON DELETE RESTRICT` (sürüm satırı zaten silinemiyor,
> bu ikinci savunma hattı).

```
        ┌──────────────────────────────────┐
        │  PaymentProvider   (arayüz)      │
        │  ─────────────────────────────   │
        │  charge()        refund()        │
        │  verifyWebhook()                 │
        └───────┬──────────────────┬───────┘
                │                  │
     FakePaymentProvider     IyzicoProvider / PayTR
     Faz 1 — gerçek para     sonra takılır
     yok, "başarılı" der     ÜSTTEKİ KOD DEĞİŞMEZ
```

> ⚠️ **İki kural pazarlık dışı:**
> · tutar **sunucuda** `orders.grand_total`'dan yeniden üretilir — istemciden gelene asla
>   güvenilmez
> · webhook **imzası doğrulanmadan** sipariş ödendi sayılmaz — yoksa herkes sahte istek
>   atıp bedava sipariş oluşturur
>
> Kart verisi hiçbir zaman sisteme girmez. Sağlayıcı anahtarları `settings`'te şifreli.

---

##### 1E'nin asıl şekli — ödeme BİZİM sürecimizde değil

Alışkanlık "sağlayıcıyı çağır, cevabı işle" der. Türkiye'de kartla ödeme öyle
çalışmıyor: **3D Secure zorunlu** ve müşteri sürecin ortasında bizden çıkıyor.

```
MÜŞTERİ           BİZ                    SAĞLAYICI            BANKA
   │               │                         │                  │
   │─"öde"────────▶│                         │                  │
   │               │──ödeme başlat──────────▶│                  │
   │               │◀─"şu adrese yolla"──────│                  │
   │◀──yönlendir───│                         │                  │
   │                                                            │
   │══════════ BİZ ARTIK SÜREÇTE YOKUZ ═══════════════════════▶ │
   │              (müşteri SMS kodunu bankaya giriyor)          │
   │                                                            │
   │◀═════════════════════════════════════════════════════════ │
   │               │                         │                  │
   │──geri dön────▶│  ① tarayıcı geri geldi  │                  │
   │               │◀──webhook───────────────│  ② sunucu haberi │
```

**1E'nin bütün zorluğu bu iki okta.** İkisi de "ödeme oldu" diyor; hangisine inanılır?

---

##### 1E-K1 · GERÇEK ② webhook'tur. ① yalnızca ekran çevirir.

> ① müşterinin tarayıcısından geliyor — adres çubuğuna `?status=success` yazan herkes
> üretebilir. ② sağlayıcının sunucusundan ve **imzalı** geliyor.
>
> ⚠️ Bu bizim tahminimiz değil, **iyzico kendi belgesinde yazıyor:** geri dönüş
> yönlendirmesi ödemenin tamamlandığının güvenilir göstergesi *değildir* — kullanıcı o
> ekrana hiç ulaşmayabilir, sekmeyi kapatabilir, geri tuşuna basabilir, bağlantısı
> kopabilir. Callback **kullanıcıyı bilgilendirmek** içindir.
>
> Sonuç: callback ucu **hiçbir şey kaydetmez**. "Teşekkürler" ya da "bekleniyor"
> gösterir, o kadar. Tek yazan yer webhook.

##### 1E-K2 · Sipariş ödemeden ÖNCE doğar. (1D'de zaten öyle — burada gerekçesi)

```
SHOPIFY                         MAGENTO · WOOCOMMERCE · BİZ
"checkout" nesnesi              sipariş HEMEN doğuyor (pending)
sipariş ödeme başarılı          ödeme sonucu yalnızca DURUMU değiştirir
  olunca DOĞAR
```

> Shopify'ın modelinin belgelenmiş bedeli var: müşteri ödedikten sonra "teşekkürler"
> sayfasına ulaşamadan sekmeyi kapatırsa sipariş **hiç doğmuyor**, kayıt "terk edilmiş
> sepet" olarak kalıyor. Satıcı topluluğunda tam bu başlıkla açılmış konular var:
> *ödeme onaylandı ama terk edilmiş sepette görünüyor.* Para çekilmiş, sipariş yok.
>
> Bizde webhook geldiğinde bağlanacağı satır **zaten var**. Bu yüzden 1D'nin
> `pending` doğan siparişi değişmiyor.

##### 1E-K3 · Webhook "EN AZ BİR KEZ" gelir. Tekrarı veritabanı reddeder.

```
webhook #1  →  stok düş, paid yap        ✓
webhook #2  →  stok DÜŞ, paid yap        ✗ iki kez düştü
webhook #3  →  stok DÜŞ, paid yap        ✗ üç kez        — hepsi SESSİZ
```

> Tekrar teslim **arıza değil tasarım**: Stripe'ın ifadesiyle teslim "en az bir kez",
> aynı olay kimliği iki kez gelebilir. iyzico somut takvim veriyor: ilk bildirim
> **10-15 saniye** sonra, sunucu 2xx dönmezse **15 dakikada bir, 3 kez daha.**
>
> Sektörün ortak çözümü tek cümle: *sağlayıcının olay kimliğini idempotanslık anahtarı
> yap ve **veritabanı UNIQUE kısıtıyla** koru* — uygulamada "önce bir bakayım işledim
> mi" kontrolü yarış koşulunu çözmez, kısıt çözer.
>
> ```
> payments (provider, provider_ref)  UNIQUE   ← ikinci kayıt DB'de reddedilir
> ```
>
> Bu, projenin tekrarlayan deseni: **unutmayı imkânsız kıl.**

##### 1E-K4 · Müşteri iki kez "öde"ye basarsa: idempotanslık anahtarı

> UNIQUE burada yetmiyor — sağlayıcı iki **farklı** işlem numarası üretir, ikisi de
> geçerlidir, müşterinin parası iki kez çekilir.
>
> Çözüm sağlayıcıya *giderken* taşınan anahtar: aynı anahtarla ikinci istek gelirse
> sağlayıcı yeni çekim yapmaz, ilkinin sonucunu döndürür. **Anahtar = sipariş
> numarası** (`TM-2026-000123`) — marka içinde tekil ve zaten üretilmiş.
>
> ⚠️ Bu borç 1D'nin kilitleme tartışmasında not edilmişti; burada kapanıyor.

##### 1E-K5 · ★ "Para geldi, mal yok" → KABUL ET, İŞARETLE, markaya sor

```
10:00  sipariş verildi, 3 tişört rezerve
11:05  rezervasyon öldü (60 dk), stok serbest
11:06  başka müşteri o 3 tişörtü aldı
11:08  webhook: "ödeme başarılı"        ← PARA ÇEKİLDİ, MAL YOK
```

> 1D-K3 güncellemesi bu senaryoyu **nadirleştiriyor ama yok etmiyor.** Üç seçenek:
>
> ```
> A  ödemeyi reddet + iade et      müşteri parasını 3-5 günde alır, kötü deneyim
> B  kabul et, stok EKSİYE düşsün  marka gönderemeyeceği siparişi görür
> C  kabul et + "stok bekliyor"    marka karar verir: tedarik mi, iade mi   ← SEÇİLEN
> ```
>
> **Sektör de C yapıyor.** Shopify eksi stoğa izin veriyor, sipariş normal doğuyor,
> satırlar stok gelene kadar sevk edilmemiş kalıyor. Satıcı cephesinden bakıldığında
> bu "en kötü operasyonel arızalardan biri" olarak tarif ediliyor — ödenmiş ama
> karşılanamayan siparişle kalıyorsun: iade et, geciktir, ya da zararına tedarik et.
>
> ⚠️ **Ama Shopify'ın asıl uyarısı şu: sorun eksi stoğa izin vermek değil, HABER
> VERMEDEN izin vermek.** Açık mesaj yoksa "bu ayar çözdüğünden fazla sorun yaratır"
> deniyor.
>
> Bu yüzden C'nin şartı var: durum yalnızca veritabanında değil **panelde görünür bir
> uyarı** olmalı. Marka satırı açmadan göremiyorsa C sessiz arızaya döner — tam olarak
> bu projede kovaladığımız şeye.

##### 1E-K6 · Sahte sağlayıcı GERÇEK akışı taklit eder

> "Başarılı" diyen bir sınıf yazmak kolay, ama 1E'nin **hiçbir zorluğunu** sınamaz:
> yönlendirme yok, gecikme yok, tekrar yok, imza yok. O sahte sağlayıcıyla yeşil olan
> testler iyzico takıldığı gün hiçbir şey söylemez.
>
> `FakePaymentProvider` şunları üretecek: yönlendirme adresi · **gecikmeli** webhook ·
> **aynı olayı birden çok kez** gönderme · imzalı ve imzasız istek · başarısız sonuç.

##### Kiracılık: webhook hangi markaya ait?

```
sağlayıcı  →  POST /webhooks/payment  →  hangi şema?
```

> ⚠️ 0.5'te ölçtüğümüz kiracılık tuzağının yeni yüzü: **yanlış şemaya yazılan ödeme
> hata vermez.** A markasının tahsilatı B'nin defterinde görünür.
>
> Uç, alan adından çözülüyor (`marka-a.localhost/webhooks/payment`) — kiracı
> tanımlaması zaten alan adı üzerinden (M-2). Sabit tek adrese yollayan sağlayıcı
> çıkarsa marka ayrımı yükün içindeki bir alandan yapılır; o zaman **merkez şemada**
> eşleme tablosu gerekir. Faz 1'de gerek yok, notu duruyor.
>
> ⚠️ Bu uç **kimlik doğrulamasız** olmak zorunda — sağlayıcı bizim token'ımızı bilmez.
> Tek koruma **imza**: iyzico `X-IYZ-SIGNATURE-V3`, HMAC-SHA256, gizli anahtar +
> belirli alanlar sırayla. Gizli anahtar `settings`'te şifreli, marka başına ayrı (§4).

---

##### Bu kararların dayandığı kaynaklar

> Beş karar da tahminle değil **okunarak** verildi; biri (1D-K3) araştırma yüzünden
> değişti. Kaynaklar sonradan doğrulanabilsin diye burada:
>
> · iyzico — [Webhook](https://docs.iyzico.com/ek-servisler/webhook) (imza, 10-15 sn +
>   15 dk × 3 tekrar) · [3DS entegrasyonu](https://docs.iyzico.com/odeme-metotlari/api/3ds/3ds-entegrasyonu)
>   (callback güvenilir gösterge değildir)
> · ikas — [Orders API](https://ikas.dev/docs/api/admin-api/orders): `orderPaymentStatus`
>   ile `orderPackageStatus` **ayrı alanlar** (iki eksen kararımızı doğruluyor;
>   rezervasyon alanı yayınlamıyorlar)
> · Shopify — [terk edilmiş sepetler](https://help.shopify.com/en/manual/promoting-marketing/create-marketing/abandoned-checkouts) ·
>   [ödeme onaylandı ama terk edilmiş sepette](https://community.shopify.com/t/payment-gateway-issue-payment-completed-but-order-is-in-abandoned-checkouts/40400) ·
>   [stok tükendiğinde satışa devam](https://help.shopify.com/en/manual/products/inventory/getting-started-with-inventory/selling-when-out-of-stock) ·
>   [aşırı satış sorunu](https://www.lasyncro.com/blog/shopify-overselling-problem) ·
>   [envanter rezervasyonlarını ölçeklemek](https://shopify.engineering/scaling-inventory-reservations)
>   ("ACID across reserve and claim" — Redis'ten MySQL'e geri döndüler; bizim tek
>   veritabanı kararımızın aynısı)
> · WooCommerce — [bekleyen ödeme ve tutulan stok](https://krokedil.com/pending-payment-orders-and-held-stock/)
>   (varsayılan 60 dk) · [#43593 siparişler erken iptal ediliyor](https://github.com/woocommerce/woocommerce/issues/43593)
>   — kök sebep **GMT ile yerel saat karışması** (WordPress site saat dilimi ayarı,
>   müşterinin cihazı değil); HPOS siparişleri GMT'de saklıyor ama iptal görevi
>   `current_time()` ile yerel saat kullanıyordu. Brisbane'de (UTC+10) 60 dakika
>   dolmadan iptal oluyordu.
>
>   ⚠️ **Bu okundu ve BİZDE DE ÖLÇÜLDÜ — vardı.** Laravel `now()`'ı ofissiz metin
>   olarak bağlıyor, PostgreSQL onu oturumun `TimeZone`'una göre yorumluyor:
>
>   ```
>   15 dk sonra dolacak rezervasyon      oturum UTC              → yaşıyor  ✓
>   AYNI satır, AYNI an                  oturum America/New_York → ÖLMÜŞ    ✗
>   ```
>
>   Sunucu varsayılanı zaten UTC'ydi, yani **tesadüfen** doğruyduk; bunu sağlayan
>   bir şey yoktu. Kapatıldı: `config/database.php`'de `'timezone' => 'UTC'`
>   (ayarın gerçekten oturumu sürdüğü New_York'a çevrilerek kanıtlandı) +
>   `tests/Feature/ZamanDilimiTest`. Kural `CLAUDE.md`'ye eklendi.
> · Stripe deseni — [idempotanslık, tekrar, eşzamanlılık](https://www.snowinch.com/en/blog/stripe-webhook-idempotency-duplicates) ·
>   [webhook tekilleştirme](https://www.hooklistener.com/learn/webhook-idempotency-and-deduplication)

---

##### Madde listesi

```
1E.1  PaymentProvider arayüzü + FakePaymentProvider
      payments tablosu · (provider, provider_ref) UNIQUE
      sağlayıcı anahtarları settings'te ŞİFRELİ (marka başına ayrı)

1E.2  Rezervasyon durumu: held → paying (1D-K3 güncellemesi)
      ödeme başlayınca süre 15 dk → 60 dk
      stok:rezervasyon-temizle `paying` olana DOKUNMAZ

1E.3  Ödeme başlatma ucu — POST /api/orders/{uuid}/pay
      ⟳ PLAN {no} diyordu, UUID'ye çevrildi: sipariş numarası tahmin
        edilebilir (1D-K4) ve o karar "görüntülemek kimlik doğrulaması
        ister" varsayımına dayanıyordu — misafir siparişinde yok.
        Numarayla ardışık deneyen biri başkasının ödemesini başlatırdı.
      tutar SUNUCUDA grand_total'dan üretilir
      idempotanslık anahtarı = sipariş numarası
      dönüş adresi de SUNUCUDA üretilir (açık yönlendirme)
      dönüş: sağlayıcının yönlendirme adresi

1E.4  Webhook ucu — imza doğrulama + idempotanslık
      imzasız/yanlış imzalı istek REDDEDİLİR (kayıt bile açılmaz) → 401
      odemeBasarili / odemeBasarisiz burada çağrılır — TEK yazan yer
      ⟳ "iş KUYRUĞA atılır" YAPILMADI. Gerekçe: yapılan iş birkaç satır
        güncellemesi, aynı veritabanında ve hızlı. Kuyruk üç şey
        eklerdi — kiracı bağlamını taşıma zorunluluğu (M-2.4), hatanın
        görünmez hâle gelmesi ve sağlayıcıya "işlendi" demişken işin
        sonradan düşmesi. Senkron işleyip 2xx dönmek daha dürüst:
        iş bitmeden 2xx demiyoruz, düşerse sağlayıcı zaten tekrar deniyor.
        Kuyruk, işin yavaşladığı gün eklenir (e-posta, fatura, olay kaydı).

1E.5  Callback ucu — HİÇBİR ŞEY KAYDETMEZ
      siparişin o anki durumunu okur, ekran çevirir
      webhook henüz gelmediyse "ödemeniz işleniyor" gösterir

1E.6  Blok kapanışı — panel ödeme görünümü · uçtan uca · iki kiracıda gerçek HTTP
      "stok bekliyor" uyarısı panelde GÖRÜNÜR (1E-K5'in şartı)
```

---

## ✅ FAZ 1E TAMAMLANDI

```
1E.1 PaymentProvider arayüzü · FakePaymentProvider · payments tablosu
1E.2 rezervasyona ödeme aşaması: held 15 dk → paying 60 dk
1E.3 ödeme başlatma ucu — tutar/anahtar/dönüş adresi hepsi SUNUCUDA
1E.4 webhook — imza · eşleşme · tekrar; siparişi değiştiren TEK yer
1E.5 dönüş ekranı — HİÇBİR ŞEY YAZMIYOR
1E.6 stok açığı işareti · uçtan uca ödemeli akış · iki kiracı

290 test · lint · analyse — hepsi yeşil
```

**1E'nin ölçerek öğrettikleri:**

| Ölçüm | Sonuç | Olmasaydı |
|---|---|---|
| `hash_hmac(..., '')` | boş anahtarla **geçerli imza** üretiyor | doğrulama "çalışır" görünür, hiçbir şey korumazdı |
| Test markası ≠ gerçek marka | `DefaultSettings` testte hiç koşmuyordu | testler canlıda olmayan bir marka biçimini sınıyordu |
| `SoftDeletes` + `firstOrFail()` | varyant silinince ödeme **işlenemiyor** | webhook 404, sağlayıcı 3 kez dener, tahsilat kayıp |
| `'549.7'` vs `'549.70'` | düz `!==` bunları farklı görüyor | geçerli ödemeler tutar uyuşmazlığı sanılırdı |

**★ 1E.6'da test GERÇEK BİR HATA buldu:** marka, ödemesi yolda olan bir
siparişin varyantını katalogdan kaldırınca `StockService::kilitle()`
`firstOrFail()` ile patlıyordu — webhook 404 dönüyor, sağlayıcı üç kez
deniyor, üçü de düşüyor ve **tahsilat hiç kaydedilmiyordu.** Para çekilmiş,
sistemde iz yok. Kapanış yolları (`kesinlestir` / `serbestBirak`) artık
silinmiş varyantı da kilitliyor; rezervasyon **açma** yolu ise sıkı kalıyor.

> Katalogdan kaldırmak bir **vitrin** kararı; yolda olan siparişin
> muhasebesini bozmamalı.

**1E-K5 kapatıldı:** rezervasyonu ölmüş bir siparişe ödeme gelirse sipariş
**kabul ediliyor** ama `orders.stock_shortfall` işaretleniyor ve panel
listesinde **en üstte** görünüyor. Sıralama kararının sebebi: tarihe göre
sıralansaydı yoğun bir günde uyarı üçüncü sayfaya düşer, pratikte görünmez
olurdu — Shopify'ın uyarısı zaten "eksi stoğa izin vermek değil, **haber
vermeden** izin vermek" idi.

**İki kiracıda doğrulandı:** her iki markada da sipariş → ödeme başlat →
sahte `success` ile dönüş (`processing`) → webhook (`paid`) → tekrar
(`already_processed`) → dönüş (`success`). marka-a'da rezervasyon bilerek
öldürüldü; paneli **⚠️ STOK AÇIĞI** işaretiyle en üstte gösterdi,
marka-b temiz kaldı.

**Tablolar:** `payments` (+ `stock_reservations.status` genişliyor)

**Bitiş ölçütü:** sahte sağlayıcıyla ödeme uçtan uca çalışıyor · **aynı webhook üç kez
gönderilince stok bir kez düşüyor** · imzasız webhook reddediliyor · başarısız ödemede
rezervasyon serbest bırakılıyor · callback'e sahte `success` gönderilince sipariş
ödenmiş sayılmıyor · ödeme sürerken rezervasyon temizliği o satıra dokunmuyor · para
gelip stok kalmadığında sipariş kabul edilip **panelde uyarı olarak görünüyor** · iki
kiracıda doğrulanıyor

#### 1E.7 — iyzico (gerçek sağlayıcı)

> ⟳ **PLAN DEĞİŞİKLİĞİ.** Gerçek sağlayıcı Faz 5'e yazılmıştı; öne çekildi.
> Gerekçe: sandbox hesabı açıldı, arayüz zaten iyzico'nun akışına göre
> biçimlendirilmişti (1E.1) ve sahte sağlayıcı onun imza düzenini taklit
> ediyor. Beklemek, tasarımın doğruluğunu **sınamadan** bir blok daha
> ilerlemek olurdu.

**1E-K7 · Kart verisi BİZE DEĞMEZ — barındırılan ödeme formu.**

```
A  Doğrudan API   kart numarası bizim sunucumuzdan geçer → PCI-DSS yükü
B  Ödeme formu    kart bilgisi iyzico'nun sayfasında girilir   ← SEÇİLEN
```

> Zaten karar verilmişti (`domain-model.md` §10: "kart verisi hiçbir zaman
> sisteme girmez"); burada teyit ediliyor. Bedeli: ödeme ekranının görünümü
> üzerinde tam denetimimiz yok. Karşılığı: kart verisi hiç görmediğimiz için
> PCI kapsamı en dar hâlinde kalıyor.

**1E-K8 · Eşleşme anahtarı: ÖDEME DENEMESİNİN uuid'si — sipariş numarası DEĞİL.**

> iyzico başlatırken verdiğimiz `conversationId`'yi bildirimde geri
> döndürüyor. Oraya sipariş numarası konabilirdi ama iki sorun var:
>
> ```
> TM-2026-000123    tahmin edilebilir (1D-K4)
>                   bir siparişin BİRDEN ÇOK denemesi olabilir
>                     kart reddedildi → müşteri başka kartla denedi
>                   → iki deneme aynı kimliği taşırdı
> ```
>
> `payments.uuid` ikisini de çözüyor: tahmin edilemez ve deneme başına tekil.
> `payments.provider_ref` ise iyzico'nun kendi ödeme kimliği olarak kalıyor —
> UNIQUE kısıtı onun üzerinde (1E-K3).

**1E-K9 · Tutar İKİNCİ ÇAĞRIYLA doğrulanıyor.**

> Sahte sağlayıcının bildiriminde tutar vardı ve karşılaştırıyorduk (1E.4).
> iyzico'nun bildiriminde **tutar yok** — yalnızca ödeme kimliği ve durum.
>
> Seçenekler: tutar doğrulamasından vazgeç, ya da bildirimi alınca iyzico'ya
> "bu ödeme neydi" diye sor. **İkincisi seçildi.**
>
> ⚠️ Bu, 1E-K1 ile çelişmiyor. Orada "callback'e sorma" denmişti; buradaki
> çağrı callback'ten değil, **imzası doğrulanmış webhook'tan** sonra ve
> **doğrulama** amaçlı. Tek doğruluk kaynağı yine sağlayıcının sunucusu.
>
> Bedeli: webhook işleme artık bir dış çağrı içeriyor, yani yavaşlayabiliyor.
> Çağrı düşerse 2xx dönmüyoruz ve sağlayıcı tekrar deniyor — doğru davranış.

**1E-K10 · Sandbox bize webhook ATAMAZ — geçici genel adres gerekiyor.**

```
iyzico sunucusu  ──✗──>  marka-a.localhost   (internetten erişilemez)
iyzico sunucusu  ──✓──>  <geçici-adres>      → tünel → yerel makine
```

> Karar: **ngrok** ile geçici adres. Gerçek alan adına geçildiğinde silinecek;
> kalıcı bir bağımlılık değil.
>
> ⚠️ Tünel adresi **kiracı alan adı olarak** kayıtlı olmak zorunda: webhook
> ucu kiracıyı alan adından çözüyor (1E.4). Kayıtlı değilse istek 404 alır ve
> tahsilat hiç işlenmez.

**1E-K11 · Sağlayıcı anahtarları PANELDEN girilir.**

> 1E.1'de anahtarlar `settings`'e şifreli konuyor ama **panelden girme yolu
> yok**; sandbox anahtarlarını elle veritabanına yazdık. Canlıda her marka
> kendi hesabını kendisi tanımlayacak.
>
> ⚠️ Ayar ucu şu an serbest biçimli: `iyzico_api_key` yerine `iyzico_api`
> yazan marka **hata almaz**, anahtar hiçbir zaman okunmayan bir yere yazılır
> ve ödeme "yapılandırılmış" görünürken çalışmaz. Bu yüzden her sağlayıcı
> **ihtiyaç duyduğu anahtarları bildirecek** ve panel eksikleri gösterecek —
> `StoreReadiness` deseninin aynısı.

**1E-K12 · İmzasız bildirim: gövdesine GÜVENME, SAĞLAYICIYA SOR.**

> ⚠️ Gerçek sandbox'ta ölçüldü: iyzico `X-Iyz-Signature` başlığını **boş**
> gönderiyor. İmza özelliği hesapta ayrıca aktive ediliyor
> (`entegrasyon@iyzico.com`); panelden açılan bir ayar değil.
>
> 1E-K1 "imzasız bildirim reddedilir" diyordu ve o kural **doğru** — ama
> reddettiğimiz sürece iyzico ile tek ödeme bile işlenemiyor.
>
> ```
> ESKİ GÜVEN         mesaja güven      "imza tutuyorsa içindekine inanırım"
> YENİ GÜVEN         KAYNAĞA güven     "referansı al, ne olduğunu SOR"
> ```
>
> Bildirim artık bir **kapı zili**: "bir şey oldu, bak" diyor. Ne olduğunu
> söyleme yetkisi yok — gövdesindeki `status` alanına hiç bakılmıyor.
>
> **Neden güvenli:** sahte bildirim atan birinin yapabileceği tek şey bize
> *zaten bizde olan* bir referansı hatırlatmak. Cevabı yine sağlayıcı
> veriyor, saldırgan değil.
>
> **Bedeli:** her bildirimde bir dış çağrı. Düşerse 2xx dönmüyoruz ve
> sağlayıcı tekrar deniyor — doğru davranış.
>
> ⚠️ **Genel gevşetme DEĞİL, sağlayıcı başına beyan edilen yetenek**
> (`QueryablePaymentProvider`). Sahte sağlayıcı imzalıyor, sorgulanamıyor
> ve imzasız bildirimi **reddediyor**. Genel olsaydı, imzalayan bir
> sağlayıcının imzası bir gün hiç gelmemeye başlasa fark etmezdik.
>
> ⚠️ **İmza VARSA yine doğrulanıyor (A + B birlikte).** Bozuk imza,
> imzasızdan *daha kötü* bir işaret: ya anahtar değişmiş ya biri kurcalıyor.
> iyzico imzayı açtığında tasarım kendiliğinden iki katmanlı oluyor.
>
> **Bu karar K9'u da kapatıyor:** sorgu hem tutarı hem `paymentStatus`'ü
> veriyorsa asıl olan **sorgudur**. ⚠️ Ölçüldü: 3DS'i geçemeyen bir ödemede
> bile `paidPrice` doğru dönüyor — tutara bakıp "ödendi" demek yanlış olurdu.

```
1E.7.1  panel ödeme ayarları — sağlayıcı seçimi + anahtar doğrulama (K11)
1E.7.2  IyzicoProvider — başlatma · imza · bildirim çözme · tutar sorgusu
1E.7.3  ngrok tüneli + gerçek sandbox'a karşı uçtan uca koşu
```

##### Tünel kurulumu — adım adım

> Tünel **compose servisi** olarak duruyor; yerel makineye hiçbir şey
> kurulmuyor. ⚠️ `profiles: ["tunel"]` sayesinde **normal `up` ile
> açılmıyor** — açılsaydı geliştirme makinesi her gün internete açık
> kalırdı ve tünelin arkasında panel de var.

```
1  ngrok.com'da ücretsiz hesap → authtoken + ÜCRETSİZ SABİT ADRES al
   ⚠️ Sabit adres şart: rastgele adres her açılışta değişir ve her
      seferinde hem kiracıya hem iyzico paneline yeniden yazmak gerekir.

2  .env'e yaz (ikisi de depoya GİRMEZ):
      NGROK_AUTHTOKEN=...
      NGROK_DOMAIN=abc-def.ngrok-free.app

3  Alan adını markaya bağla — webhook kiracıyı ALAN ADINDAN çözüyor:
      php artisan tenant:domain marka-a.localhost abc-def.ngrok-free.app
   ⚠️ Bağlanmazsa istek 404 alır ve TAHSİLAT HİÇ İŞLENMEZ.

4  Caddy'yi yeniden başlat (yeni site bloğunu okusun):
      docker compose up -d caddy

5  Tüneli aç:
      docker compose --profile tunel up -d ngrok
      arayüz: localhost:4040  ← gelen webhook'ları burada görüyoruz

6  iyzico panelinde webhook adresi:
      https://abc-def.ngrok-free.app/webhooks/payment
```

> ⚠️ **Caddy bloğu `http://` önekli** — otomatik HTTPS bilerek kapalı.
> Kapatılmasaydı Caddy gerçek bir alan adı görüp Let's Encrypt'ten
> sertifika isterdi; makine dışarıdan erişilebilir olmadığı için doğrulama
> düşer ve kotamız yanardı (M-4.1/1). TLS'i ngrok sonlandırıyor.
>
> ⚠️ Tünel `caddy:80`'e bağlanıyor, 443'e değil: Caddy'nin `tls internal`
> sertifikasını ngrok tanımaz ve tünel her istekte düşerdi.
>
> **İş bitince:** `docker compose stop ngrok` ve alan adını kaldır
> (`tenant:domain … --kaldir`). Gerçek alan adına geçildiğinde bu servis
> tamamen silinecek — kalıcı bir bağımlılık değil.

---

##### ✅ 1E.7 TAMAMLANDI — gerçek sandbox'ta uçtan uca doğrulandı

```
katalog → sepet → sözleşme → sipariş → ödeme başlat
        → iyzico'nun ödeme formu (kart BİZE DEĞMEDİ)
        → 3D Secure
        → callback  200  "processing"        ← webhook henüz gelmedi
        → webhook   200  "paid"              ← imzasız geldi, İYZİCO'YA SORULDU
        → stok 10 → 9, rezervasyon committed
        → panelde sipariş `paid`
```

**Gerçek koşunun bulduğu, taklidin gizlediği DÖRT şey:**

| Bulgu | Taklit neden gizledi | Sonucu ne olurdu |
|---|---|---|
| callback `token`'ı **POST gövdesinde** yolluyor | sahte sağlayıcı adresi kendisi üretiyordu (`?ref=`) | müşteri ödemeden sonra **404** görüyordu |
| imza başlığı **boş** geliyor | sahte sağlayıcı imzalıyor | hiçbir ödeme işlenemiyordu (401 → tekrar → 401) |
| başlık adı `X-Iyz-Signature` (belgede yalnızca V3 yazıyor) | — | imza hiç okunamazdı |
| başarısız ödemede bile `paidPrice` doğru dönüyor | sahte sağlayıcıda böyle bir ayrım yoktu | tutara bakıp "ödendi" denirdi |

> ★ Dördü de **1D.6'nın dersinin tekrarı**: test uca gidiyor ama uca
> verdiği değeri **kendisi uyduruyorsa**, sınadığı şey kendi varsayımıdır.
> Sahte sağlayıcı gerçek akışı taklit edecek kadar iyiydi (1E-K6) ama
> **protokolün ayrıntısını** uyduramazdı.

**Ayrıca ölçüldü:** vekil arkasında şema (`X-Forwarded-Proto`) — Caddy
`trusted_proxies` olmadan başlığı kendi şemasıyla eziyordu; iyzico callback
adresinin SSL olmasını zorunlu tuttuğu için bu sessizce ödemeyi engellerdi.

**Bitiş ölçütü:** gerçek iyzico sandbox'ında kart girilerek ödeme tamamlanıyor ·
webhook imzası doğrulanıyor · tutar ikinci çağrıyla teyit ediliyor · stok
düşüyor · panelde sipariş ödendi görünüyor · **eksik anahtarla ödeme
başlatılamıyor ve marka eksiği panelde görüyor**

#### 1F — Olay kaydı ✅

```
olay olur                          kuyruk               worker
─────────                          ──────               ──────
product_viewed      ──┐
search_performed    ──┤                             ┌─ events tablosuna yaz
cart_item_added     ──┼──→  kuyruğa atılır  ──────→ ┤  type + jsonb payload
cart_item_removed   ──┤     istek beklemez          └─ occurred_at
order_placed        ──┘
                            ⚠ iş kiracı kimliğini TAŞIR (M-2.4/kuyruk)
                              taşımazsa A'nın olayı B'nin şemasına yazılır
```

---

##### 1F kararları

> **Araştırma:** Medusa olay veriyolunu **modüler** tutuyor (geliştirmede yerel,
> üretimde Redis); Saleor asenkron işleri Celery+Redis'te koşuyor; Magento misafir
> için ayrı ziyaretçi kaydı tutup giriş anında müşteriye bağlıyor; Sylius olayları
> `pre_`/`post_` diye ayırıp **veri yazıldıktan sonra** tetikliyor.
> Ortak desen: **üreten yer ile işleyen yer ayrı**, olay iş bittikten sonra doğar.

**1F-K1 · Olay DOMAIN'de doğar — controller'da değil.**

> Projenin mevcut kuralı: *bir kontrol HTTP dışından atlanabiliyorsa `app/Domain/`'e
> girer.* Olaylar için de aynısı — sipariş bir tohumlayıcıdan da oluşabiliyor.
>
> ⚠️ **Tek istisna `product_viewed`:** iş mantığı yok, saf bir görüntüleme.
> Domain'e taşımak "ürüne bakıldı" diye bir iş kuralı uydurmak olurdu.

**1F-K2 · Misafir kimliği ŞİMDİLİK BOŞ — `anon_id` kolonu açılır, doldurulmaz.**

```
A  sepet token'ı        var ama sepet dönüşünce/yenilenince kimlik KOPAR
B  ayrı anon çerezi     vitrin teknolojisi HENÜZ SEÇİLMEDİ (M-3, Faz 4)
C  şimdilik NULL        ← SEÇİLEN
```

> Gerekçe 1C-K1 ile aynı: çerez, API'yi henüz var olmayan bir istemciye bağlar.
> Yarım bir çözüm koymak, sonradan iki kimlik biçimini birleştirmeye çalışmaktan
> kötüdür. Kolon açık duruyor; Faz 4'te vitrin gelince kimin dolduracağı belli olur.

**1F-K3 · Olay yazımı düşerse SESSİZCE düşer — siparişi BOZMAZ.**

> ⚠️ Olay kaydı, işin kendisinden daha önemli olamaz. Kaydedilmemesi kötü;
> sipariş verilememesi felaket.
>
> **Tekilleştirme YOK — bilinçli.** Ödemede `UNIQUE` ile tekrarı imkânsız kılmıştık
> çünkü orada tekrar = ikinci kez stok düşmek. Burada tekrar = bir fazla satır;
> analizi hafifçe şişirir, parayı bozmaz. Mükemmelliğin bedeli burada değmiyor.

**1F-K4 · `payload` içinde KİŞİSEL VERİ YOK.**

> Yalnızca kimlikler ve sayılar. Ad, e-posta, adres girmez.
>
> ⚠️ Sebebi ileriye dönük: Faz 2'de KVKK silme talebi geliyor ve o iş kişisel
> alanları anonimleştirmek zorunda. `events` tablosunu da taraması gerekirse iş
> iki katına çıkar. Baştan koymamak, sonradan temizlemekten ucuz.

**1F-K5 · ★ Olay TRANSACTION BİTTİKTEN SONRA kuyruğa girer.**

> Araştırmadan çıktı ve **kodumuzda birebir var**:
>
> ```
> CheckoutService::baslat()
>   BEGIN TRANSACTION
>     stok rezerve edilir
>     sipariş oluşur
>     olay kuyruğa atılır      ← ⚠️ BURADA
>   COMMIT   ← ya buraya gelemezse?
> ```
>
> Transaction geri sarılırsa sipariş **hiç var olmaz** — ama olay çoktan Redis'e
> girmiştir. Worker onu alır ve olmayan bir siparişin `order_placed` olayını yazar.
> Kaynaklardaki ifade: *işlem geri sarılınca veritabanı değişiklikleri atılır ama
> gönderilmiş olaylar işlenmeye devam eder.*
>
> Laravel'in `afterCommit` mekanizması kullanılacak — ve **ölçülecek**. Bu hatanın
> belirtisi yok, yalnızca tutarsız veri.

**Tablolar:** `events`
**Bitiş ölçütü:** beş olay tipi kaydediliyor · olayın **doğru kiracının** şemasına
yazıldığı testle kanıtlanıyor · olay yazımı istek süresini uzatmıyor ·
**geri sarılan transaction'ın olayı YAZILMIYOR** · payload'da kişisel veri yok

> Tüketicisi TıkRota'daki gibi bir keşif akışı **değil** — tek markada keşfedilecek satıcı
> yok. Besleyeceği şeyler: ürün önerisi (Faz 3), terk edilmiş sepet (Faz 2) ve markanın
> satış raporu.

---

### 1A — Kimlik, yetkilendirme ve mağaza ayarları

**Amacı:** "kim kimdir ve kim ne yapabilir" sorusunu çözmek, ve markaya özel her şeyin
okunacağı `settings` katmanını kurmak. Sonraki her blok bu ikisine yaslanacak.

**Bitiş ölçütü:** markanın personeli panele girebiliyor ve izinlerine göre kısıtlanıyor;
bir müşteri vitrin tarafında kayıt olabiliyor; mağaza ayarları okunup yazılabiliyor.
Müşteri token'ıyla panel ucuna erişilemiyor — testle kanıtlanıyor.

#### 1A.0 Ön karar: iki ayrı kimlik alanı

- [x] **Laravel Sanctum, token tabanlı** — TıkRota K-12'den devralındı
- [x] **İki ayrı guard: `customer` ve `staff`** — `config/auth.php`
  > **Kanıtlandı (1A.2), tek uç yazmadan önce:** müşteri token'ı `staff` guard'ından,
  > personel token'ı `customer` guard'ından **reddediliyor**. Mekanizma
  > `vendor/laravel/sanctum/src/Guard.php:145` → `$tokenable instanceof $model`.
  > `Customer` ve `User` kardeş sınıflar, biri diğerinin örneği değil.
  > **Tek tabloda olsalardı bu koruma imkânsızdı.**
  > **Neden:** `docs/domain-model.md` §3 gereği müşteri ve personel ayrı tablolar. Tek
  > guard kullanılırsa "bu token hangi tabloya ait" sorusu her istekte tekrar sorulur ve
  > bir gün yanlış cevaplanır. İki guard, ayrımı framework seviyesinde zorunlu kılar.

#### 1A.1 Migration ve modeller (kiracı şeması)

> ⚠️ **0.4b alıştırmasında yakalandı — `timestamps()` kullanma, `timestampsTz()` kullan.**
> Laravel'in varsayılan `$table->timestamps()` metodu PostgreSQL'de
> `timestamp(0) without time zone` üretiyor. `docs/domain-model.md` §0 ise
> **`timestamptz`** (UTC) diyor.
>
> Saat dilimi taşımayan bir zaman damgası, farklı sunucular ve yaz saati geçişinde
> kayar. Sipariş anı, rezervasyon bitişi (+15 dk), kargo tarihleri bundan etkilenir.
> Sonradan düzeltmek her tabloya migration yazmak demek.


- [x] `customers` — `email` **varchar nullable** (citext DEĞİL), `password` nullable,
      `accepts_marketing`, `uuid`
  > ⚠️ **citext denendi, kiracı şemasında ÇALIŞMIYOR.** Eklenti `public` şemasında;
  > marka bağlantısının `search_path`'i onu görmediği için operatörler bulunamıyor ve
  > PostgreSQL **sessizce** düz metin karşılaştırmasına düşüyor — `Ali@x.com` ile
  > `ali@x.com` farklı sayılıyor, hata da vermiyor. Ölçüldü: `search_path` sadece marka →
  > `false`, marka+public → `true`.
  > **Yerine:** modelde sınırda küçültme + `CHECK (email = lower(email))`.
  > Ayrıntı `docs/domain-model.md` §0.
  > **Neden nullable:** misafir siparişini mümkün kılan alan bu (`domain-model.md` §3).
- [x] `users` (personel) — `email` varchar unique, `password`, `is_owner`, `uuid`
  > Laravel'in varsayılan `users` migration'ı silinip yerine bu yazıldı.
  > `remember_token` yok — kimlik token tabanlı (K-12).
- [x] `roles`, `role_user`, `role_permissions` — tek dosyada (birbirine bağlılar)
  > İlk yabancı anahtarlarımız. `role_user` bileşik birincil anahtarlı pivot.
  > ⚠️ `users` soft delete kullandığı için **kullanıcı tarafında cascade fiilen
  > çalışmıyor** — personel "silindiğinde" rol bağları duruyor. Doğru davranış: geri
  > alınırsa rolleri de gelir. Kalıcı silmede cascade devreye giriyor.
- [x] `settings` — `group`, `key`, `value` (jsonb), `is_encrypted` · `unique(group,key)`
  > Anahtar-değer + jsonb seçildi; geniş satır (her ayar bir kolon) alternatifi her yeni
  > ayar için migration gerektirirdi — "çekirdek düzenlenmez, genişletilir" ile çelişir.
- [x] `addresses` — müşterinin adres DEFTERİ
  > ⚠️ Sipariş adresi değil. Sipariş verilirken `orders`'a **kopyalanacak**; müşteri
  > adresini düzeltirse geçmiş siparişlerin nereye gittiği değişmemeli (§7).
- [x] Modeller + ilişkiler — `Customer` · `Address` · `User` · `Role` · `Setting`
  > **Bu blokta üç kez tekrar eden desen:** `$fillable` "neyi ekleyeyim" değil,
  > **"neyi ASLA dışarıdan almam"** listesi.
  > `Address.customer_id` (başkasının defterine adres eklenemesin) ·
  > `User.is_owner` (kimse kendini sahip yapamasın) ·
  > `Role.is_system` (sistem rolünün koruması kaldırılamasın).
  > Üçü de denendi: istekten gelen değer sessizce atılıyor.
  >
  > `Setting` satır bazlı şifreleme yapıyor (Laravel'in hazır `encrypted` cast'i kolon
  > bazlı). **Sıra tuzağı:** `value`, `is_encrypted`'dan önce yazılırsa ödeme anahtarı
  > düz metin kaydedilirdi → `RuntimeException` fırlatılıyor, sessiz yanlış yerine
  > gürültülü durma.
- [x] Enum sınıfı: **`SettingGroup`** (`app/Enums/`)
  > **Neden:** durumları serbest metin yerine PHP enum'u olarak tanımlamak yazım hatasını
  > yazım anında yakalatır. Denendi: `'payments'` (fazladan s) yazınca `ValueError`
  > fırlıyor. Enum olmasaydı satır kaydedilir, panelde ödeme ayarları **boş liste** olarak
  > görünür ve hiçbir hata çıkmazdı.
  >
  > ⚠️ **Plandan sapma — diğer iki enum yazılmadı:**
  > `CustomerStatus` **tamamen düşürüldü**: `customers` tablosunda böyle bir kolon yok,
  > `docs/domain-model.md` §3'te de tasarlanmamış. Spekülatif bir maddeydi.
  > `OrderPaymentStatus` **1D'ye taşındı**: `orders` tablosu orada oluşacak. Şimdi
  > yazılsaydı tüketicisi olmayan, test edilemeyen ve 1D'de muhtemelen değişecek bir sınıf
  > olurdu — plan kuralı 2 (her madde doğrulanabilir olmalı) ve 5 (kapsam genişletme
  > yasağı) ile çelişirdi.
- [x] Factory'ler: `CustomerFactory` (misafir/pazarlamaIzinli), `UserFactory` (sahip),
      `AddressFactory` (müşterisini kendisi üretir), `RoleFactory` (sistem), `SettingFactory` (şifreli)
  > **Neden:** sonraki her blokta test verisi buradan üretilecek. Şimdi doğru kurulursa
  > 1B–1F'de tek satır tekrar yazılmaz.
- [x] `tenants:migrate` sorunsuz çalışıyor — iki kiracıda da 7 tablo kuruluyor
  > `addresses` · `customers` · `role_permissions` · `role_user` · `roles` ·
  > `settings` · `users` (+ 1A.2'de `personal_access_tokens`)

> ✅ **1A.1 — customers ve users tabloları yazıldı.** Yol boyunca iki ek düzeltme:
>
> **1. `domains.domain`'e de küçük harf `CHECK`'i eklendi** (`landlord/` altında ayrı
> migration; paketin kendi dosyasına dokunulmadı). Tutarlılık için: `customers.email` ve
> `users.email` veritabanı garantisi alıyorsa alan adı da almalı.
> ⚠️ Buradaki risk daha ağır: `'Marka-A.com'` ve `'marka-a.com'` iki ayrı satır olarak
> **farklı markalara** bağlanabilirdi → gelen istek hangisine eşleşirse o markanın
> mağazası açılır, yani **yanlış marka servis edilir**. Faz 3'te alan adı web formundan
> geleceği için garanti koda değil veritabanına konuldu.
>
> **2. `tenant:create` yarıda kalırsa artık arkasını topluyor.** 1A.1'de gerçekten yaşandı:
> marka migration'ı hata verdi, `domains` satırına sıra gelmedi ve ortada **öksüz kiracı**
> kaldı — şeması var, hiçbir adresten erişilemiyor. Üstelik HTTP denenene kadar fark
> edilmedi. Artık hata olursa kiracı siliniyor (şeması da düşüyor), çıkış kodu 1.

#### 1A.2 Kimlik doğrulama uçları

- [x] `laravel/sanctum` kuruldu · `personal_access_tokens` **marka şemasında**
  > Token da marka verisi: marka silinince token'ları da gitmeli. `vendor:publish`
  > dosyayı **boş bıraktığımız köke** düşürdü — 0.5/2'de öngördüğümüz tuzağın ta kendisi.
  > `tenant/` klasörüne taşındı. Sanctum kendi migration'ını otomatik yüklemiyor,
  > çift kayıt riski yok.
- [x] `config/auth.php` — iki guard, iki provider (yukarıda 1A.0'daki kanıt)
- [x] `HasApiTokens` iki modele de eklendi

- [x] Müşteri: `POST /api/register` · `POST /api/login` · `POST /api/logout` · `GET /api/me`
  > `api` middleware grubu kullanıldı, `web` değil — `web` oturum ve CSRF getirir,
  > token istemcisi CSRF üretemediği için her POST kırılırdı.
- [x] Personel: `POST /panel/login` · `POST /panel/logout` · `GET /panel/me`
  > Soft delete edilmiş personel giriş yapamıyor (Eloquent zaten
  > `deleted_at IS NULL` ekliyor). `panel/me` cevabında `roles` **yok** — izin
  > sistemi 1A.3'te; şimdi eklenseydi kuralı olmayan bir alanı API sözleşmesine
  > sokmuş olurduk.
  > Personelde **kayıt ucu yok** — personel davetle gelir (1A.3).
- [x] Giriş ve kayıt uçlarına **hız sınırlama** — `giris` 5/dk, `kayit` 10/saat
  > `giris` anahtarı **e-posta + IP birlikte**. Sadece IP olsaydı ortak ağdaki
  > (okul, ofis) kullanıcılar birbirini kilitlerdi; sadece e-posta olsaydı saldırgan
  > farklı adreslerle sınırsız deneme yapardı. Elle doğrulandı: 429 dönüyor.
  > **Neden:** kaba kuvvet saldırısının en ucuz önlemi. M-4.1/3 gereği hız sınırlama
  > vekilde değil burada yapılıyor — bu yüzden atlanamaz.
- [x] Testler — `tests/Tenancy/KimlikTest.php`, **16 test**
  > başarılı kayıt · e-posta küçültme/kırpma · yinelenen e-posta (BÜYÜK harfle de) ·
  > eksik alan · doğru/yanlış parola · **yanlış parola ile olmayan hesabın AYNI mesajı**
  > (hesap sayımı engeli) · misafir giriş yapamıyor · tokensiz 401 · çıkış sonrası token
  > geçersiz · silinmiş personel giremiyor · panelde kayıt ucu yok
  >
  > **Plana ek olarak iki test:** A markasının müşterisi B'de giriş yapamıyor ·
  > A'nın token'ı B'de geçersiz (kiracılık × kimlik kesişimi, daha önce hiç sınanmamıştı).
- [x] **Kritik test:** müşteri token'ı ile `/panel/*` → **401** · personel token'ı ile
      `/api/*` → **401**
  > **Kırmızı görüldü:** `auth:staff` → `auth:customer` yapıldığında **yalnızca bu test**
  > kırıldı, diğer 16'sı geçmeye devam etti.
  >
  > ⚠️ **Test ortamı yapaylığı çıktı ve belgelendi:** testlerde bütün istekler aynı PHP
  > sürecinde koşuyor ve konteynerdeki guard nesnesi bir önceki isteğin kullanıcısını
  > önbellekte tutuyor. Gerçek HTTP'de her istek yeni süreç olduğu için sorun yok —
  > `curl` ile doğrulandı. Testlerde `guardOnbelleginiTemizle()` kullanılıyor.

#### 1A.3 İzin sistemi

- [x] İzin listesi kodda sabit — `app/Enums/Permission`, 9 izin
  > Panelden yeni izin **türü** üretilemez; roller bu listeden seçim yapar. Üretilebilseydi
  > her izin için ayrıca "bu izin neyi kontrol ediyor" eşlemesi gerekirdi ve izin sistemi
  > kendi başına bir projeye dönerdi (domain-model §3 kapsam sınırı).
- [x] Varsayılan rolleri seed et — **ÜÇ rol**: Yönetici · Katalog · Sipariş & Destek
  > ⚠️ **Plandan sapma: "Sahip" bir ROL DEĞİL.** `users.is_owner` bayrağı. Rol olsaydı
  > sahip kendi rolünü kaldırıp markasına kilitlenebilirdi — `is_owner` tam da bunu
  > engelleyen emniyet kilidi (domain-model §3).
  >
  > ⚠️ **`staff.manage` hiçbir varsayılan rolde YOK** — Yönetici'de bile. Personel davet
  > etmek yetki yükseltmeye en yakın işlem: bir yönetici kendine ikinci hesap açıp
  > izinlerini genişletebilirdi. Pratikte personel yönetimi yalnızca sahipte.
  >
  > `tenant:create` artık rolleri ve sahip kullanıcıyı da kuruyor
  > (`--sahip-eposta`, `--sahip-parola`).
- [x] İzin kontrolü tek yerden — `izin:` middleware (`RequirePermission`)
  > ⚠️ **Laravel'in `can:` middleware'i (Gate) KULLANILMADI.** Gate varsayılan guard'ın
  > kullanıcısına bakıyor; bizde varsayılan `customer`, panel uçlarında ise kimlik `staff`
  > guard'ından geliyor — Gate yanlış kullanıcıyı sorgulardı.
  >
  > `User::hasPermission()` izinleri rollerden topluyor ve istek başına bir kez
  > sorguluyor. **Sahip her izne otomatik sahip** (bkz. yukarı).
- [x] `GET / POST / DELETE /panel/staff` — personel davet ve yönetimi (`staff.manage`)
  > URL'de `id` değil **`uuid`** (`User::getRouteKeyName`). Roller **isimle** atanıyor
  > (`{"roles":["Katalog"]}`) — id ile olsaydı hem okunmaz olurdu hem iç kimlikleri
  > sızdırırdı. Olmayan rol adı `exists` kuralıyla reddediliyor; sessizce yok sayılsaydı
  > rolsüz personel oluşur ve neden hiçbir şey göremediği anlaşılmazdı.
- [x] **`is_owner` kilidi** — üç katman
  > **Neden:** son yöneticinin kendini yetkisiz bırakıp panele kilitlenmesini engeller.
  > Bu bir rol değil, emniyet kilidi.
  >
  > 1. `is_owner` `$fillable` dışında → istekle sahiplik alınamaz
  > 2. Sahip **çıkarılamaz** → marka sahipsiz kalmaz
  > 3. Kimse **kendini çıkaramaz** → tek yetkili kendini panelden atamaz
  >
  > 📌 3. kilit yalnızca marka **kendi rolünü** oluşturup ona `staff.manage` verdiğinde
  > devreye giriyor (sahip zaten 2. kilitle korunuyor). Elle test ederken bu senaryoya
  > ulaşılamadı — otomatik testte özel rol kurularak sınandı.
- [x] Testler — `tests/Tenancy/IzinTest.php`, **13 test** (toplam paket: 49)
  > sahip rolsüz ama tüm izinlere sahip · rolsüz personelin hiçbir izni yok · personel
  > yalnızca rolünün izinlerine sahip · **Sipariş & Destek'te iade izni yok** · hiçbir
  > varsayılan rolde `staff.manage` yok · izinsiz personel **403** · davet + rol ataması ·
  > olmayan rol reddi · sahip çıkarılamıyor · `staff.manage`'li biri kendini çıkaramıyor ·
  > çıkarılan personelin token'ı iptal ediliyor · müşteri token'ı personel yönetimine
  > giremiyor
  >
  > **Kırmızı görüldü:** `hasPermission`'daki sahip muafiyeti kaldırılınca **6 test**
  > kırıldı — hepsi sahibin personel yönetebilmesine dayananlar. Muafiyet olmadan sahip
  > (rolü olmadığı için) hiçbir şey yapamıyor: öngörülen kilitlenmenin ta kendisi.

#### 1A.4 Mağaza ayarları · yasal metinler · yayın durumu ✅

- [x] `SettingsService` — grup bazlı okuma/yazma, grup bazlı önbellek
- [x] Şifreli ayarlar **yazılır ama okunmaz** — panele `{"is_set": true}` döner
- [x] Yasal metinler `settings`'ten **çıkarıldı**, sürümlü kendi tablolarına alındı
- [x] `GET / PUT /panel/settings` + `/panel/legal/*` + `/panel/store/*` — `settings.write`
- [x] `tenant:create` varsayılanları — son `TODO(1A.4)` kapandı
- [x] 24 test (49 → 73), kırmızı görüldü

> **PLANDAN SAPMA 1 — yasal metinler ayar değil.** Plan "`settings` alanları" diyordu.
> Ayar *şu an geçerli değer*dir, geçmişi yoktur; yasal metnin geçmişi olmak **zorunda**:
> 15 Mart'ta verilen sipariş, 20 Mart'ta değiştirilen sözleşmeye değil kendi günündeki
> metne bağlı kalmalı. Ayarda dursaydı marka bir virgül düzeltince geçmiş siparişlerin
> dayanağı sessizce değişirdi. → `legal_document_drafts` (değişken) +
> `legal_document_versions` (yalnızca INSERT, veritabanı tetiğiyle korunuyor).
> `SettingGroup::Legal` silindi — dursaydı biri oraya yazardı.
>
> **PLANDAN SAPMA 2 — mağaza yayın durumu eklendi.** Planda yoktu. Zorunlu yasal
> bilgiler eksikken satış yapılabilmesi kabul edilemezdi. Yeni marka **kapalı doğuyor**;
> açılması hazırlık denetiminden geçiyor.
>
> **Model: "önce kapat, sonra düzenle".** Alternatif "yayındayken tek tek engelle"
> modeli, alanı *boşaltmayı* yasaklar ama *yanlış yazmayı* yasaklayamaz — vergi dairesi
> değişince doğru değeri yazana kadar yayında yanlış bilgi durur. Kapalıyken o anı kimse
> görmüyor.
>
> **Kilit sınırı** — ayırt edici soru: *"bu değer müşterinin onayladığı sözleşmenin içine
> giriyor mu?"* Giriyorsa yayındayken kilitli (409). KDV oranı kanunla değişir, kargo
> ücreti günlük iştir → serbest.
>
> **Yer tutucular yayın anında doldurulur.** Yasal taslaklar `{{unvan}}`, `{{vergi_no}}`
> içeren iskeletlerle doğuyor. Yayınlarken mağaza bilgilerinden dolduruluyor; biri eksik
> kalırsa **422, sürüm oluşmuyor**. Müşteri hiçbir koşulda süslü parantez göremez.
> Yan fayda: metin o günkü bilgilerle **donuyor** — "sipariş fotoğraftır"ın metin tarafı.
>
> **Bulgular:** satır tetiği `TRUNCATE`'i görmüyor (PostgreSQL satırları tek tek silmiyor)
> → ayrı `BEFORE TRUNCATE` tetiği · `published_by` FK **verilmedi**, verilseydi personel
> çıkarılınca `ON DELETE SET NULL` satırı UPDATE etmeye çalışır ve tetik personel
> çıkarmayı çökertirdi · yeni marka geliştirmede HTTPS'e çıkmıyor (Caddyfile'da alan
> adları elle sayılı, on-demand TLS Faz 3'te) → komut çıktısına uyarı eklendi.
>
> **Ertelendi:** `store.publish` diye ikinci izin tartışıldı, eklenmedi — "kargo ayarını
> değiştirebilsin ama mağazayı kapatamasın" rolü bugün kurulamıyor. Gerekirse bölünecek.

#### 1A.5 Adres defteri ✅

- [x] `GET / POST / PUT / DELETE /api/addresses`
- [x] Müşteri yalnızca **kendi** adreslerini görür ve değiştirir
- [x] Adreslere `uuid` (UUIDv7) eklendi
- [x] 10 test (73 → 83), kırmızı görüldü
  > **Neden:** bu, projedeki en tekrar eden güvenlik kuralının ilk uygulaması — "sahibi
  > olmadığın kaynağa erişemezsin". Deseni burada oturtuyoruz.

> **DESEN — Policy değil, DARALTILMIŞ SORGU.** Plan "Policy" diyordu; Laravel'in klasik
> policy kalıbı *yükle-sonra-kontrol et*tir ve satırı belleğe getirir:
>
> ```
> YÜKLE-SONRA-KONTROL (kullanılmadı)      HİÇ YÜKLEME (kullanıldı)
> $a = Address::find($id);                $musteri->addresses()
> if ($a->customer_id !== $ben)             ->where('uuid', $uuid)
>     abort(403);                           ->firstOrFail();
>        ▲                                          ▲
> satır belleğe geldi; kontrolü          sorgu zaten WHERE customer_id = <ben>
> yazmayı unutan uç başkasının           içeriyor. Kontrolü unutmak mümkün
> adresini döndürür, hata vermez         değil — kontrol sorgunun kendisi
> ```
>
> `search_path` ile kiracılıkta kullandığımız ilkenin aynısı: yanlış veriyi *sonra
> ayıklamak* yerine **erişilemez** kılmak. Sonraki bloklarda (1B ürün, 1C sepet,
> 1D sipariş, Faz 2 iade) hep bu kullanılacak.
>
> **PLANDAN SAPMA 1 — 403 değil 404.** 403 "böyle bir adres **var** ama senin değil"
> demek olur ve saldırgana varlık bilgisi verir; ayrıca daraltılmış sorgunun doğal
> sonucu zaten 404.
>
> **PLANDAN SAPMA 2 — `uuid` eklendi (planda yoktu).** Ardışık `id` ile müşteri komşu
> numaraları tarayıp mağazadaki toplam adres sayısını çıkarabiliyordu. Veri sızmıyordu
> (sorgu daraltılı) ama **sayı** sızıyordu. `id` içeride kaldı, `uuid` dışarı açılan
> kimlik oldu — `customers`/`users` ile aynı desen. Migration üç adımda yazıldı
> (nullable → PHP tarafında backfill → not null + unique); tek adımda yazılsaydı mevcut
> satırı olan bir markada çökerdi. Backfill PHP'de çünkü PostgreSQL'in
> `gen_random_uuid()`'si v4 üretir, karışık sürümlü kolon istemiyoruz.
>
> **Kendi hatam, düzeltildi:** ilk yazımda örtük rota bağlaması (`Address $adres`)
> kullanmıştım. O, uuid'yi **tüm tabloda** arar — başkasının satırı belleğe gelir.
> "Hiç yükleme" ilkesini savunup tersini yapmak olurdu; o satır bir gün bir loga, hata
> mesajına ya da `dd()`'ye düşerdi. Rota artık düz uuid alıyor.
>
> **Kırmızı görüldü:** sahiplik daraltması kaldırılınca **2 test** kırıldı — liste
> başkasının adresini gösterdi, yabancı adres 404 yerine 200 döndü.

#### 1A.6 Blok kapanışı ✅

- [x] **Rol yönetimi ucu** — marka kendi rolünü kurabilsin (1A.4'te karar verildi)
  > **Neden esnek:** katı roller güvenlik üretmez, **aşırı yetki** üretir. Markanın
  > muhasebecisi yalnızca ciro raporu görecekse ve "sadece finans" rolü yoksa, marka
  > Yönetici rolünü verir — muhasebeci ödeme anahtarlarını da görmeye başlar.
  >
  > **Sınırlar:** izinler yalnızca `Permission` enum'ından seçilir (marka yeni izin
  > *türü* icat edemez) · 3 sistem rolü silinemez · **rol yönetimi izinle değil
  > `is_owner` ile** — izin olsaydı `role.manage` sahibi kendine `settings.write`
  > içeren bir rol kurup atardı. "Yetki dağıtan işlem, yetkiyle dağıtılmaz"
  > (`staff.manage`'ı hiçbir role koymama kararıyla aynı mantık).
- [x] `tenant:create` artık tam çalışıyor: şema + migration + varsayılanlar + sahip kullanıcı
- [x] Seeder: 2 personel (farklı rollerde), 2 müşteri, 2 adres
  > **Neden:** sonraki bloklarda elle veri üretmemek için. 1B'de ürün eklerken hazır
  > yetkili personel bulunacak.
  >
  > **TUZAK KAPANDI — merkez/marka tohumlayıcı ayrımı.** Laravel'in varsayılan
  > `DatabaseSeeder`'ı `User::factory()` çağırıyor ve **merkez** bağlamda koşuyordu;
  > `users` tablosu merkezde yok. Üstelik `config/tenancy.php`'deki `tenants:seed` de
  > aynı sınıfı çağırıyordu — tek sınıf iki bağlamda, "hangi şemadayım" belirsiz.
  > Ayrıldı: `DatabaseSeeder` (merkez, veri üretmiyor) · `TenantDemoSeeder` (marka).
  > Rol ve sahip kullanıcı tohumlayıcıda **üretilmiyor** — onlar `tenant:create`'in işi;
  > iki yerde üretilseydi "marka nasıl doğar" sorusunun iki cevabı olurdu.
  > Üç savunma: canlı ortam reddi · kiracı bağlamı yoksa anlaşılır hata · rol yoksa hata.
  > `firstOrCreate` ile tekrar çalıştırılabilir (üç kez koşturuldu, sayılar sabit).
- [x] `lint` + `analyse` + `test` üçü de yeşil — **98 test**
- [x] CI yeşil
  > ★ **CI 20 KOŞUDUR KIRMIZIYMIŞ** — 1A.2/1'den (`be4168a`) beri, fark edilmeden.
  > Sebep: `app/Models/Customer.php`, `class_attributes_separation` (docblock'lu
  > `use` satırından sonra boş satır). Tek satırlık düzeltme.
  >
  > **İki ders, ikisi de süreçle ilgili:**
  >
  > 1. **Yerel kapı yalan söyledi.** `lint:check` yerelde PASS, CI'da FAIL — aynı
  >    içerikte. Ölçüldü: dosya *tek başına* denetlenince yerelde de FAIL, *tüm proje*
  >    denetlenince PASS. Bozuk sürüm koyunca tam koşu yakalıyor, yani dosyayı
  >    inceliyor. Sebep kesinleşmedi (paralellik değil — `--parallel` kapalı); en makul
  >    açıklama Pint'in geçici klasördeki önbelleğinde bayat kayıt, ama tekrar
  >    üretilemedi. **Tahmin olarak kayıtta, kanıt değil.**
  > 2. **Kural vardı, kimse bakmadı.** "CI yeşil" plan kuralı ve README rozeti dururken
  >    19 commit kırmızı üstüne atıldı. *Kural, bakılmadığı sürece kural değildir.*
  >
  > Actions günlüklerini indirmek depo **yöneticiliği** istiyor (API 403); sebebi ancak
  > `.github/ci-kontrol.sh` eklenip hata çıktısı **anotasyona** basılınca görebildik.
  > Anotasyonlar herkese açık. CLAUDE.md'ye kontrol komutu yazıldı.
- [x] **İki kiracıda doğrulandı** (plan kuralı 6) — gerçek HTTP, 6 başlık
  > A token'ı B panelinde 401 · aynı e-posta iki markada ayrı iki kişi · A'nın adres
  > uuid'si B'de 404 · katalogcu `/panel/settings` 403 ve `/panel/roles` 403, sahip 200 ·
  > kargo ücreti A 11.11 / B 99.99 karışmıyor · A yayında (0 eksik), B kapalı (9 eksik).
  >
  > **BULGU — eski markalar varsayılanları almıyor.** `tenant:create` yeni markaya
  > varsayılan ayar ve yasal taslakları kuruyor (1A.4), ama **önceden açılmış**
  > markalara kimse gidip kurmuyor. B markası (0.5'te açıldı) yasal taslaksızdı.
  > Elle uygulandı. Canlıda olsaydı eski markalar taslaksız kalır ve kimse fark
  > etmezdi → Faz 3'e geri-doldurma komutu maddesi eklendi.
- [x] `docs/` içindeki bir sapma varsa güncellendi

---

## ✅ FAZ 1A TAMAMLANDI

```
1A.0 kiracı iskeleti     1A.1 tablolar + modeller    1A.2 kimlik (iki guard)
1A.3 izin + personel     1A.4 ayarlar + yasal        1A.5 adres defteri
1A.6 rol yönetimi + tohum + doğrulama

98 test · lint · analyse (seviye 8) · CI — hepsi yeşil
```

**1A'nın bıraktığı desenler** — sonraki bloklar bunları kullanacak:

| Desen | Nerede doğdu | Nerede kullanılacak |
|---|---|---|
| `$fillable` = "asla dışarıdan almam" listesi | 1A.1 | her yeni model |
| Daraltılmış sorgu = sahiplik kontrolü | 1A.5 | 1B · 1C · 1D · Faz 2 |
| Sürümlü + değişmez kayıt (tetikle zorlanan) | 1A.4 | 1E sözleşme bağlama |
| Kayıt bir fotoğraftır (kopyala, bağlama) | 1A.1 · 1A.4 | 1D sipariş satırları |
| Yetki dağıtan işlem yetkiyle dağıtılmaz | 1A.3 · 1A.6 | yeni ayrıcalıklı uçlar |
| Emniyeti bozup kırmızı görmeden yeşile güvenme | 0.4b'den beri | her blok |

### 1A sonrası mimari inceleme — ölçülerek yapıldı

**Tutan:** `app/Domain/` içinde `App\Tenancy`, `tenant(`, `tenancy(` geçişi **sıfır**.
M-2.7'nin tek cümlesi 15 dosya ve 1359 satır sonra ayakta. Kiracılık bir gün değişse
(şema → ayrı veritabanı) bu katmana dokunulmaz.

**Tutmayan (düzeltildi):** iş mantığının nereye yazıldığı tutarsızdı. Kimlik, ayarlar ve
yasal metinlerin Domain servisi vardı; **roller ve adresler tamamen controller'daydı**.
`RoleController` gerçek iş kuralları taşıyordu:

```
"sistem rolü silinemez" · "üzerinde personel olan rol silinemez"
        ▲
bu kurallar HTTP katmanındayken bir artisan komutu, kuyruk işi ya da
tohumlayıcı rol silerse ÇALIŞMAZLARDI — hata da vermezlerdi.
1A.5'te adres için reddettiğimiz desenin ta kendisi.
```

→ `RoleService` yazıldı, kurallar Domain'e indi. **Kanıt:** iki yeni test HTTP'den
geçmeden servisi doğrudan çağırıp kuralları doğruluyor; taşımadan önce bu testler
yazılamazdı.

`AddressController` bilerek servissiz kaldı: oradaki tek kural sahiplik daraltması
(`$musteri->addresses()`) ve o, unutulabilir bir *kontrol* değil, ilişkinin kendisi.
HTTP dışından yazan bir çağıran da doğal olarak aynı ilişkiden geçer.

> **KURAL (CLAUDE.md'ye de yazıldı):** bir kontrol, HTTP dışından
> (artisan · kuyruk · tohumlayıcı) atlanabiliyorsa `app/Domain/`'e girer.
> Controller yalnızca çevirir: isteği al, servisi çağır, cevabı biçimle.

**Diğer düzeltmeler:**

| Bulgu | Düzeltme |
|---|---|
| `magazayiHazirla` / `sirketBilgileriniDoldur` iki dosyada kopya; 1B'de üçüncüsü yazılacaktı | `tests/Pest.php`'ye toplandı (`ornekAdres` de) |
| CLAUDE.md "app/Tenancy = kiracılığın TAMAMI" — gerçekte 142 satır, kiracılık 5 yere yayılı | Cümle düzeltildi, beş yerin listesi yazıldı |
| `tests/Feature/ExampleTest.php` Laravel varsayılanı olarak duruyordu | `MerkezTest.php` ile değiştirildi: merkez adres · `/up` · tanımsız alan adı 404 |

**Notlandı, düzeltilmedi:** 9 izinden 7'sinin hâlâ kapısı yok (1B/1D'de gelecek) — ama
bu bir kez ısırdı: `settings.write` üç blok boyunca hiçbir şeyi korumuyordu ve kimse fark
etmedi. · `bootstrap/app.php`'deki istisna→HTTP eşlemesi 6'ya çıktı; 1D/1E'de 10+ olunca
ayrı dosyaya taşınmalı.

---

## Faz 2 — Olgunlaştırma  ← **AÇIK**

```
2H bildirim    → 2G kvkk → 2B iade → 2A kupon → 2C arama → 2D · 2E · 2F
```

> **Sıranın gerekçesi.** Bildirim olmadan diğerlerinin yarısı yazılamaz (iade
> sonucu, hatırlatma, veri indirme bağlantısı hep maile bağlı). KVKK her yeni
> tabloyla büyüyor — şimdi üç tablo taranacak, 2A ve 2B'den sonra beş. İade ise
> ödeme kodu tazeyken yazılmalı; altı ay sonra iyzico'nun düzenini yeniden
> öğrenmek gerekir.

> **Araştırma yapıldı, kararlar ona göre verildi.** Kaynaklar bölüm sonunda.
> ⚠️ Bir öneri araştırma sonucu **DEĞİŞTİ**: kısmi iadede kargo bedeli
> "geri verilmez" denmişti; mevzuat tam caymada teslim masraflarının da iadesini
> **zorunlu** tutuyor (2B-K5).

---

### 2H — Bildirim altyapısı ✅

> ⚠️ **Faz 1'in görülmemiş eksiği.** Sipariş onay maili bile yok; `mailpit`
> compose'da duruyor ama tek satır kod yazılmadı. Bu, e-ticarette
> "müşteri parasını verdi ve hiçbir şey duymadı" demek.

**2H-K1 · Mail KUYRUKTA gider.**
> Müşteri bekletilmez. 1F'deki gerekçenin aynısı: mailin bir saniye geç gitmesi
> zararsız, isteğin bir saniye uzaması vitrini yavaşlatır.

**2H-K2 · Mail düşerse İŞ BOZULMAZ.**
> ⚠️ 1F-K3'ün tekrarı. Mail gitmemesi kötü, sipariş oluşamaması felaket.
> Kuyruk zaten tekrar deniyor.

**2H-K3 · Şablonlar KODDA, marka yalnızca değişkenleri değiştirir.**
> Logo, ad, imza markadan; metin ve düzen kodda. Yasal metinler gibi
> **sürümlenmesine gerek yok** — mail geçmişe dönük bir dayanak değil (1A.4).
> Panelden serbest metin düzenleme Faz 4'ün işi.

**2H-K4 · İlk dört mail:** sipariş onayı · ödeme başarısız · kargoya verildi · teslim edildi.

**Tablolar:** — (kuyruk yeterli)
**Bitiş ölçütü:** dört mail gidiyor · kuyrukta gidiyor · mail düşünce sipariş
etkilenmiyor · doğru markanın ayarlarıyla üretiliyor (M-2.4)

> ✅ **TAMAMLANDI.** `BrandMail` tabanı + üç posta sınıfı + `Notifier`.
> Gerçek koşu: Mailpit'e `A Markası <destek@ornek.test>` adresinden
> "Siparişiniz alındı — TM-2026-000013" gitti, gövdede satırlar ve
> "KDV (dâhil)" doğru.
>
> ★ **Sipariş onayı `odemeBasarili()`'de**, `baslat()`'ta değil — sipariş
> `pending` doğuyor ve ödemesi hiç tamamlanmayabiliyor. Kırmızı kontrol:
> çağrı `baslat()`'a taşınınca ilgili test kırmızıya döndü.
>
> ★ **Gönderen işçide okunuyor**, atış anında gömülmüyor: gömülseydi marka
> iletişim adresini değiştirdikten sonra kuyrukta bekleyen postalar eski
> adresle giderdi.

---

### 2G — KVKK: anonimleştirme ve veri indirme ✅

> **Araştırma:** Magento ve WooCommerce **ikisi de** aynı yolu tutuyor —
> sipariş muhasebe için saklanıyor, kişisel alanlar anonimleştiriliyor.
> Magento anonimleşen siparişi **"misafir siparişi"ne** çeviriyor.

**2G-K1 · SİLME DEĞİL ANONİMLEŞTİRME.**

```
customers        ad · e-posta · telefon      → tanınmaz hale
addresses        tamamı                       → tanınmaz hale
orders           shipping_* · billing_* · email  ← ⚠️ ASIL İŞ BURADA
order_items      dokunulmaz (tutar, sku, adet kalır)
```

> ⚠️ Sipariş bir **fotoğraf**: adres müşteri defterinden değil, siparişin kendi
> kopyasından okunuyor (1D). Yalnızca `customers` temizlenseydi kişisel veri
> siparişlerde olduğu gibi kalırdı — ve kimse fark etmezdi.
>
> ⚠️ `DELETE` yazılsaydı ya yasal kayıt kaybolur ya yabancı anahtar kırılırdı.

**2G-K2 · Anonimleşen sipariş MİSAFİR SİPARİŞİNE dönüşür.**
> `customer_id → null`. Yapı zaten hazır: misafir siparişi Faz 1'den beri var
> (M-1) ve `orders.customer_id` nullable.

**2G-K3 · Misafir de talep edebilir: e-posta + sipariş numarası.**
> ⚠️ Doğrulama maili şart. Olmasaydı sipariş numarası tahmin eden biri
> (numaralar ardışık, 1D-K4) başkasının verisini sildirebilirdi.

**2G-K4 · İşlem GERİ ALINAMAZ; talebin kendisi kayda geçer.**
> Ne zaman, hangi sipariş kapsamında — ama **kişisel veri olmadan**.
> "Sildim mi silmedim mi" sorusunun cevabı kalmalı.

**2G-K5 · Müşteri kendi verisini indirir — makine okunur.**
> Küçük bir uç; anonimleştirmenin yanında yazılıyor.

**Tablolar:** `data_requests` (talep izi)
**Bitiş ölçütü:** anonimleştirme sonrası siparişin tutarı ve satırları duruyor ·
kişisel alanların hiçbiri okunamıyor · `events` zaten temiz (1F-K4) · misafir
talebi doğrulama maili olmadan işlemiyor

> ✅ **TAMAMLANDI.** `Anonymizer` · `DataExporter` · `DataRequestService`
> + vitrin uçları. Kırmızı kontrol iki kez: sipariş alanları
> anonimleştirmeden çıkarılınca 3 test, doğrulama kapısı kaldırılınca
> 6 test kırmızıya döndü.
>
> **Ek karar — ŞEHİR ve İLÇE KALIYOR.** Kişiyi tanımlamıyorlar ama markanın
> satış coğrafyası raporu onlara dayanıyor. Silinseydi geçmiş dağılım
> bozulur, KVKK açısından hiçbir kazanç olmazdı.
>
> **Ek karar — e-posta ANONİMLEŞTİRİLİRKEN benzersiz kalıyor.**
> `customers.email` unique; hepsine aynı işaret yazılsaydı ikinci
> anonimleştirme veritabanı hatasıyla düşerdi.
>
> ⚠️ Talep kaydında **e-posta tamamlanınca siliniyor**, `email_hash`
> kalıyor: "bu adres için talep var mıydı" cevaplanabilsin ama adres
> okunamasın. Kalsaydı silme kaydı, silinen verinin kopyası olurdu.

---

### 2B — İade ve cayma hakkı ✅  *(en zor blok)*

**2B-K1 · İADE TALEBİ ile PARA İADESİ AYRI ŞEYLERDİR.**

```
iade talebi   müşteri "geri göndereceğim" der   ürün YOLDA
para iadesi   marka onaylar, para gider          stok geri girer
```

> ⚠️ Karıştırılırsa ya para ürün gelmeden gider ya stok gelmeden açılır.
> **Magento da ayırmış:** kredi notu ekranında "stoğa geri" ayrı bir onay kutusu.

**2B-K2 · 14 gün TESLİM GÜNÜNDEN başlar.**
> Mevzuat açık: süre tüketicinin **malı teslim aldığı** gün başlıyor; malın
> **taşıyıcıya teslimi süreyi BAŞLATMIYOR**.
>
> ⚠️ Bizde bu `fulfillments.delivered_at` demek — `orders.placed_at` değil.
> Sipariş tarihinden sayılsaydı kargoda geçen her gün müşterinin hakkından
> yenirdi.
>
> ⚠️ **Kısmi sevkiyatta her paketin kendi teslim tarihi var** (1D.4). Süre
> paket paket işliyor.

**2B-K3 · İade SATIR SATIR yapılır — tutar yazılarak değil.**
> **Magento'nun modeli:** kredi notunda satırlar seçiliyor.
> ⚠️ Tutar bazlı olsaydı verginin hangi satırdan düştüğü bilinemez, KDV hesabı
> tutmazdı.

**2B-K4 · Vergi YENİDEN HESAPLANMAZ; seçilen satırın vergisi geri döner.**
> Magento da böyle yapıyor. Vergi dâhil fiyatlandırmada (§8.2) satırın vergisi
> zaten donmuş durumda — yeniden hesaplamak kuruş kaydırırdı.

**2B-K5 · ⚠️ TAM CAYMADA KARGO BEDELİ DE GERİ VERİLİR.**

> **Bu madde araştırma sonucu DEĞİŞTİ.** İlk öneri "kısmi iadede kargo geri
> verilmez, tam iptalde verilir" idi; mevzuat daha katı:
> satıcı **teslim masrafları dâhil tahsil edilen tüm ödemeleri** iade etmekle
> yükümlü.
>
> ⚠️ Ayrıca **süre işliyor:** malın iade taşıyıcısına verilmesinden itibaren
> 14 gün içinde para geri gitmiş olmalı.
>
> Kısmi iadede operatör belirliyor (Magento'da da ayrı alan) — ama tam caymada
> seçenek yok.

**2B-K6 · Stok OTOMATİK geri girmez.**
> Ayrı onay: ürün gerçekten geldi mi, satılabilir mi? Otomatik olsaydı
> hiç gelmeyen ürün satışa açılırdı.

**2B-K7 · Sağlayıcıya iade çağrısı İDEMPOTANSLIK ANAHTARI taşır.**
> ⚠️ 1E-K4'ün tekrarı — bu sefer para **geri** giderken. İki kez iade,
> iki kez tahsilattan beter.

**Tablolar:** `returns` · `return_items` · `refunds`
**Bitiş ölçütü:** kısmi iade satır bazlı çalışıyor · vergi doğru geri dönüyor ·
tam caymada kargo iade ediliyor · stok yalnızca onayla geri giriyor · aynı iade
iki kez çağrılamıyor · 14 gün teslim tarihinden hesaplanıyor

> ✅ **TAMAMLANDI.** `WithdrawalWindow` · `RefundTotals` · `ReturnService` ·
> `RefundService` + `RefundablePaymentProvider` + vitrin/panel uçları.
>
> **★ TESTLER İKİ GERÇEK EKSİK BULDU:**
>
> 1. **Kısmi iadeden sonra ikinci talep açılamıyordu.** `talepAc` yalnızca
>    `paid` kabul ediyordu; ilk kısmi iade siparişi `partially_refunded`
>    yapınca kalan satırların iade hakkı kapanıyordu.
>
> 2. **İki adımda yapılan tam cayma "tam" sayılmıyordu.** Kargo yalnızca
>    tek talepte tüm adetler iade edilirse geri veriliyordu. Müşteri
>    siparişin tamamını iki talepte iade ederse bu da tam caymadır —
>    artık BİRİKİMLİ sayılıyor, ve kargo bir kez iade ediliyor.
>
> **★ KIRMIZI KONTROL BİR TESTİ ÇÜRÜTTÜ.** Cayma süresini `placed_at`'ten
> saydırdım ve testler **yeşil kaldı**: her senaryoda sipariş "az önce"
> verilmişti, fark görünmüyordu. Ayrımın göründüğü tek durum **geç
> teslimat** — 20 gün önce verilip dün teslim edilen sipariş. O test
> eklendi; şimdi kırılma gerçekten yakalanıyor.
>
> ✅ **iyzico iade yolu GERÇEK SANDBOX'TA DOĞRULANDI** — ve ilk hâli
> çalışmadı. 1E.7.3'ün dersi bir kez daha:
>
> **Bulgu 1 — iyzico ödemeyi KIRILIMLARA bölüyor.** Sepetteki her satır
> ayrı bir `paymentTransactionId` alıyor ve **iade her kırılım için ayrı**
> yapılıyor:
>
> ```
> ödeme 299,80  →  kırılım A: ürün   249,90
>                  kırılım B: kargo   49,90
> ```
>
> İlk uygulama tek kırılıma tüm tutarı gönderdi ve gerçek sandbox
> reddetti: `5093 — verilen iade tutarı kırılımın tutarından büyük
> olamaz`. Taklit bunu uyduramazdı. Artık tutar kırılımlara dağıtılıyor
> ve `refundedPrice` düşülüyor (kısmi iadeden sonraki kalan).
>
> **Bulgu 2 — başarısız çağrıdan sonra iade TEKRAR DENENEMİYORDU.**
> Sağlayıcı çağrısı düşünce kayıt `pending` kalıyor, ikinci deneme
> sağlayıcıya hiç gitmeden o kaydı geri veriyordu. Artık yalnızca
> `completed` kayıt erken dönüyor.
>
> **⚠️ Bulgu 3 — AÇIKLANAMAYAN TUTAR.** Bir çağrıda 249,90 istendi,
> `status: success` ve `price: 200` döndü; sebebi cevaptan anlaşılamadı.
> Kontrollü tekrar ölçümde aynı tutar tam geçti. Sebep **kesinleşmedi**.
> Kapatılan şey belirti: sağlayıcının iade ettiği tutar istenenle
> karşılaştırılıyor, tutmuyorsa **gürültülü hata**. Olmasaydı kayıtta
> 299,80 iade yazarken müşteriye 249,90 gitmiş olurdu — hiçbir yerde
> görünmeden.

---

### 2A — Kupon ✅

**2A-K1 · Kargo eşiği İNDİRİMDEN SONRAKİ tutara bakar. (ayarlanabilir)**

```
A  indirim → eşik    480₺ −%20 = 384₺ → eşiğin altında → kargo VAR   ← varsayılan
B  eşik → indirim    480₺ eşiği geçti → kargo YOK → sonra indirim
```

> ⚠️ **Bu kuruş değil YÜZDE kaybettirir.**
> **Araştırma:** WooCommerce'in varsayılanı da A — ama bunu bir **ayar** yapmış
> ve konuyla ilgili en az iki hata kaydı açılmış; yani satıcılar anlaşamıyor.
> Biz de aynısını yapıyoruz: varsayılan A, `settings`'te bayrak.

**2A-K2 · Sipariş başına TEK kupon.**
> Üst üste binme (stacking) Faz 2'de yok. İki kupon aynı anda uygulanınca
> "hangisi önce" sorusu doğuyor ve indirim beklenenden büyük çıkabiliyor.

**2A-K3 · Kullanım sınırı SATIR KİLİDİYLE korunur.**
> ⚠️ 1D-K5'in tekrarı: "acaba kullanılmış mı" kontrolü yarışı çözmez. Son bir
> kullanımı kalan kupon, aynı anda gelen iki istekte iki kez kullanılırdı —
> hatasız.

**2A-K4 · Kupon kodu ve tutarı SİPARİŞE KOPYALANIR.**
> ⚠️ "Sipariş bir fotoğraftır" ilkesi (1D). Kupon sonradan silinse bile sipariş
> neyle indirildiğini söyleyebilmeli.

**Tablolar:** `coupons` · `coupon_redemptions` (+ `orders.coupon_code`,
`carts.coupon_code`)
**Bitiş ölçütü:** yüzde/sabit/ücretsiz kargo çalışıyor · eşik sırası ayarla
değişiyor · son kullanım eşzamanlı iki istekte bir kez tükeniyor · sipariş
kuponsuz da okunabiliyor

> ✅ **TAMAMLANDI.** `CouponCode` · `DiscountCalculator` · `CouponService`
> + vitrin ucu.
>
> **★ TÜRKÇE BÜYÜTME TUZAĞI — `EmailNormalizer`'ın (1A.2) kardeşi.**
> `mb_strtoupper('indirim')` Türkçe yerelde `İNDİRİM` üretiyor; müşteri
> klavyeden `INDIRIM` yazıyor ve kupon **bulunamıyor** — hata da vermiyor,
> "geçersiz kupon" diyor ve marka kampanyasının neden tutmadığını
> anlayamıyor. `CouponCode` harf harf ASCII'ye indiriyor; ayrıca
> `CHECK (code = upper(code) AND code ~ '^[A-Z0-9_-]+$')` ile veritabanı
> da zorluyor — uygulamadan kaçan tek satır bile bozuk kod yazamıyor.
>
> **★ Ek karar — KOTA SEPETTE DEĞİL SİPARİŞTE harcanıyor.** Sepette
> harcansaydı kuponu deneyip vazgeçen her müşteri kampanyadan bir kullanım
> yer, kupon hiç satış olmadan tükenirdi.
>
> **★ Ek karar — indirim SEPETTEN BÜYÜK OLAMIYOR.** Olsaydı `grand_total`
> eksiye düşer, sağlayıcıya negatif tutar gider ve ödeme hiç başlatılamazdı.
>
> **⚠️ Müşteri başına sınır MİSAFİRDE UYGULANAMIYOR** — kimlik yok.
> Sessizce "uygulandı" sayılsaydı marka "kişi başı 1" derken misafirler
> sınırsız kullanırdı.
>
> **★ KIRMIZI KONTROL: satır kilidi silinince HİÇBİR TEST KIRILMADI** —
> 1D'dekinin birebir tekrarı. Sıralı testler kilidi zorlamıyor. Çözüm de
> aynı: üretilen SQL'de `for update` arayan **yapısal test**.

---

### 2C — Arama

**2C-K1 · PostgreSQL'İN KENDİ ARAMASI — dış servis yok.**

```
LIKE '%kelime%'     indeks kullanmaz, ölçekte çöker
PostgreSQL FTS      Türkçe sözlük HAZIR, tsvector + GIN     ← seçilen
pg_trgm             yazım hatası toleransı                   ← birlikte
Meilisearch/ES      ayrı servis, ayrı bakım — Faz 2'ye fazla
```

> **Araştırma:** üretimde ikisi birlikte kullanılıyor — FTS kelime için,
> trigram hata için ("tişort" → "tişört").

**2C-K2 · Hız ÖLÇÜLÜR, varsayılmaz.**
> ⚠️ İndekssiz `similarity()` her satırı tarıyor. 1B'de `text_pattern_ops`
> ölçümünde aynı tuzağa düşmüştük: sorgu çalışır, sessizce tam tarama yapar.

**2C-K3 · ⚠️ Türkçe küçük harf tuzağı burada TEKRAR çıkacak.**
> `KIRMIZI` → `kirmizi` ama `Kırmızı` → `kırmızı` (1B'de ölçüldü). Arama
> normalleştirmesi `EmailNormalizer`'daki dersi tekrarlamalı.

★ `search_performed` olayı ilk üreticisine kavuşuyor (1F).

**Bitiş ölçütü:** çok kelimeli arama çalışıyor · yazım hatası tolere ediliyor ·
sorgu planı indeks kullanıyor (ölçüldü) · Türkçe karakterde tutarlı

---

### 2D — Koleksiyon

**2D-K1 · Manuel liste VE kural birlikte.**
> 1B-K7'de not düşülmüştü: koleksiyon "nerede göstereyim" sorusudur, kategori
> "bu nedir". Ürün tek kategoride, çok koleksiyonda.

**2D-K2 · Kural SORGU ANINDA çalışır — önceden hesaplanmaz.**
> ⚠️ Saklanan liste, fiyat değişince **bayatlar ve kimse fark etmez**.
> "250₺ altı" koleksiyonunda 400₺'lik ürün durur.
> Ölçekte yavaşlarsa materyalleştirme + tazeleme işi ayrı karar olur.

**Tablolar:** `collections` · `collection_product`
**Bitiş ölçütü:** manuel ve kurallı koleksiyon çalışıyor · fiyat değişince
kurallı koleksiyon kendiliğinden güncelleniyor

---

### 2E — Yorum ve puan

**2E-K1 · Yalnızca SATIN ALAN yazabilir.**
> Sipariş kontrolü. Herkese açık olsaydı rakip ve bot yorumu kaçınılmazdı.

**2E-K2 · Yorum ONAY BEKLER, otomatik yayınlanmaz.**
> ⚠️ Markanın sorumluluğu: hakaret veya kişisel veri içeren yorum vitrinde
> anında görünmemeli.

**2E-K3 · Ortalama puan sayacı GECE DENETLENİR.**
> ⚠️ `committed` sayacının aynısı (1D.5): materyalleştirilmiş sayının bedeli
> denetimdir. Onarmaz, **haber verir** — kendiliğinden düzeltseydi sayacı hangi
> kod yolunun bozduğu hiç görünmezdi.

**Tablolar:** `reviews` (+ `products.rating_avg`, `rating_count`)
**Bitiş ölçütü:** satın almayan yazamıyor · onaysız yorum vitrinde yok ·
ortalama denetimle doğrulanıyor

---

### 2F — Terk edilmiş ödeme

**2F-K1 · Sepet DEĞİL, ödemesi yarım kalmış SİPARİŞ hedeflenir.**

> ⚠️ Sınır dürüstçe kabul ediliyor: misafirin e-postasını **ancak ödeme
> adımında** öğreniyoruz. Sepette e-posta alanı yok.
>
> **Araştırma:** WooCommerce eklentileri de aynı sorunla boğuşuyor — seçenekleri
> *hiç kaydetme · ödeme sayfasında e-posta girilince yakala · açılır pencereyle
> önceden iste.* Bizim tek POST'luk checkout'umuzda ara yakalama yok.
>
> Ama elimizde **daha güçlü** bir sinyal var: `pending` kalmış siparişler.
> Orada e-posta zaten dolu (1D: misafir siparişinde bile zorunlu). Daha az
> kayıt, çok daha yüksek dönüşüm.

**2F-K2 · Olay kaydını İLK KEZ TÜKETEN iş bu.**
> 1F'de "tüketicisi şu an yok" diye yazılmıştı; burada kavuşuyor.

**2F-K3 · Hatırlatma BİR KEZ gider.**
> ⚠️ Zamanlanmış görev `tenants:run` ile sarılır (0.5, 5. tuzak) ve gönderilen
> hatırlatma işaretlenir — yoksa her koşumda aynı müşteriye tekrar gider.

**Bitiş ölçütü:** ödemesi yarım kalan siparişe bir kez hatırlatma gidiyor ·
ödenmiş siparişe gitmiyor · iki markada karışmıyor

---

##### Bu kararların dayandığı kaynaklar

> · **Mevzuat** — [Mesafeli Sözleşmeler / cayma hakkı](https://tuketici.ticaret.gov.tr/yayinlar/tuketici-bilgi-rehberi/mesafeli-sozlesmeler-hakkinda-bilgilendirme)
>   (14 gün **teslimden** başlar; taşıyıcıya teslim süreyi başlatmaz; teslim
>   masrafları dâhil tüm ödemeler iade edilir)
> · **Magento** — [kredi notu](https://experienceleague.adobe.com/en/docs/commerce-admin/stores-sales/order-management/credit-memos/credit-memo-create)
>   (satır bazlı iade · vergi yeniden hesaplanmaz · kargo ayrı alan · "stoğa
>   geri" ayrı kutu) · [GDPR anonimleştirme](https://www.scommerce-mage.com/magento-2-gdpr.html)
> · **WooCommerce** — [ücretsiz kargo](https://woocommerce.com/document/free-shipping/)
>   ve tartışma kayıtları [#17274](https://github.com/woocommerce/woocommerce/issues/17274) ·
>   [#11232](https://github.com/woocommerce/woocommerce/issues/11232)
>   (eşik varsayılan olarak **indirimden sonraki** tutara bakıyor, ama ayar) ·
>   [GDPR](https://condorito.fr/docs/woocommerce-manual/clients-rgpd.html) ·
>   [terk edilmiş sepet](https://woocommerce.com/document/abandoned-cart-recovery/)
> · **PostgreSQL** — [metin arama](https://www.postgresql.org/docs/current/textsearch-controls.html)
>   (Türkçe sözlük hazır) · [pg_trgm](https://www.postgresql.org/docs/current/pgtrgm.html)
>   (yazım hatası toleransı)

---

## Faz 3 — Satılabilirlik  *(henüz açılmadı)*

Kontrol düzlemi · abonelik ve planlar · marka açma akışının tamamı · **gerçek on-demand TLS**

**1A'dan devredilen maddeler:**

- **Varsayılan geri-doldurma komutu.** ⚠️ **1E.4'te ikinci kez ısırdı:**
  0.5'te açılan dev markalarının ödeme imza anahtarı yoktu (`DefaultSettings`
  1E.1'de genişledi) ve iki kiracıda gerçek HTTP koşusu `fake_secret anahtarı
  ayarlarda yok` hatasıyla durdu. Elle dolduruldu. **Gürültülü istisna işe
  yaradı** — 1E.1'de boş anahtarla imzalamayı yasaklamasaydık, imza sessizce
  boş anahtarla üretilir ve doğrulama hiçbir şey korumazdı. `tenant:create` yeni markaya varsayılan ayarları
  ve yasal taslakları kuruyor (1A.4), ama **önceden açılmış** markalara kimse gitmiyor.
  1A.6'da ölçüldü: 0.5'te açılan B markası yasal taslaksızdı. Canlıda eski markalar
  eksik doğar ve kimse fark etmez. Gereken: `tenants:backfill` gibi, eksik varsayılanı
  olan markalara **var olanı ezmeden** ekleyen bir komut.
- **Sahip parolası varsayılanı kaldırılacak.** `tenant:create --sahip-parola` şu an
  `123`; kontrol düzlemi gelince marka kendi parolasını belirleyecek (1A.3).

## Faz 4 — Arayüz  *(henüz açılmadı)*

M-3 — teknoloji kararı bu fazın başında verilecek. Vitrin + yönetim paneli.

## Faz 5 — Entegrasyonlar  *(henüz açılmadı)*

Kargo firmaları · e-fatura / e-arşiv

## Faz 6 — Dağıtım  *(henüz açılmadı)*

Yayın · yedekleme · gözlemlenebilirlik

---

## Sonraya bırakılanlar

> Kapsam dışı kalan ama "iyi olurdu" denen fikirler buraya yazılır. Plana girmez.

- **e-Fatura entegrasyonu** → Faz 5. Vergi **kolonları** Faz 1'de açılıyor
  (`docs/pre-setup.md` §3/0 şartı)
- **Filament** (hazır panel altyapısı) değerlendirmesi → Faz 4
- **Storefront API / mobil uygulama** → M-3'ün şartı tutulursa sonradan ince bir katman
- **pgBouncer (bağlantı havuzlayıcı)** — şu an gerek yok: tek sunucu, az işçi,
  bağlantı baskısı yok.
  > ⚠️ **Eklenirse şartı var:** `search_path` bir *oturum* ayarıdır, işlem değil.
  > pgBouncer'ın verimli modu olan `transaction` modunda fiziksel bağlantı her işlem
  > sonunda başka isteğe verilir ve A markasının `search_path`'i B'ye geçer — şema bazlı
  > kiracılıkta bu doğrudan veri sızmasıdır. Ya `session` modu kullanılmalı (havuzlama
  > kazancının çoğu kaybolur) ya da her işlem başında `search_path` yeniden kurulmalı.
  > Bkz. `docs/pre-setup.md` M-2.4 / 5. tuzak.
- Çoklu depo · çoklu para birimi · çoklu dil
- Bölgesel/desi bazlı kargo tarifesi
- Pazaryeri kanal entegrasyonları (Trendyol, Hepsiburada)
- Panelden özelleştirilebilir izin türleri (`domain-model.md` §3 kapsam sınırı)

---

## İleri iyileştirme fikirleri — ⏸️ KARARA BAĞLANMADI

> ⚠️ **Bu bölüm bir plan değil.** Buradaki hiçbir madde kararlaştırılmadı, hiçbiri
> kapsamda değil. **Proje bittikten sonra** ele alınıp alınmayacağına bakılacak; alınırsa
> da burada yazan çözümler yerine başka yaklaşımlar seçilebilir.
>
> Kaynak: kullanıcı araştırması. "Büyük ölçekli e-ticaret sistemlerinde FrankenPHP
> sonrasındaki mimari sorunlar" başlığı altında derlendi.
>
> 📌 **Ortak ön kabul:** listenin tamamı **FrankenPHP'ye geçildiğini** ve **mikroservise
> bölündüğünü** varsayıyor. İkisi de bugün kararımız değil — PHP-FPM ve modüler monolit
> kullanıyoruz. Yani maddelerin bir kısmı henüz var olmayan bir problemi çözüyor.

| # | Sorun | Önerilen çözüm | **Bizdeki durum** |
|---|-------|----------------|-------------------|
| 1 | Mikroservisler arası HTTP/REST haberleşmesi hantal | gRPC + Protobuf | **Konu dışı.** Mikroservis yok, modüler monolit var. Servisler aynı süreçte, ağ turu bile yok. Ancak bölünmeye karar verilirse anlamlı |
| 2 | Uygulama bellekte kalıcı olunca RAM şişiyor (memory leak) | Worker Recycling — `max_requests` sonrası süreç kendini yeniler | **Bizde bu problem YOK.** PHP-FPM'de her istek sıfırdan başlar ve süreç ölür (bkz. istek akışı dersi). Problem yalnızca FrankenPHP/Octane gibi *kalıcı süreç* modellerinde doğar. ⚠️ Ama kuyruk işçimiz kalıcı — orada geçerli |
| 3 | Ağır CPU işleri API'yi kilitliyor | İşleri kuyruğa devret (Redis/RabbitMQ/Kafka) | **Zaten yapıldı (0.2, 0.5).** Redis kuyruğu + ayrı `worker` konteyneri + `scheduler` çalışıyor. Görsel işleme, e-posta, olay kaydı oraya gidecek |
| 4 | Gevşek tipler kritik sepet hatalarına yol açıyor | `strict_types` + PHPStan/Psalm | **Yarısı yapıldı (0.3).** Larastan **seviye 8** kurulu ve CI'da çalışıyor. Eksik olan: dosya başlarına `declare(strict_types=1)` zorunluluğu. Bu tek satırlık bir Pint kuralıyla eklenebilir |
| 5 | Veritabanı bağlantı darboğazı | Connection pooling + PgBouncer/ProxySQL | ⚠️ **KARARIMIZLA ÇELİŞİYOR.** Zaten "Sonraya" listesinde ve şartıyla birlikte yazılı: `search_path` bir *oturum* ayarıdır, pgBouncer'ın verimli modu olan `transaction` modunda fiziksel bağlantı başka isteğe verilir ve **A markasının şeması B'ye geçer** — şema bazlı kiracılıkta bu doğrudan veri sızmasıdır. Eklenecekse `session` modu ya da her işlemde `search_path` yeniden kurulumu gerekir (M-2.4 / 5. tuzak) |

### Ayrıca — 1A.4'ten çıkan, karara bağlanmamış fikir

| Fikir | Ne çözer | Maliyeti |
|---|---|---|
| **Ayarlarda taslak (kesintisiz düzenleme)** | Bugün marka, sözleşmesindeki şirket bilgilerini değiştirmek için mağazasını **kapatmak** zorunda — satış duruyor. Taslak kopyada düzenleyip tek anda yayına geçirse mağaza hiç kapanmazdı | Ayarların **iki sürümünü** tutmak: taslak tablosu + yayın tablosu + geçiş işlemi. Yasal metinlerde bunu zaten yaptık; aynısını ayarlara yaymak demek |

> Bugün gerek yok: şirket bilgileri yılda belki bir kez değişiyor ve düzenleme birkaç
> dakika sürüyor. Ölçüm olmadan eklenmez.

**Değerlendirme sırası geldiğinde sorulacak ilk soru** — K-6/M-2.0'daki ölçek prensibi:
*bu problemi gerçekten yaşıyor muyuz, yoksa yaşayabileceğimizi mi düşünüyoruz?*
Ölçüm olmadan hiçbiri eklenmez.
