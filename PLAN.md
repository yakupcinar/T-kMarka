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
│  2 · OLGUNLAŞMA    kampanya · kupon · iade · arama · kvkk      │
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
| **2** | Olgunlaştırma | Kampanya, kupon, iade, arama, yorum, koleksiyon |
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

> 🔜 Blok blok açılacak. **1A açık**, diğerleri sırası gelince yazılacak.

- [ ] **1A — Kimlik, yetki ve mağaza ayarları** ← aşağıda
- [ ] 1B — Katalog
- [ ] 1C — Sepet
- [ ] 1D — Stok + Sipariş + Sevkiyat
- [ ] 1E — Ödeme
- [ ] 1F — Olay kaydı

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

**Tablolar:** `orders` · `order_items` · `fulfillments` · `fulfillment_items` ·
`stock_reservations`
**Bitiş ölçütü:** uçtan uca test — misafir sipariş verir, stok düşer, sipariş **kısmi**
sevk edilir, `fulfillment_status` doğru hesaplanır · eşzamanlı iki siparişte aşırı satış
olmadığı testle kanıtlanır

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

**Tablolar:** `payments`
**Bitiş ölçütü:** sahte sağlayıcıyla ödeme uçtan uca çalışıyor · başarısız ödemede
rezervasyon serbest bırakılıyor · imzasız webhook reddediliyor

#### 1F — Olay kaydı

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

**Tablolar:** `events`
**Bitiş ölçütü:** beş olay tipi kaydediliyor · olayın **doğru kiracının** şemasına
yazıldığı testle kanıtlanıyor · olay yazımı istek süresini uzatmıyor

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

## Faz 2 — Olgunlaştırma  *(henüz açılmadı)*

Kampanya ve kupon motoru · iade · arama · yorum · koleksiyonlar · terk edilmiş sepet

**Bu faza ayrıca M-1'den iki madde düşüyor:**

- **Cayma hakkı (14 gün)** — iade akışının parçası, ayrı iş değil
- **KVKK veri silme talebi** — müşterinin kişisel alanları **anonimleştirilir**
  > ⚠️ **`DELETE` değil.** Siparişler yasal saklama süresi boyunca silinemez; silinen şey
  > kişisel alanlardır (ad, e-posta, telefon, adres). Sipariş satırı tutarıyla birlikte
  > yerinde kalır, kime ait olduğu okunamaz hale gelir. Basit bir silme yazılırsa ya yasal
  > kayıt kaybolur ya da yabancı anahtarlar kırılır.
- **Müşterinin kendi verisini indirmesi** — küçük bir uç, yukarıdakinin yanında yazılır

## Faz 3 — Satılabilirlik  *(henüz açılmadı)*

Kontrol düzlemi · abonelik ve planlar · marka açma akışının tamamı · **gerçek on-demand TLS**

**1A'dan devredilen maddeler:**

- **Varsayılan geri-doldurma komutu.** `tenant:create` yeni markaya varsayılan ayarları
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
