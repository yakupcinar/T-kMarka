# TıkMarka — Geliştirme Planı

> **Bu dosya projenin tek yol haritasıdır.** Tüm geliştirme buna göre ilerler.
> Kararların gerekçeleri `docs/pre-setup.md`'de, veri modeli `docs/domain-model.md`'de.
> Son güncelleme: **2026-08-03**

```
┌─ YOL HARİTASI ────────────────────────────── şu an: 1A.4  ───┐
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
> | Yasal metinler (KVKK aydınlatma, iade politikası, mesafeli satış) | 1A.4 — `settings` alanları |
> | **Mesafeli satış sözleşmesi onayı** | **1D** — `orders`'a kanıt kolonları |
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
 ürün oluştur (draft)                 ProductQuery::forStorefront()
   → varyant ekle (sku, fiyat, stok)    → kategori / filtre / sırala
   → görsel yükle (sıralı)              → liste
   → KDV oranı belirle                  → ürün detay (varyantlar + görseller)
   → status = active
```

**Kural — ürün listeleme tek bir sorgu katmanından geçer.** Controller'a gömülü sorgu
yazılmayacak. Kategori, koleksiyon, arama, öneri ve panel — beşi de
`app/Domain/Catalog/ProductQuery` üzerinden geçer, yalnızca modu değişir:

```
ProductQuery::forStorefront()  → sadece status=active ürün, is_active varyant
ProductQuery::forPanel()       → taslak ve arşiv dahil hepsi
```

> **Neden:** "vitrinde hangi ürün görünür" kuralı beş yere kopyalanırsa bir gün birinde
> unutulur ve **taslak hâlindeki, fiyatı girilmemiş ürün müşteriye görünür** — sessiz bir
> hata. Panelin ayrı mod olması da tesadüf değil: panel taslakları görmek **zorunda**.
> Sorunun iki doğru cevabı var, bu ayrım tek yerde yaşamalı. (TıkRota K-4/1'in karşılığı.)

**Tablolar:** `categories` · `products` · `product_variants` · `product_images`
**Bitiş ölçütü:** panelden varyantlı ürün eklenebiliyor · vitrin ucu **yalnızca** aktif
olanları dönüyor · taslak ürünün vitrinde görünmediği testle kanıtlanıyor

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

**Tablolar:** `carts` · `cart_items`
**Bitiş ölçütü:** misafir sepete ekleyebiliyor · giriş sonrası sepet birleşiyor ·
`customer_id` ve `session_token`'dan tam olarak birinin dolu olması veritabanı `CHECK`'i
ile zorlanıyor

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

#### 1A.4 Mağaza ayarları (`settings`)

- [ ] Ayar okuma/yazma servisi — grup bazlı, tipli
- [ ] `is_encrypted` alanlar için `encrypted` cast
- [ ] `GET / PUT /panel/settings` — `settings.write` izni gerekli
- [ ] Kurulum varsayılanlarını `tenant:create`'e ekle (M-2.5 adım 4): KDV, kargo, yasal
      metin şablonları
- [ ] Testler:
  - [ ] Şifreli ayar veritabanında **ham okunamıyor**
  - [ ] `settings.write` izni olmayan personel yazamıyor
  - [ ] İki kiracının aynı anahtarı **farklı değer** dönüyor

#### 1A.5 Adres defteri

- [ ] `GET / POST / PUT / DELETE /api/addresses`
- [ ] Policy: müşteri yalnızca **kendi** adreslerini görür ve değiştirir
- [ ] Test: başka müşterinin adresine erişim **403** dönüyor
  > **Neden:** bu, projedeki en tekrar eden güvenlik kuralının ilk uygulaması — "sahibi
  > olmadığın kaynağa erişemezsin". Deseni burada oturtuyoruz.

#### 1A.6 Blok kapanışı

- [ ] `tenant:create` artık tam çalışıyor: şema + migration + varsayılanlar + sahip kullanıcı
- [ ] Seeder: 1 sahip, 4 rol, 2 personel (farklı rollerde), 2 müşteri, varsayılan ayarlar
  > **Neden:** sonraki bloklarda elle veri üretmemek için. 1B'de ürün eklerken hazır
  > yetkili personel bulunacak.
- [ ] `lint` + `analyse` + `test` üçü de yeşil
- [ ] CI yeşil
- [ ] **İki kiracıda doğrulandı** (plan kuralı 6)
- [ ] `docs/` içindeki bir sapma varsa güncellendi

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

**Değerlendirme sırası geldiğinde sorulacak ilk soru** — K-6/M-2.0'daki ölçek prensibi:
*bu problemi gerçekten yaşıyor muyuz, yoksa yaşayabileceğimizi mi düşünüyoruz?*
Ölçüm olmadan hiçbiri eklenmez.
