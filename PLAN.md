# TıkMarka — Geliştirme Planı

> **Bu dosya projenin tek yol haritasıdır.** Tüm geliştirme buna göre ilerler.
> Kararların gerekçeleri `docs/pre-setup.md`'de, veri modeli `docs/domain-model.md`'de.
> Son güncelleme: **2026-08-14**

```
┌─ YOL HARİTASI ────────────── şu an: FAZ 4 · 4A bitti ──────┐
│                                                                │
│  0 · TEMEL      ✅ git → docker → test → KİRACILIK → ci        │
│                    ╰ çıktı: iki kiracı, verileri karışmıyor    │
│                                                                │
│  1 · ÇEKİRDEK   ✅ 1A kimlik+yetki+ayarlar                     │
│                    1B katalog → 1C sepet                       │
│                    1D stok+sipariş+sevkiyat  ← en zor          │
│                    1E ödeme → 1F olay kaydı                    │
│                    ╰ çıktı: misafir müşteri sipariş verebiliyor│
│                                                                │
│  2 · OLGUNLAŞMA ✅ 2H bildirim → 2G kvkk → 2B iade             │
│                    2A kupon → 2C arama → 2D koleksiyon         │
│                    2E yorum → 2F terk edilmiş ödeme            │
│                    ╰ çıktı: mağaza konuşuyor, geri veriyor,    │
│                      bulunabiliyor, güven üretiyor             │
│                                                                │
│  3 · SATILABİLİRLİK ✅ 3A varsayılan → 3B merkez tablo         │
│                    3C kontrol düzlemi → 3D marka açma          │
│                    3E abonelik → 3F kota → 3G kalıcı silme     │
│                    3H özel alan adı + on-demand TLS            │
│                    ╰ çıktı: ürün kendi kendini satıyor         │
│                      ⚠️ eksik: marka geneli veri dışa aktarma  │
│                                                                │
│  4 · ARAYÜZ  ◀ AÇILDI — M-3 verildi: yüzeye göre bölünmüş      │
│     vitrin Blade · panel+yönetim Inertia+Vue · SSR YOK         │
│     ✅ 4A vitrin → ✅ 4B akış → 4C panel → 4D katalog            │
│     4E sipariş → 4F yönetim → 4G tema → 4H kapanış             │
│  5 · ENTEGRASYON   kargo · e-fatura                            │
│  6 · DAĞITIM       yayın · yedekleme · izleme                  │
│                                                                │
│  Kural: bir blok bitmeden sonrakine geçilmez.                  │
│  577 test · lint · analyse · CI hepsi yeşil                    │
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

#### 2C — BİTTİ ✅ (13 test · 396 test yeşil)

Kod: `app/Domain/Search/` (`SearchText`, `ProductSearch`) ·
`app/Console/Commands/ReindexSearch.php` ·
`database/migrations/tenant/2026_08_12_210000_add_search_to_products.php` ·
`tests/Tenancy/SearchTest.php`

**Planla çelişen dört bulgu — hepsi ölçümle, üçü testleri yalanlayarak.**

**1 · `pg_trgm` marka şemasından GÖRÜNMÜYOR (2C-K1'e ek).**
Eklenti `public`'te; marka `search_path`'i onu içermiyor. citext (1A) ve
ltree (1B) ile aynı tuzak — **üçüncü kez**. Bu sefer gürültülü (sorgu
patlıyor, sessizce yanlış sonuç vermiyor). Bütün çağrılar nitelikli:
`public.similarity`, `public.gin_trgm_ops`, `OPERATOR(public.<%)`.
Türkçe FTS sözlüğü ise `pg_catalog`'ta — o **görünüyor**, ek iş yok.

**2 · `similarity()` DEĞİL `word_similarity` — ve fonksiyon değil OPERATÖR.**
```
similarity('basic tisort demo', 'tsiort')       = 0,20   ✗ hiç bulmuyor
word_similarity('tsiort', 'basic tisort demo')  = 0,29
```
`similarity()` metnin *tamamıyla* karşılaştırıyor: arama kelimesi kısa,
ürün metni uzun olduğu için puan eşiğin altında kalıyor. Ayrıca fonksiyon
biçimi GIN indeksini **kullanmıyor**; `<%` operatörü kullanıyor (plan
`Bitmap Index Scan on products_search_text_trgm_idx` gösteriyor — 2C-K2).

**3 · ★ FTS kolu SİLİNDİĞİNDE HİÇBİR TEST KIRILMADI.**
Kırma denemesiyle bulundu. Sebep ölçüldü: eşik 0,3'te trigram, FTS'in
bulduğu her şeyi zaten buluyor (`tişörtler` 0,60 · `gömlekleri` 0,55 ·
`ayakkabıları` 0,62). Yani "Türkçe kök bulma" testi aslında **trigram'ı**
ölçüyordu. Ters yön de ölçüldü — Türkçe stemmer sanıldığı kadar güçlü
değil, 4+ ekli kelimede duruyor (`tişörtlerimizdekiler` → FTS bulmuyor,
trigram buluyor).

→ **Karar değişti:** FTS'in işi *bulmak* değil **sıralamak**.
`setweight` A/B/C ile alaka sıralaması (`ts_rank`) eklendi ve onu ölçen
bir test yazıldı. İlk hâli yine yalancıydı — ürünler `id` sırasında zaten
doğru geliyordu; sıra ters kurulunca test gerçekten kırılır oldu.

**4 · ★ TEST YEŞİL, GERÇEK MARKA BOŞ — `search_text` uzunluğa duyarlıymış.**
`tsiort` araması testte çalışıyordu, iki kiracıda gerçek HTTP koşusunda
**hiçbir şey bulmadı**. Sebep: SKU'lar da `search_text`'e yazılıyordu;
test verisinde 1, gerçek üründe **9** varyant vardı →
`"basic tisort demo bt-9 bt-8 … bt-5"` → skor 0,33'ten 0,286'ya düştü.
Yani ürün, **varyant sayısı arttığı için** aranamaz oldu.

→ SKU `search_text`'ten çıktı, FTS vektörüne (C ağırlığı) girdi: orada
tam token eşleşmesi yapıyor ve metnin uzunluğunu etkilemiyor. `?q=BT-1`
gerçek markada doğrulandı.

**Eşik 0,3 — ölçülerek seçildi, gerçek katalog metinleri üzerinde:**

| arama | doğru ürün | en yüksek gürültü |
|---|---|---|
| `cuzdn` | 0,667 | 0,000 |
| `gomlek` | 1,000 | 0,286 |
| `kolleksyon` | 0,467 | 0,091 |
| `tsiort` | **0,286** | 0,000 |

⚠️ **Sınır dürüstçe kaydedildi:** `tsiort` (6 harfte iki harf yer
değiştirmiş) bulunmuyor. Eşiği oraya indirmek `gomlek`'in yanlış ürünü
getirmesi demekti. Trigram her yazım hatasını kurtarmaz — kurtardığı
iddia edilseydi test yalan söylerdi. Test artık **hem çalışan hem
çalışmayan** vakayı ölçüyor.

**`search:reindex` komutu — kolon sonradan eklendiği için ZORUNLU.**
`search_text`/`search_vector` yalnızca ürün *değiştiğinde* yazılıyor;
migration'dan önceki ürünlerin alanları boş kaldı ve bu **hata vermedi** —
ürünler duruyor, vitrin çalışıyor, sadece arama onları bulmuyordu. Gerçek
iki markada ölçüldü: tek ürün bile aranabilir değildi.
⚠️ Marka verisine dokunuyor → `php artisan tenants:run "search:reindex"`.

**Doğrulandı (iki kiracıda gerçek HTTP):** `?q=tişört` · `?q=tişörtler`
(kök) · `?q=cuzdn` (yazım hatası) · `?q=BT-1` (SKU) → hepsi doğru ürün;
taslak "Yaklaşan Koleksiyon" aramada **çıkmıyor** (`forStorefront` — 1B-K10).

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

#### 2D — BİTTİ ✅ (15 test · 411 test yeşil)

Kod: `app/Domain/Catalog/` (`CollectionService`, `CollectionQuery`,
`CollectionRules`, `CollectionRuleException`) · `app/Models/ProductCollection.php` ·
`app/Enums/CollectionType.php` · `app/Http/Panel/CollectionController.php` ·
`app/Http/Storefront/CollectionController.php` ·
`database/migrations/tenant/2026_08_13_090000_create_collections_tables.php` ·
`tests/Tenancy/CollectionTest.php`

**2D-K3 · Kural şeması KAPALI LİSTE — plana eklendi.**

```
field       op                anlamı
brand       eq · contains
title       contains
category    in_tree           ALT AĞAÇ dâhil (1B-K6)
price       lte · gte         ⚠️ VARYANTIN alanı, ürünün değil
match: all │ any
```

> ⚠️ Alan listesi açık bırakılsaydı `{"field":"cost_price"}` yazan bir kural
> maliyet üzerinden koleksiyon kurabilir, hatta hata mesajıyla maliyeti
> sızdırabilirdi. Bilinmeyen alan **sessizce atlanmıyor**, istisna fırlıyor —
> atlansaydı üç koşullu kuralın ikisi uygulanır, koleksiyon fazla ürün
> gösterir ve kimse fark etmezdi.

> ⚠️ `price` "en az bir satılabilir varyant bu koşulu sağlıyor" diye okunuyor.
> Ürünün en düşük fiyatı üzerinden okunsaydı `gte` anlamsızlaşırdı.

**2D-K4 · BOŞ KURAL YASAK.**
> İzin verilseydi koleksiyon **tüm kataloğu** gösterirdi — hata vermeden.
> Marka "kampanya koleksiyonu" sanır, vitrinde her ürün çıkardı.

**2D-K5 · Kayıtlı kural ÇALIŞTIRILMADAN ÖNCE TEKRAR doğrulanıyor.**
> "Yazarken doğruladık" yetmez: kural veritabanına elle, tohumlayıcıyla ya da
> eski bir sürümle girmiş olabilir. Test bunu veritabanına bozuk kural yazarak
> ölçüyor.

**2D-K6 · Manuel ve kurallı KARIŞMIYOR.**
> Kurallı koleksiyona elle ürün eklenemiyor (422), manuele dönerken kural
> siliniyor. Karışsaydı "bu ürün neden burada" sorusunun **iki** cevabı olurdu
> ve elle eklenen ürün, kural onu dışlasa bile listede kalırdı. Kural kalsaydı
> tip bir gün geri çevrildiğinde markanın hatırlamadığı eski kural yürürlüğe
> girerdi.

**2D-K7 · `LIKE` joker karakteri kaçırılıyor.**
> Kaçırılmasaydı `%` yazan tek bir kural tüm kataloğu eşleştirirdi — sessizce.

**Sınıf adı `Collection` DEĞİL `ProductCollection`.**
> Laravel'in `Support\Collection` ve `Eloquent\Collection` sınıfları her
> dosyada import edili; aynı ad her `use` satırında takma ad gerektirirdi ve
> bir gün biri yanlış olanı import ederdi. Tablo adı `collections` kalıyor.

**Beş kırma denemesi, beşi de doğru testi düşürdü** (2C'deki dersin
uygulanması — orada üç test yanlış şeyi ölçüyordu):

| kırılan | düşen test |
|---|---|
| manuel sıra → `id` | markanın SIRASI |
| `any` → `all` | "any" ile "all" farkı |
| `forStorefront()` → ham sorgu | TASLAK ürün çıkmıyor |
| `LIKE` kaçırma kalktı | joker karakter |
| kayıtlı kural doğrulanmadı | 6 test birden |

**Doğrulandı (iki kiracıda gerçek HTTP):** kurallı koleksiyon açıldı,
fiyat veritabanından değiştirildi ve **koleksiyona dokunmadan** liste
güncellendi (1 ürün → 0 → başka ürün → geri). Geçersiz kural 422.
Manuel koleksiyonda sıra panelden değiştirildi ve vitrine yansıdı; eklenen
taslak ürün vitrinde **çıkmadı** (`forStorefront` — 1B-K10).

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

#### 2E — BİTTİ ✅ (14 test)

Kod: `app/Domain/Review/` (`ReviewService`, `PurchaseProof`, `RatingCounter`,
iki istisna) · `app/Models/Review.php` · `app/Enums/ReviewStatus.php` ·
`app/Console/Commands/AuditRatingCounters.php` ·
`app/Http/{Storefront,Panel}/ReviewController.php` ·
`database/migrations/tenant/2026_08_13_120000_create_reviews_table.php` ·
`tests/Tenancy/ReviewTest.php`

**2E-K1 netleşti: "satın aldı" değil "TESLİM ALDI".**
> Ödemeyle yetinilseydi kargodaki ürün hakkında yorum yazılırdı — ürün
> deneyimi değil, beklenti olurdu. Teslim tespiti `WithdrawalWindow::teslimTarihi()`'nden
> geliyor, kopyası yazılmadı: orada iptal edilmiş paketlerin sayılmaması gibi
> ölçülmüş bir incelik var (1D.4). İki kopya olsaydı biri düzeltilir, diğeri
> sessizce eski davranışta kalırdı.
>
> ⚠️ **İade edilmiş sipariş SAYILIYOR:** parası geri verilmiş olabilir ama
> ürünü kullandı. Dışlansaydı memnun olmayan müşteri, tam da yorumu en
> değerli olan kişi olarak susturulurdu.
>
> ⚠️ **Misafir yazamıyor — bu bir SINIR, gizlenmiyor.** Misafir siparişte
> kimlik yok, "bu kişi gerçekten aldı mı" sorusu cevaplanamaz.

**2E-K4 · ÜRÜN BAŞINA TEK YORUM — silinmişi de sayılarak.**
> Kısıt olmasaydı aynı müşteri aynı ürüne defalarca 5 yıldız verip ortalamayı
> tek başına belirlerdi. `withTrashed` şart: bakılmasaydı müşteri yorumunu
> silip yenisini yazarak kotayı sonsuz kullanır, veritabanı kısıtı
> `deleted_at`'e bakmadığı için istek 500 ile düşerdi.

**2E-K5 · Puan aralığı VERİTABANINDA da kısıtlı** (`CHECK rating BETWEEN 1 AND 5`).
> Yalnızca uygulamada doğrulansaydı tohumlayıcı ya da elle yazılan bir satır
> 7 yıldızlı yorum sokabilir ve ortalama sessizce bozulurdu.

**2E-K6 · Vitrinde ad KISALTILIYOR** ("Ahmet Y."), `moderation_note` hiç yok.
> Tam ad yazılsaydı müşterinin kim olduğu vitrinde herkese açık olurdu;
> moderasyon notu personel içindir.

**Sayaç: artırma değil YENİDEN HESAPLAMA.**
> Artırma yazılsaydı her durum geçişinde (onay → red → onay) ayrı düzeltme
> gerekirdi ve biri unutulduğunda sayaç sessizce kayardı. Onayda **ve**
> reddetmede tazeleniyor: yalnızca onayda yazılsaydı geri alınan yorum
> ortalamada kalır, puan şişik görünürdü.

**⚠️ `IS DISTINCT FROM`, `<>` DEĞİL.**
> `null <> null` sonucu `null`, yani "farklı" sayılmaz: **yorumu olmayan
> ürünlerdeki bozukluk sessizce denetimden kaçardı.** Ayrı bir test bunu
> ölçüyor.

**Gecelik denetim:** `tenants:run puan:sayac-denetle` (03:45, stok
denetiminden 15 dk sonra — ikisi aynı anda aynı markanın bağlantı havuzunu
tüketmesin). `stok:sayac-denetle`'nin ikizi: **onarmıyor, haber veriyor.**

**Beş kırma denemesi yapıldı ve BİRİ testlerdeki bir yalanı ortaya çıkardı:**

| kırılan | düşen test |
|---|---|
| teslim şartı kalktı | "ödedi ama teslim almadı" |
| ortalama bekleyenleri de saydı | ⚠️ **hiçbiri** → test düzeltildi |
| red'de tazeleme yok | onay/red ortalaması |
| silinmiş yorum sayılmadı | ikinci yorum engeli |
| `IS DISTINCT FROM` → `<>` | null tuzağı |

> ★ "Onaysız yorum ortalamaya girmiyor" testi aslında **yanlış şeyi
> ölçüyordu**: sayaç zaten 0'dı çünkü yazma sırasında hiç tazeleme
> yapılmıyor. Teste açık bir `tazele()` çağrısı eklendi; şimdi kırma
> denemesi onu düşürüyor. 2C'deki dersin tekrarı.

**★ 2E'NİN EN BÜYÜK BULGUSU 2E İLE İLGİLİ DEĞİL: her cevap JSON değilmiş.**

Gerçek `curl` koşusunda misafirin yorum denemesi **500** döndü; testte 401'di.
Ölçüldü ve sorunun 2E'ye özel olmadığı görüldü — **bütün korumalı uçlar**:

```
                              Accept: application/json    başlık YOK
api/products/{slug}/reviews            401                    500
api/addresses                          401                    500
panel/collections                      401                    500
```

Sebep: Laravel kimliksiz HTML isteğini `login` adlı rotaya yönlendirmeye
çalışıyor; arayüz olmadığı için (M-3) öyle bir rota yok →
`RouteNotFoundException` → 500.

⚠️ **425 testin hiçbiri yakalamamıştı.** Hepsi `postJson`/`getJson` kullanıyor
ve o yardımcılar başlığı otomatik ekliyor. Bu, 1D.6'daki "uçtan uca testte
kimlik modelden okunmaz" dersinin kardeşi: test istemcisi gerçek istemci gibi
davranmıyorsa yeşil hiçbir şey kanıtlamaz.

İki çözüm denendi ve **ikisi de çalışmadı** (ölçüldü, ölü kod bırakılmadı):
`$exceptions->shouldRenderJsonWhen(fn () => true)` cevabı JSON yaptı ama durum
kodu 500 kaldı; `$exceptions->render(AuthenticationException::class)` hiç
çalışmadı — Laravel bu istisnayı kullanıcı geri çağırmalarından **önce**
eşliyor. Çalışan çözüm istek düzeyinde: `app/Http/Middleware/ForceJson.php`.

Test: `tests/Tenancy/JsonCevapTest.php` (3 test) — ve o dosyada `postJson`
**kullanılmıyor**; kullanılsaydı yeşil olur ama hiçbir şey ölçmezdi. Middleware
kaldırılınca üçü de düşüyor (doğrulandı).

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

**2F-K2 · ⚠️ BU KARAR GERÇEKLE ÇELİŞTİ — DÜZELTİLDİ.**

> Plan "olay kaydını ilk kez tüketen iş bu" diyordu. Uygulayınca öyle
> **olmadı**: bu işin ihtiyacı olan her şey (`payment_status`, `created_at`,
> `email`) zaten `orders` tablosunda. Olaylara bakmak ek sorgu getirir ve
> hiçbir yeni bilgi vermezdi.
>
> Zorlanabilecek tek kullanım "müşteri hâlâ sitede geziniyorsa mail gönderme"
> idi; o da yalnızca **kayıtlı** müşteride çalışırdı (olayda `customer_id`
> var, misafirde yok) — yani yarım çalışan bir özellik olurdu. Eşik zaten
> 60 dakika: o kadar süre ödeme yapmayan biri gitmiştir.
>
> **`search_performed`** olayı 2C'de gerçek üreticisine kavuştu; olay
> kaydının ilk gerçek **tüketicisi** markanın rapor ekranı olacak (Faz 3).
> 1F'de "tüketicisi şu an yok" doğruydu ve hâlâ doğru.

**2F-K3 · Hatırlatma BİR KEZ gider.**
> ⚠️ Zamanlanmış görev `tenants:run` ile sarılır (0.5, 5. tuzak) ve gönderilen
> hatırlatma işaretlenir — yoksa her koşumda aynı müşteriye tekrar gider.

**Bitiş ölçütü:** ödemesi yarım kalan siparişe bir kez hatırlatma gidiyor ·
ödenmiş siparişe gitmiyor · iki markada karışmıyor

#### 2F — BİTTİ ✅ (12 test)

Kod: `app/Domain/Order/AbandonedOrderService.php` ·
`app/Console/Commands/RemindAbandonedOrders.php` · `app/Mail/AbandonedOrderMail.php` ·
`resources/views/mail/abandoned-order.blade.php` ·
`Notifier::odemeHatirlatmasi()` · `routes/console.php` (saatlik) ·
`database/migrations/tenant/2026_08_13_160000_add_abandoned_reminder_to_orders.php` ·
`tests/Tenancy/AbandonedOrderTest.php`

**2F-K4 · PENCERE: 60 dakika ile 72 saat arası.**

```
|---- 60 dk ----|--------- hatırlatma gider ---------|---- 72 saat: YOK ----
 rezervasyon      sipariş gerçekten terk edilmiş       çok geç ve rahatsız
 hâlâ ayakta                                            edici
```

> **Alt eşik** `StockService::ODEME_DAKIKA` ile aynı, tesadüfen değil: daha
> erken gönderilseydi müşteri hâlâ 3DS ekranında olabilirdi ve "ödemenizi
> tamamlayın" maili tam ödeme yaparken düşerdi.
>
> **★ ÜST SINIR EN ÖNEMLİ KORUMA.** `abandoned_reminded_at` kolonu sonradan
> eklendi; eklendiği an geçmişteki **bütün** `pending` siparişler
> "hatırlatılmamış" görünüyor. Sınır olmasaydı görevin ilk koşusu aylar
> öncesine kadar herkese mail atardı — hata vermeden, tek seferde, geri
> alınamaz biçimde. (2C'de aynı sınıf hata sessiz bir **eksiklikti**; burada
> sessiz bir **saldırı** olurdu.)

**2F-K5 · Mail STOK SÖZÜ VERMİYOR.**
> Rezervasyon 60 dakikada düşüyor (1D-K3) ve mail o süre dolduktan sonra
> gidiyor. "Ürünleriniz sizin için ayrıldı" demek tutulamayacak bir söz
> olurdu: ödeme kabul edilse bile stok açığı çıkabilir (1E-K5). Metin
> "stok durumu o anda kontrol edilecek" diyor ve test bunu ölçüyor.

**2F-K6 · `failed` siparişe GİTMİYOR — ayrı hikâye.**
> `failed`'da müşteri denedi ve reddedildi (`PaymentFailedMail` gitti),
> `pending`'de hiç denemedi. İkisine de gönderilseydi müşteri aynı sipariş
> için çelişkili iki mail alırdı.

**İşaretleme gönderimden ÖNCE, koşullu güncellemeyle.**
> Sonra işaretlenseydi süreç düştüğünde ikinci mail giderdi. Koşul
> (`whereNull`) ise 1D-K5'in tekrarı: birden çok `scheduler` konteyneri
> aynı siparişi görebilir ve `withoutOverlapping` yalnızca kendi süreci için
> geçerli (0.5'te ölçülmüştü).

**Dört kırma denemesi — biri yine bir testin yalanını ortaya çıkardı:**

| kırılan | düşen test |
|---|---|
| üst sınır kalktı | geçmişe mail bombardımanı |
| alt eşik kalktı | çok yeni sipariş |
| `failed` de dâhil edildi | ayrı hikâye |
| işaretleme gönderimden sonraya alındı | ⚠️ **hiçbiri** → test düzeltildi |

> ★ "Yarış koşulu" testi `hatirlat()` üzerinden yazılmıştı ve hiçbir şey
> ölçmüyordu: `bekleyenler()` zaten işaretlileri eliyor. Gerçek yarışı
> görebilmek için `hatirlatBir()` public yapıldı ve test iki koşucuyu
> doğrudan taklit ediyor. **2C ve 2E'deki dersin üçüncü tekrarı.**

**⚠️ Ölü savunma bulundu ve kaldırıldı.** Sorguda `whereNotNull('email')`
vardı; test `null` yazmayı denedi ve **veritabanı reddetti** — kolon zaten
`NOT NULL`. Savunma hiçbir şey yapmıyordu. Yerine `!= ''` kondu: boş metin
geçebiliyor ve gönderim sessizce düşerken sipariş "hatırlatıldı"
işaretlenirdi.

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

## ✅ FAZ 2 TAMAMLANDI

**440 test · lint · analyse · CI hepsi yeşil** (Faz 1 sonu: 326 — **+114**)

| blok | konu | durum |
|---|---|---|
| 2H | bildirim altyapısı | ✅ |
| 2G | KVKK — anonimleştirme ve veri indirme | ✅ |
| 2B | iade ve para iadesi | ✅ |
| 2A | kupon ve indirim | ✅ |
| 2C | arama | ✅ |
| 2D | koleksiyon | ✅ |
| 2E | yorum ve puan | ✅ |
| 2F | terk edilmiş ödeme | ✅ |

Mağaza artık yalnızca satmıyor: **konuşuyor** (mail) · yanlış giderse
**geri veriyor** (iade) · **bulunabiliyor** (arama) · **kendini düzenliyor**
(koleksiyon) · **güven üretiyor** (yorum) · **kaçanı geri çağırıyor**
(hatırlatma) · ve müşterinin verisini **silmeden unutabiliyor** (KVKK).

---

### Faz 2'nin taşıyıcı dersi — Faz 1'inkinin ÜSTÜNE

Faz 1'in dersi *"sessiz hata gürültülü hatadan tehlikelidir"* idi ve hâlâ
geçerli. Faz 2 bunun üstüne **yöntem** koydu.

#### ★ 1 · Kırma denemesi artık bir yöntem

Faz 1'de bozuk testler tesadüfen fark ediliyordu. Faz 2'de her blokta
sistematik olarak "kodu bozup testin kırıldığını görmek" yapıldı ve
**üç kez** bir testin yalanını ortaya çıkardı:

| blok | kırılan | olan |
|---|---|---|
| 2C | FTS kolu tamamen silindi | **hiçbir test kırılmadı** — trigram zaten buluyormuş |
| 2E | onaysız yorum sayacı | sayaç zaten `0`'dı, test hiçbir şey ölçmüyordu |
| 2F | işaretleme gönderimden sonraya alındı | `bekleyenler()` zaten eliyor, yarış hiç sınanmıyordu |

> **2C'de bu, tasarımı değiştirdi.** FTS kolunun silinmesi hiçbir testi
> kırmayınca ölçüldü: eşik 0,3'te trigram, FTS'in bulduğu her şeyi zaten
> buluyor. FTS'in işi *bulmak* değil **sıralamak** olarak yeniden
> tanımlandı ve `ts_rank` eklendi.

**Kural:** yeşil bir testi de kırmayı dene; kırılmıyorsa test yalan söylüyor.

#### ★ 2 · Gerçek HTTP, süitin göremediğini gösterdi — iki kez

```
2C   "tsiort" araması        testte ✓        gerçek markada 0 sonuç
     sebep: test verisinde 1 varyant, gerçek üründe 9 → türetilmiş
     metin uzadı, skor 0,33 → 0,286, ürün VARYANT SAYISI YÜZÜNDEN
     aranamaz oldu

2E   Accept başlığı yok      testte ✓        HER korumalı uçta 500
     sebep: 425 testin hepsi postJson kullanıyor, o başlığı otomatik
     ekliyor. Gerçek curl koşusu ortaya çıkardı.
```

**Kural:** iki kiracıda gerçek koşu süitin yerine geçmez, ama süitin
**göremediği yeri** gösterir.

#### ★ 3 · Sonradan eklenen kolon iki kez ısırdı

```
2C   geriye dönük doldurma unutuldu
     → arama hiçbir ESKİ ürünü bulmuyordu          sessiz EKSİKLİK

2F   geçmişteki TÜM pending siparişler "hatırlatılmamış" görünüyor
     → üst sınır konmasaydı ilk koşu aylar öncesine kadar
       herkese mail atardı                          sessiz SALDIRI
```

**Kural:** türetilmiş bir kolon eklerken iki soru sorulur — *kim
dolduracak* ve *boş hâli ne yapar*.

#### ★ 4 · Plan gerçekle çeliştiğinde plan güncellendi — üç kez

| karar | ne oldu |
|---|---|
| **2B** kargo iadesi | araştırma **benim önerimi** yanlışladı: tam caymada teslim masrafları da geri veriliyor |
| **2C** FTS'in rolü | bulmak → **sıralamak** (kırma denemesiyle ölçüldü) |
| **2F-K2** olay tüketimi | "olayları ilk tüketen iş" — tüketmedi, gerekmiyordu |

#### ★ 5 · Materyalleştirilmiş sayacın bedeli denetim — üç oldu

```
committed    (1D)    stok
used_count   (2A)    kupon
rating_avg   (2E)    puan
```

Üçü de gecelik denetleniyor ve **üçü de onarmıyor**: kendiliğinden
düzeltilseydi sayacı bozan kod yolu hiç görünmez, her gece sessizce
onarılır ve sorun kalıcı olurdu.

#### ★ 6 · Ölü savunma da bir hatadır

2F'de sorguda `whereNotNull('email')` vardı. Test `null` yazmayı denedi ve
**veritabanı reddetti** — kolon zaten `NOT NULL`. Savunma hiçbir şey
yapmıyormuş. Kaldırıldı, yerine gerçek risk kondu: boş metin geçebiliyor ve
gönderim sessizce düşerken sipariş "hatırlatıldı" işaretlenirdi.

---

### Faz 2'de tekrarlayan eski tuzaklar

Faz 1'in dersleri hâlâ iş görüyor; parantez içi **kaçıncı kez** ısırdığı:

| tuzak | nerede | |
|---|---|---|
| uzantı `public`'te, marka görmüyor | citext · ltree · **pg_trgm** | (3.) |
| Türkçe küçük harf tuzağı | e-posta · kupon kodu · **arama** | (3.) |
| kolon varsayılanı modele ulaşmaz | **koleksiyon · yorum** | (5.) |
| yarışı kontrol değil KİLİT çözer | **kupon · hatırlatma** | (3.) |
| yerel yeşil ≠ CI yeşil | **pg_trgm CI'a eklenmemişti** | (2.) |

---

### Faz 2'de açılan ve Faz 3'e devredenler

- **`tenants:backfill`** — genel amaçlı geriye dönük doldurma komutu.
  2C'de `search:reindex` olarak *tek iş için* yazıldı; üçüncü kez
  gerekince genelleştirilecek.
- **Sahip varsayılan parolası `123`** — geliştirme kolaylığı, üretimde
  olamaz.
- **On-demand TLS** — yeni marka hâlâ `Caddyfile`'a elle yazılıyor.

---

## Faz 3 — Satılabilirlik

> TıkMarka'yı **satılabilir bir ürün** hâline getiren faz. Bugün marka açmak
> terminalden `artisan tenant:create` çalıştırmak; sonunda bir ziyaretçi
> kendi mağazasını kurup parasını ödeyecek.

**Kararlar araştırmayla alındı** — İkas · Shopify · iyzico · Let's Encrypt ·
KVKK/TTK. Kaynaklar bölümün sonunda.

### Sıra ve gerekçesi

```
3A backfill      borç — ÖNCE gerekli: 3B'de kolon eklenince eski markalar
                 boş doğacak ve bu HATA VERMEYECEK
3B merkez tablo  timestamptz · jsonb · gerçek kolonlar
3C kontrol düzl. platform yöneticisi API'si — 3. guard
3D marka açma    self-servis kayıt · kartsız deneme
3E abonelik      iyzico · plan · yenileme · başarısız ödeme
3F kota          sınır UYGULANMAZSA plan anlamsız
3G yaşam döngüsü askı → kapatma → 1 yıl → silme
3H özel alan adı on-demand TLS — en sona, altyapısı %70 hazır
```

---

### 3-K1 · SINIR KAPASİTEYE KONUR, İŞLEME DEĞİL

```
✗ "ayda 200 sipariş"        201. sipariş geldiğinde ne yapacaksın?
                            reddedersen markanın SATIŞINI kestin
                            geçirirsen sınır zaten yok

✓ ürün sayısı · personel sayısı · özellik
                            marka 501. ürünü ekleyemez ama satışı sürer
```

> **⚠️ ARAŞTIRMA ÖNERİMİ ELEDİ.** İlk tasarımda "aylık sipariş" seçeneği de
> vardı. İkas ve Shopify'a bakıldı: **ikisinde de aylık sipariş limiti yok.**
> İkas ürün + kullanıcı sayısıyla ayırıyor (Start 100 ürün/1 kullanıcı →
> Scale sınırsız ürün/5 kullanıcı), Shopify personel sayısı + özellik
> derinliğiyle (1 → 5 → 15 → sınırsız).
>
> Sebebi açık: sipariş sınırı markanın **en iyi gününde** sistemi ona
> kapatır. Kampanyası tutan marka o gün seni bırakır.

### 3-K2 · ABONELİK iyzico'NUN KENDİ SİSTEMİYLE

```
ÜRÜN ("TıkMarka Mağaza")
  └── ÖDEME PLANI  price · paymentInterval · trialPeriodDays · recurrenceCount
        └── ABONELİK  (markanın kaydı)

durumlar : ACTIVE · PENDING · UNPAID · UPGRADED · CANCELED · EXPIRED
uçlar    : iptal · yükseltme · KART GÜNCELLEME · retry · sorgu
her tekrarlı ödemede WEBHOOK  → 1E'nin webhook disiplini burada tekrar
⚠️ duraklatma ucu YOK · yalnızca kredi kartı
```

> **Kendimiz yazsaydık** markanın kartını saklamamız gerekirdi — en riskli
> veri türü, en ağır yasal yükümlülük. Kart son kullanma tarihi, tekrar
> deneme mantığı, kart güncelleme ekranı… hepsi bize kalırdı.
>
> ⚠️ Kart bizim sistemimize **hiç girmiyor**.

### 3-K3 · DENEME BİZDE, ABONELİK SONRA — kartsız kayıt için

```
⚠️ TEKNİK KISIT: iyzico'da abonelik başlatmak bir ÖDEME İSTEĞİ.
   Tutar 0 olsa bile KART ZORUNLU. Yani "kartsız deneme" iyzico'nun
   içinde yapılamıyor.

seçilen:  kayıt → kart YOK → tenants.trial_ends_at = +14 gün
          14 gün her şey açık
          deneme biterken "devam için kart girin"
          kart girilince iyzico ABONELİĞİ BAŞLIYOR
```

> Alternatif, kayıtta kart isteyip `trialPeriodDays` kullanmaktı — daha az
> kod ama kayıt sürtünmesi çok daha yüksek.
>
> ⚠️ **Sonradan değiştirmesi pahalı:** diğerine geçmek, kart girmiş
> markaların aboneliklerini iyzico tarafında taşımak demek.

### 3-K4 · BAŞARISIZ ÖDEME KADEMELİ — ve VİTRİN AÇIK KALIR

```
gün 0     ödeme başarısız → iyzico UNPAID → webhook
gün 0-7   HER ŞEY AÇIK, yalnızca hatırlatma
            ⚠️ başarısız ödemelerin çoğu KASITLI DEĞİL — kart yenilenmiştir
gün 7-14  PANEL SALT-OKUNUR: marka görür, değiştiremez
          VİTRİN AÇIK → müşteriler alışverişe devam
gün 14+   ASKI
```

> **⚠️ SHOPIFY'DAN BİLİNÇLİ OLARAK AYRILIYORUZ.** Shopify donmuş mağazada
> panel de vitrin de kapatıyor. Biz vitrini açık bırakıyoruz çünkü **vitrini
> kapatmak markayı değil, markanın MÜŞTERİLERİNİ vuruyor:**
>
> - siparişini takip edemeyen müşteri
> - iade açamayan müşteri → 2B'de yazdığımız her şey erişilemez
> - parasını ödemiş, malı gelmemiş insan
>
> Bu insanların TıkMarka ile hiçbir sözleşmesi yok; faturayı ödemeyen marka.
> Sektör pratiği de tam kapatma yerine **kademeli bozulmayı** öneriyor:
> kapıyı tamamen kapatırsan marka geri dönmek için sebep bulamıyor.

### 3-K5 · WILDCARD SERTİFİKA YOK — ama TAVAN SESSİZ OLMAYACAK

```
Let's Encrypt: haftada 50 sertifika / KAYITLI ALAN ADI
  marka-a.tikmarka.com ┐
  marka-b.tikmarka.com ├─ HEPSİ tikmarka.com kotasından yiyor
  ...                  ┘
  51. marka → sertifika YOK → sitesi açılmıyor, BİR HAFTA boyunca
```

> Wildcard (`*.tikmarka.com`) bunu tek sertifikaya indirir ama **bedeli var
> ve ölçüldü:**
>
> | risk | wildcard | ayrı sertifika |
> |---|---|---|
> | DNS API anahtarı sunucuda | **gerekli** — çalınırsa tüm DNS ele geçer | gerekmiyor |
> | anahtar çalınırsa | **tüm markalar** taklit edilebilir | yalnızca o marka |
> | yenileme bozulursa | **hepsi birden** HTTPS'siz | biri etkilenir |
> | kapsam | yalnızca bir seviye (`a.b.tikmarka.com` ✗) | sınır yok |
>
> **Karar: bugün 50/hafta yeterli** (ayda ~200 yeni marka; yenilemeler bu
> kotadan sayılmıyor). Wildcard'ın güvenlik yoğunlaşmasını almaya gerek yok.
>
> ⚠️ **ŞART:** tavan sessiz olmayacak. Bugünkü tuzağımız tam bu — marka
> açılır, kayıt başarılı görünür, site açılmaz.
>
> ```
> merkez panel : "bu hafta açılan marka: 12 / 50"
> 40'ta        : uyarı
> 50'de        : yeni marka açma REDDEDİLİR
>                (kırık marka üretmektense açıkça hayır demek)
> ```
>
> Wildcard **ertelenmiş çözüm**; tetikleyicisi bu sayaç.

### 3-K6 · ÖZEL ALAN ADI VAR — DNS'i MARKA ekler, BİZ kontrol ederiz

```
✓ HAZIR   domains tablosu çoklu alan adı destekliyor (0.5)
✓ HAZIR   DomainCheckController — Caddy'nin "ask" ucu (0.5)
✗ eksik   Caddyfile'da on-demand TLS
✗ eksik   markanın "alan adımı bağla" ucu
✗ eksik   DNS doğrulama kontrolü
```

Akış:

```
1  marka panelde yazar: markasitesi.com     → durum BEKLİYOR
2  biz NET talimat veririz (CNAME/A kaydı, değerleriyle)
   ⚠️ bu adımı MARKA yapıyor, kendi alan adı panelinde
3  marka "Kontrol et"e basar → biz DNS'e sorarız
   bizi gösteriyorsa DOĞRULANDI, değilse AÇIK mesaj
4  ilk ziyarette Caddy bize sorar (ask) → "evet" → sertifika alınır
```

> **⚠️ "URL'de görünen değişsin, aslı aynı kalsın" YAPILAMAZ.** Tarayıcının
> en temel sözü, adres çubuğunda yazanın gerçekten bağlanılan yer olması.
> Bozulabilseydi tüm bankacılık çökerdi. Tek yakın yöntem iframe'e gömmek;
> o da SEO'yu öldürüyor, 3DS ödemeyi engelliyor (1E'de görüldü), çerezleri
> bozuyor ve zaten oltalama kalıbı.
>
> **Neden gerekli:** görüntüden ibaret değil — **Google bakıyor.** Marka
> `marka-a.tikmarka.com`'da kalırsa itibar bizim alan adımıza yazılır;
> marka ayrılırsa sıralamayı sıfırdan kurar. Kendi alan adı = markanın
> **taşınabilirliği**.
>
> ⚠️ 3. adım **destek yükü**: marka DNS panelini bilmiyorsa takılır.
> Kontrol sonucu açıkça gösterilmeli, sessizce beklememeli.
>
> ⚠️ Özel alan adları wildcard sorununa girmiyor — her biri **kendi** kayıtlı
> alan adına sayılıyor, yani kotamızdan yemiyor.

### 3-K7 · KAPANAN MARKA: 1 YIL DOKUNULMADAN, SONRA SİLİNİR

```
gün 0    mağaza kapandı
         ★ marka VERİSİNİ İNDİRİR (2G'deki dışa aktarma — tam yerine oturdu)
           10 yıllık yasal yükümlülüğünü kendi arşiviyle karşılar
gün 0-1yıl  ŞEMA DURUYOR, dokunulmuyor
            geri dönerse HER ŞEY yerinde — müşteri listesi dâhil
1 yıl    ŞEMA SİLİNİR
```

> **Yasal araştırma iki kural buldu ve ikisi de bizi ilgilendiriyor:**
>
> **A — saklama ZORUNLU.** Sipariş, fatura, ödeme kayıtları TTK ve VUK
> gereği **10 yıl** saklanmalı. Silmek yasak değil, tersine tutmak zorunlu.
>
> **B — ama yükümlülük KİMİN?**
> ```
> MARKA           veri SORUMLUSU    10 yıl saklama yükümlülüğü ONUN
> TıkMarka        veri İŞLEYEN      onun adına tutuyoruz,
>                                   kendi amacımızla kullanamayız
> ```
> ⚠️ Sözleşme bitince veri işleyenin veriyi sorumluya **iade edip silmesi**
> gerekiyor. KVKK Kurulu'nun 2021/1258 kararında tam bu durumda ceza var.
>
> **Sonuç:** 1 yıl saklamak meşru, **ama şartı var** — süre sözleşmede
> AÇIKÇA yazılı olacak ("mağaza kapatıldıktan sonra verileriniz 1 yıl
> saklanır, süre sonunda kalıcı olarak silinir"). Süre belirsiz bırakılırsa
> ya da "belki lazım olur" denirse risk doğuyor.
>
> Sözleşme metinlerini zaten sürümlüyoruz (1A.6, `legal_document_versions`)
> — madde oraya girecek.
>
> ⚠️ Kapanma anında **veri indirme teklifi** şart: markanın 10 yıllık
> yükümlülüğü ona devredilmiş olur ve 1 yıl sonra sildiğimizde kimse
> "arşivim gitti" diyemez.

### 3-K8 · PLATFORM YÖNETİCİSİ ÜÇÜNCÜ GUARD

```
customer   müşteri            (1A.2)
staff      marka personeli    (1A.2)
platform   BİZ                ← yeni
```

> ⚠️ Mevcut guard'lardan birine bindirilseydi bir marka personeli, kendi
> markasının sınırlarını değiştirebilir hâle gelirdi. 1A.2'de müşteri
> token'ının personel guard'ından reddedildiğini ölçmüştük; aynı kanıt
> burada da gerekiyor.

### 3-K9 · MERKEZ TABLO DÜZELTİLECEK — kendi kuralımızı ihlal ediyor

```
tenants.created_at   timestamp WITHOUT time zone   ← CLAUDE.md 2. kural
tenants.data         json (jsonb DEĞİL)            ← indekslenemez
```

> ⚠️ İkisi de **paketin varsayılan migration'ından** geliyor. Marka
> şemalarında `timestampsTz()` disiplinini uyguladık ama merkez tabloyu hiç
> açmamışız.
>
> Abonelik alanları `data` json'ına **konmayacak**, gerçek kolon olacak:
> `plan_id · status · trial_ends_at · suspended_at · closed_at`.
> json'a konsaydı "ödemesi geçmiş markalar" ya da "denemesi bugün biten
> markalar" sorgusu yazılamazdı — ve zamanlanmış görevlerin tamamı bu
> sorgulara dayanıyor.

---

### 3A — BİTTİ ✅ (15 test)

Kod: `app/Domain/Settings/DefaultsBackfill.php` ·
`app/Console/Commands/BackfillDefaults.php` ·
`tests/Tenancy/BackfillDefaultsTest.php`

```bash
php artisan tenants:run marka:eksikleri-tamamla --option="kuru=1"   # önce BAK
php artisan tenants:run marka:eksikleri-tamamla                     # sonra YAP
```

**⚠️ NAİF ÇÖZÜM FELAKET OLURDU.** İlk akla gelen "mevcut markalarda
`DefaultSettings::kur()` çalıştır" idi. O metot `yaz()` kullanıyor ve var
olanı **eziyor**; kırma denemesiyle ölçüldü — tek satır değişiklikle **dört
test birden** düştü:

| ezilen | sonuç |
|---|---|
| `is_published` → false | **AÇIK MAĞAZA KAPANIR**, tek koşuda, bütün markalarda |
| `fake_secret` yenilenir | yoldaki ödeme bildirimlerinin imzası geçersiz olur (1E.6 zinciri) |
| yasal taslak | markanın saatlerce yazdığı sözleşme metni silinir, yerine iskelet |
| vergi/kargo ayarı | markanın değiştirdiği değerler varsayılana döner |

Bu yüzden komut **eksik olanı ekler, var olana hiç dokunmaz**.

**Ölçüm — komutun gerçekten iş yaptığının kanıtı.** İki gerçek markada
`shipping.threshold_after_discount` eksikti (2A'da eklenmişti).
⚠️ Sonucu bu sefer zararsızdı çünkü okuyan kod `?? true` yazmış — yani
**şans eseri** doğruyduk. 1E.4'te aynı boşluk `fake_secret`'ta çıkmış ve
iki kiracıda gerçek HTTP koşusunu durdurmuştu.

**Özel durumlar:**
- `fake_secret` eksikse **rastgele** üretiliyor, marka başına ayrı — sabit
  olsaydı bir markanın ürettiği bildirim diğerinde de geçerli olurdu (1E.1)
- `is_published` eksikse **kapalı** yazılıyor — açık yazılsaydı hazırlık
  denetiminden geçmemiş mağaza kendiliğinden satışa açılırdı
- `store.name` eksikse **merkez kayıttaki** marka adıyla dolduruluyor;
  yer tutucu yazılsaydı marka onu vitrininde görürdü

**Kuru çalışma ayrı bayrak.** Geri dönüşü olmayan ve *bütün markalara*
dokunan bir işte önce göstermek, sonra yapmak.

**Doğrulandı (iki kiracıda gerçek koşu):** öncesi/sonrası karşılaştırıldı —
yayın durumu, vergi oranı, marka adı, imza anahtarı ve yasal taslaklar
**bit bit aynı** kaldı; yalnızca eksik ayar eklendi. İkinci koşu sessiz.

**Yol boyunca çıkan iki düzeltme:**
- `Setting` modelinde `@property SettingGroup $group` notu eksikti; statik
  analiz `casts()`'tan enum'u çıkaramıyor (Product'ta aynı not aynı sebeple
  var — CLAUDE.md'de yazılı tuzağın üçüncü örneği)
- `tenants:run "komut --bayrak"` çalışmıyor ("komut tanımlı değil");
  doğrusu `tenants:run komut --option="bayrak=1"` — kurala eklendi

---

### 3B — BİTTİ ✅ (9 test)

Kod: `database/migrations/landlord/2026_08_14_090000_fix_and_extend_tenants_table.php` ·
`app/Platform/Models/Tenant.php` · `app/Platform/Models/Plan.php` ·
`app/Enums/TenantStatus.php` · `app/Tenancy/Commands/CreateTenant.php` ·
`tests/Tenancy/MerkezTabloTest.php`

**Üç iş bir arada:** `timestamps()`→`timestamptz` · `json`→`jsonb` ·
abonelik/yaşam döngüsü kolonları.

> ⚠️ İlk ikisi paketin kendi migration'ından geliyordu. Marka şemalarında
> `timestampsTz()` disiplinini uyguladık ama **merkez tabloyu hiç açmamıştık** —
> kendi kuralımız kendi evimizde ihlal ediliyordu.

**★ EN ÖNEMLİ BULGU: kolon eklemek TEK BAŞINA hiçbir işe yaramıyor.**

Paketin `getCustomColumns()` varsayılanı `['id']`; geri kalan her alan `data`
json'ına gidiyor. Ölçüldü:

```
Tenant::create(['name' => 'X', 'status' => 'trial'])
  kolon  name=NULL  status=NULL            ← BOŞ
  data   {"name":"X","status":"trial"}     ← veri burada
  $tenant->name → 'X'                      ← model DOĞRU okuyor (!)
```

⚠️ Sinsi olan son satır: kod çalışıyor *gibi görünüyor*. Kırılan tek şey
**sorgu** — `where('trial_ends_at','<=',now())` hep boş döner, hata vermez.
Faz 3'ün bütün zamanlanmış görevleri tam olarak buna bakacak.

**★ İKİNCİ BULGU: kopyalamak yetmiyor, `data`'dan SİLMEK gerekiyor.**

```
kolon: 'KOLON DEGERI'         ← SQL sorgusunun gördüğü
data : {"name":"A Markası"}
$tenant->name → 'A Markası'   ← MODELİN gördüğü — data KAZANIYOR
```

Silinmeseydi iki kaynak sessizce ayrışırdı: panel adı değiştirir (kolona
yazılır), model eski adı okumaya devam ederdi. Migration `data - 'name'` ile
temizliyor.

**Kararların koda dönüşmüş hâli:**

| karar | kod |
|---|---|
| sınır ürün+personel, sipariş DEĞİL | `plans.max_products`, `max_staff` — sipariş kolonu yok |
| `null` = sınırsız | `Plan::asildiMi()` tek kapı; `0` kullanılsaydı "sıfır ürün" ile karışırdı |
| 14 gün **kartsız** deneme | `tenants.trial_ends_at` + `CreateTenant::DENEME_GUN` — deneme BİZDE, iyzico'da değil |
| 7 gün nezaket → askı | `grace_ends_at`, `suspended_at` |
| askıda vitrin AÇIK | `TenantStatus::panelAcikMi()` ≠ `satisAcikMi()` |
| 1 yıl saklama | `closed_at` |

**⚠️ `status` kolonunun VARSAYILANI YOK — bilinçli.** `default('active')`
yazılsaydı durum vermeyi unutan her yol sessizce "ödeyen müşteri" üretirdi.
`null` gürültülü: denetimde hemen görünür.

**Test yardımcısı gerçek komutla HİZALANDI.** `kiraciOlustur` durum yazmıyordu;
yani test markaları `status=NULL` doğuyordu ve panel kapısı kontrolleri testte
hiç sınanmazdı — 1E.4'te aynı ayrışma yaşanmıştı. Ayrıca "tenant:create deneme
durumunda açıyor" testi artık **gerçek komutu** çağırıyor, yardımcıyı değil.

**Dört kırma denemesi, dördü de yakalandı:**

| kırılan | düşen |
|---|---|
| `getCustomColumns()` override kaldırıldı | 3 test |
| listeden yalnızca `name` çıkarıldı | 3 test |
| `tenant:create` durum yazmadı | 1 test |
| `Plan`'dan `CentralConnection` kaldırıldı | 1 test |

⚠️ Kırma denemesi bir **test kırılganlığı** da ortaya çıkardı: hata veren test
`delete()`'e ulaşamayıp merkez tabloda kalıntı bıraktı ve sonraki koşular
*gerçek sebepten değil kalıntıdan* kırmızı kaldı (`tests/Tenancy/`'de
`RefreshDatabase` yok). Test artık kendi kalıntısını başta temizliyor.

**Yol boyunca çıkan iki kural** (CLAUDE.md'ye yazıldı):
- `tenants`'a kolon eklemek → `getCustomColumns()`'a da yazılır
- jsonb `?` operatörü PDO'da yazılamaz (`syntax error at or near "$1"`);
  `jsonb_exists(data, 'name')` kullanılır

---

### 3C — BİTTİ ✅ (16 test)

Kod: `app/Platform/Models/PlatformUser.php` · `app/Platform/TenantLifecycle.php` ·
`app/Http/Platform/{AuthController,TenantController}.php` ·
`app/Http/Middleware/RequireActiveTenant.php` ·
`app/Console/Commands/CreatePlatformUser.php` · `routes/platform.php` ·
`database/migrations/landlord/2026_08_14_140000_create_platform_users_table.php` ·
`tests/Tenancy/KontrolDuzlemiTest.php`

**★ ÜÇÜNCÜ KİMLİK ALANI.**

```
customer   markanın müşterisi    marka şeması    auth:customer
staff      markanın personeli    marka şeması    auth:staff
platform   BİZ                   MERKEZ şema     auth:platform   ← yeni
```

> ⚠️ Bu kimliğin yetkisi **bütün markalara** uzanıyor — sistemdeki en tehlikeli
> yetki. Marka personeliyle aynı tabloda tutulsaydı bir markanın sahibi kendini
> platform yöneticisi yapabilirdi. **Kayıt ucu yok**: yönetici yalnızca
> `platform:kullanici` komutuyla, yani sunucuya erişebilen kişi tarafından
> açılıyor (1A.2'nin panel kararıyla aynı).

**⚠️ Token tablosu merkez şemada da açıldı.** `personal_access_tokens` yalnızca
marka şemalarında vardı (1A.2); ölçüldü ve merkezde yoktu. Olmadan platform
girişi "tablo yok" ile düşerdi. Aynı adlı iki tablo iki ayrı şemada — bilinçli:
bir markanın token'ı merkez uçlarda denenemiyor.

**3C-K1 · Durum geçişleri KAPALI LİSTE.**

```
provisioning ─▶ trial ─▶ active ⇄ past_due ─▶ suspended
                  └────────┴──────────┴──▶ closed ──▶ active
```

> ⚠️ **Kapatılmış marka `trial`'a DÖNEMİYOR.** Dönebilseydi marka kapatıp
> yeniden açarak sonsuz ücretsiz kullanım elde ederdi — hata vermeden, tamamen
> meşru görünen iki işlemle.

**3C-K2 · Durum ve tarih BİRLİKTE yazılıyor.**
> Ayrı çağrılara bırakılsaydı biri unutulur ve "askıda ama askıya alma tarihi
> yok" kaydı oluşurdu. Aynı duruma tekrar geçişte tarih **tazelenmiyor**:
> tazelenseydi 1 yıllık silme sayacı her koşuda sıfırlanır ve hiç dolmazdı.

**3C-K3 · Askıda PANEL kapalı, VİTRİN AÇIK.**
> 4 numaralı kararın uygulaması (`marka-aktif` middleware). Vitrini de kapatmak
> markayı değil markanın **müşterilerini** vururdu: siparişini takip edemeyen,
> iade açamayan, parasını ödemiş insanlar — onların bizimle sözleşmesi yok.
> Shopify donmuş mağazada ikisini de kapatıyor; bilerek ayrıldık.
> ⚠️ `logout` ve `me` kapının **dışında**: askıdaki yönetici çıkış yapabilmeli
> ve hesabının durumunu görebilmeli.

**★ GERÇEK HTTP KOŞUSU BİR HATA YAKALADI — testlerin göremediği.**

Rotalar önce `routes/web.php` içindeydi; **16 testin hepsi yeşildi** ama gerçek
`curl` isteği `CSRF token mismatch` aldı. Sebep: `web` grubu CSRF koruması
uyguluyor, testler ise `postJson` kullanıyor.

⚠️ Bu karar **1A.2'de zaten verilmişti** ("api grubu, web değil") ve 3C'de
tekrar unutuldu. Yani yorum yetmiyor — rotalar `routes/platform.php`'ye taşındı
ve **middleware listesini ölçen bir test** eklendi.

**Dört kırma denemesi + bir dürüstlük notu:**

| kırılan | düşen |
|---|---|
| platform uçları `auth:staff`'a çevrildi | 4 test |
| geçiş listesi serbest bırakıldı | 2 test |
| `marka-aktif` vitrine de takıldı | 1 test |
| aynı duruma geçişte tarih tazelendi | 1 test |

⚠️ **Birinci kırma bir testin sınırını gösterdi:** "marka personeli merkeze
giremiyor" testi `auth:staff`'a çevrildiğinde bile **yeşil kaldı**. Sebep ikinci
katman — personel token'ları marka şemasında, merkez bağlamda o tablo başka.
Koruma çift katmanlı ama test ikisini ayırt etmiyor; test yorumuna dürüstçe
yazıldı.

**Doğrulandı (gerçek HTTPS, iki kiracı):** yönetici açıldı, giriş yapıldı,
marka listesi ve ada göre arama çalıştı, token'sız istek 401 aldı. B markası
askıya alındı → **panel 403, vitrin 200, `panel/me` 200**; geçersiz geçiş 409;
geri açma çalıştı.

---

### 3D — BİTTİ ✅ (13 test)

Kod: `app/Platform/TenantProvisioning.php` · `app/Platform/ReservedSubdomains.php` ·
`app/Http/Platform/SignupController.php` · `routes/platform.php` ·
`app/Tenancy/Commands/CreateTenant.php` (servise devredildi) ·
`tests/Tenancy/MarkaAcmaTest.php`

**⚠️ PLANIN TAHMİNİ ÖLÇÜMLE YANLIŞLANDI — kuyruk GEREKMİYOR.**

Plan "şema açma + migration UZUN → kuyruk" diyordu. Ölçüldü:

```
şema + 28 migration : 240 ms
varsayılanlar       :  39 ms
```

Senkron akış hem daha basit hem de kullanıcıya "hazır" diyebilmenin tek dürüst
yolu — kuyrukta olsaydı kayıt biter, mağaza henüz olmazdı. Karar: **senkron**.
(`provisioning` durumu yine de kullanılıyor: saniyenin altında yaşıyor ama
kurulum patlarsa kalıcı iz bırakıyor.)

**3D-K1 · KOMUT ve KAYIT UCU aynı yolu kullanıyor.**
> `tenant:create` artık kendi kurulumunu yazmıyor, `TenantProvisioning`'i
> çağırıyor. ⚠️ İki yol ayrışsaydı bu **sessiz** olurdu — 1E.4'te `markaKur`
> ile `tenant:create` tam böyle ayrışmış ve testler gerçekte var olmayan bir
> markayı ölçmüştü. Bir test bunu **yapısal olarak** ölçüyor (komut kaynağında
> `DefaultRoles`/`DefaultSettings` geçmiyor).

**3D-K2 · AYRILMIŞ alt alan adları — iki ayrı tehlike.**
```
panel, platform, admin, api    → kendi adresimizi kaybederiz
www, mail, secure, odeme       → "resmi TıkMarka sayfası" hissi = oltalama
```
> ⚠️ Karşılaştırma **slug üzerinden**: `PANEL` de yakalanıyor.
> ⚠️ Adı gerçekten "Panel" olan marka **reddedilmiyor**, sonek alıyor
> (`panel-magaza`) — meşru müşteriyi kapıda çevirmek olurdu.

**3D-K3 · Sahip KENDİ parolasını belirliyor.**
> `tenant:create`'teki `123` varsayılanı self-servis akışta **yok** (en az 8
> karakter). Olsaydı internetten açılan her marka aynı bilinen parolayla
> doğardı. Komuttaki varsayılan geliştirme kolaylığı olarak duruyor.

**3D-K4 · HAFTALIK TAVAN gürültülü — 3-K5'in uygulaması.**
> `HAFTALIK_TAVAN = 45` (Let's Encrypt sınırı 50; elle açılanlar ve tekrar
> denemeler için pay). Aşılınca **503 + `Retry-After`**.
> ⚠️ Tavan olmasaydı marka açılır, panel çalışır ama **site açılmazdı** —
> bugünkü Caddyfile tuzağının ölçekli ve tamamen sessiz hâli.
> ⚠️ 422 değil 503: kullanıcının verisinde sorun yok, sorun bizde.

**Türkçe slug ölçüldü:** `Ayşe'nin Butiği` → `aysenin-butigi`,
`ÇİÇEK Dünyası` → `cicek-dunyasi`, `Işıl Takı` → `isil-taki`. Doğru çalışıyor —
ama `Işıl` ve `İsil` aynı slug'a düşüyor, o yüzden çakışma soneki **zorunlu**.

**Dört kırma denemesi — biri yine bir testin yalanını ortaya çıkardı:**

| kırılan | düşen |
|---|---|
| ayrılmış ad kontrolü kalktı | 1 test |
| haftalık tavan kalktı | 1 test |
| durum kurulum öncesi `trial` yazıldı | 13 test |
| **temizlik bloğu silindi** | ⚠️ **hiçbiri** → test düzeltildi |

> ★ "Kurulum yarıda kalırsa arkası toplanıyor" testi boş alan adıyla
> yazılmıştı ve hiçbir şey ölçmüyordu: boş ad **doğrulamada** yakalanıyor,
> yani marka hiç oluşmuyor. Test "arkası toplandı"yı değil "hiç başlamadı"yı
> ölçüyordu. Şimdi 260 karakterlik alan adı kullanılıyor — doğrulamadan geçiyor
> ama veritabanına yazılamıyor, yani marka satırı ve şeması oluştuktan **sonra**
> patlıyor. Test artık şema kalıntısını da kontrol ediyor.
> **2C · 2E · 2F · 3B'den sonra beşinci kez.**

**Doğrulandı (gerçek HTTPS):** `panel` müsaitlik kontrolü `reserved` döndü ·
kayıt `aysenin-butigi.localhost` üretti · sahip **kendi parolasıyla** panele
girdi (200) · eski `123` parolası reddedildi (422) · vitrin kapalı doğdu (503) ·
ayrılmış ad 422.

---

### 3E — BİTTİ ✅ (20 test)

Kod: `app/Platform/Subscription/` (arayüz, `FakeSubscriptionProvider`,
`SubscriptionService`, fabrika, DTO'lar) · `config/subscription.php` ·
`app/Http/Platform/{SubscriptionController,SubscriptionWebhookController}.php` ·
`app/Console/Commands/{SeedPlans,ExpireTrials,ExpireGracePeriods}.php` ·
`tests/Tenancy/AbonelikTest.php`

**⚠️ 1E İLE KARIŞTIRILMAMASI GEREKEN İKİ AYRI YÖN:**

```
1E  marka → kendi iyzico hesabıyla → KENDİ MÜŞTERİSİNDEN tahsil
    anahtarlar MARKA settings'inde, her markada AYRI

3E  BİZ  → kendi iyzico hesabımızla → MARKADAN tahsil
    anahtar MERKEZDE (config/subscription.php), TEK
```

> Tek arayüzde birleştirilseydi hangi anahtarın kullanılacağı çağrı yerine
> bağlı kalırdı; bir gün biri karıştırır ve **markanın parası bize, bizim
> paramız markaya** giderdi. Ayrıca anahtarlar `settings`'e konmuyor: o tablo
> marka şemasında ve marka personeli okuyabiliyor.

**Zaman çizgisi:**
```
kayıt ──▶ trial (14 gün, KARTSIZ)
            │ kart girilir
            ▼
          active ◀──────────┐
            │ ödeme başarısız│ düzeldi
            ▼                │
          past_due (7 gün) ──┘
            │ nezaket doldu
            ▼
          suspended    panel kapalı · VİTRİN AÇIK
```

**3E-K1 · İptal SAĞLAYICIDA da yapılıyor — en pahalı sessiz hata.**
> Yalnızca kendi kaydımızı kapatsaydık iyzico **her ay çekmeye devam ederdi**:
> marka ayrıldığını sanarken parası gitmeye devam ederdi. Test bunu sağlayıcıya
> **sorarak** doğruluyor; kendi kaydımıza bakmak bu iddiayı hiç ölçmezdi.

**3E-K2 · Tekrarlayan başarısızlık nezaket süresini UZATMIYOR.**
> Uzatılsaydı her başarısız denemede sayaç sıfırlanır ve marka sonsuza kadar
> askıya alınmazdı — ödemeden kullanmaya devam ederdi.

**3E-K3 · Bilinmeyen referansta 200.**
> 404 dönseydi sağlayıcı tekrar tekrar denerdi. 1E.6'da webhook zinciri tam
> böyle kırılmış ve **tahsilat hiç kaydedilmemişti**.

**3E-K4 · Denetim sağlayıcıyla kendi kaydımızı karşılaştırıyor.**
> `committed` (1D) ve `rating_avg` (2E) denetimlerinin üçüncüsü.
> ⚠️ `past_due` ile `suspended` fark sayılmıyor: nezaket süresi dolmuş marka
> bizde askıda, sağlayıcıda hâlâ `unpaid` olabiliyor.

**★ GERÇEK HTTP KOŞUSU İKİ HATA YAKALADI — 18 test yeşilken.**

| bulgu | sebep |
|---|---|
| ikinci abonelik → **500** (409 olmalı) | `AlreadySubscribedException` eşlenmemişti; testler servisi *doğrudan* çağırıp istisnayı yakalıyordu, uçtan hiç geçmiyorlardı |
| imzasız webhook → **400** (401 olmalı) | imza anahtarı boştu ve hata "senin gönderdiğin bozuk" diyordu — oysa sorun **bizde** |

İkincisi daha sinsi: üretimde bütün bildirimler sessizce reddedilir ve kimse
sebebini anlamazdı. Ayrı bir istisna açıldı (`MissingSubscriptionSecretException`)
→ **500 + `Log::critical`**. İkisi için de test yazıldı.

**★ İKİ ÖLÜ SAVUNMA BULUNDU ve kaldırıldı/ölçüldü** (2F'deki dersin tekrarı):

| ölü kod | ölçüm |
|---|---|
| serviste "zaten `past_due` ise dokunma" | kaldırıldı, hiçbir test düşmedi — asıl koruyan `TenantLifecycle::gecir()`, test oraya taşındı |
| `abonelik:deneme-denetle`'de `subscription_ref IS NULL` | kaldırıldı, hiçbir test düşmedi — **şart tutuldu** ama artık durumu elle tutarsız kuran gerçek bir test var |

**Bir test kırılganlığı da düzeltildi:** 3D'nin "yarıda kalırsa temizlenir"
testi *tüm* `tenant_%` şemalarını sayıyordu — tek başına yeşil, tam süitte
kırmızı. Artık önce/sonra **sayı farkına** bakıyor.

**Doğrulandı (gerçek HTTPS):** 3 plan kuruldu ve listelendi · B markası `buyume`
planına abone edildi (`active`) · ikinci abonelik **409** · imzasız webhook
**401** · iptal `closed` yaptı · marka geri açıldı ve vitrini 200.

⚠️ **Gerçek iyzico abonelik sağlayıcısı bu blokta YAZILMADI** — 1E'nin deseni:
önce sahte sağlayıcıyla tam akış, gerçek sağlayıcı ve sandbox doğrulaması ayrı
adım (1E.7 gibi). Arayüz hazır, tek eksik `IyzicoSubscriptionProvider`.

---

### 3F — BİTTİ ✅ (12 test)

Kod: `app/Domain/Quota/{QuotaGuard,QuotaExceededException}.php` ·
`app/Platform/PlanQuotaGuard.php` · `ProductService` · `StaffService` ·
`CollectionService` · `ReviewService` · `tests/Tenancy/KotaTest.php`

**★ BAĞIMLILIK TERS ÇEVRİLDİ — M-2.7 korunuyor.**

```
app/Domain/Quota/QuotaGuard.php     arayüz    ← iş mantığı bunu çağırıyor
app/Platform/PlanQuotaGuard.php     uygulama  ← planı merkezden okuyor
```

> Kota markanın planına bakıyor ve plan MERKEZ kayıtta; ama `app/Domain/`
> kiracıdan habersiz olmak zorunda. Ölçüm hâlâ **sıfır**: iş mantığı "kotam
> var mı" diye soruyor, planın nereden geldiğini bilmiyor.
>
> ⚠️ Ölçüm bir kez kirlendi ve bu da öğreticiydi: `QuotaGuard`'ın **yorumunda**
> kiracılık yardımcılarının adı geçiyordu ve tarama onları da saydı. Kendi
> belgemiz ölçümü bozarsa bir sonraki koşu yanlış alarm verir — yorum
> yeniden yazıldı.

**3F-K1 · Kontrol SERVİSTE, controller'da DEĞİL.**
> Controller'a yazılsaydı tohumlayıcı, artisan komutu ve içe aktarma yolları
> sınırı **atlardı** — plan satmanın anlamı kalmazdı ve bu hiçbir yerde
> görünmezdi. Bir test HTTP'den hiç geçmeden doğrudan servisi çağırarak
> bunu ölçüyor.

**3F-K2 · Kota YENİ eklemeyi engelliyor, VAR OLANI silmiyor.**
> Plan düşüren marka verisini kaybetmemeli. Koleksiyon özelliği kapanınca
> mevcut koleksiyonlar listelenmeye devam ediyor, yalnızca yenisi açılamıyor.

**3F-K3 · Tanımsız özellik KAPALI sayılıyor.**
> Açık sayılsaydı plana yeni bir özellik eklendiğinde **eski planlar onu
> sessizce kazanırdı** — hiçbir yerde görünmeden.

**3F-K4 · Denemede plan ATANMIŞ OLSA BİLE deneme sınırları geçerli.**
> Plan okunsaydı, kontrol düzleminden plan atanan bir deneme markası
> **ödemeden** o planın sınırlarını kullanırdı.

**★ İKİ HATA TESTLERLE ORTAYA ÇIKTI:**

**1 · İki farklı `null` aynı değere binmişti.** "Kiracı yok" (merkez bağlam)
ile "plan yok" (deneme) ikisi de `plan() === null` idi. Sonuç: merkez bağlamda
çalışan bakım komutları **deneme sınırına takılıyordu** — `tenants:run` ile
koşan her komut, tohumlayıcı ve veri taşıma 100 üründen sonra kırılırdı.
Ayrı bir `kotaDisi()` metodu açıldı. *(`null` = sınırsız tuzağının kardeşi.)*

**2 · `DENEME_PERSONEL = 1` deneme markasını felç ediyordu.** En düşük planla
aynı yapılmıştı; `IzinTest`'teki "sahip personel davet edebiliyor" testi
kırıldı. Marka, satın alma kararını vereceği 14 gün boyunca personel
yönetimini **hiç deneyemezdi** — tam da satın alma sebebi olan özelliği.
3'e çıkarıldı ve kendi testi yazıldı.

**Deneme sınırları:** 100 ürün · 3 personel · tüm özellikler açık.
> Sınırsız olsaydı biri deneme hesabıyla yüz binlerce ürün yükleyip terk
> ederdi; şemayı ve yedeklemeyi biz taşırdık.

**Dört kırma denemesi, dördü de yakalandı:**

| kırılan | düşen |
|---|---|
| ürün kotası kontrolü kalktı | 5 test |
| `null` sınırsız değil sıfır sayıldı | 1 test |
| tanımsız özellik açık sayıldı | 1 test |
| denemede plan okundu | 1 test |

**Bir test kalıntısı da düzeltildi:** `planliMarka` yardımcısı `firstOrCreate`
kullanıyordu; plan düşürme senaryosu planı değiştirip bırakınca sonraki koşu
*gerçek sebepten değil kalıntıdan* kırmızı kalıyordu. `updateOrCreate` oldu.
*(3B'deki kalıntı sorununun ikincisi.)*

**Doğrulandı (gerçek HTTPS):** A markasına `baslangic` planı atandı, sınır
geçici olarak 5'e çekildi → yeni ürün **402** ve cevapta `quota: products`,
`limit: 5`. Sınır 100'e geri alındı, test ürünü temizlendi.

---

### 3G — BİTTİ ✅ (12 test)

Kod: `app/Platform/TenantPurge.php` ·
`app/Console/Commands/{PurgeClosedTenants,PurgeOrphanStorage}.php` ·
`app/Tenancy/Commands/DeleteTenant.php` · `routes/console.php` ·
`tests/Tenancy/YasamDonguTest.php`

**★ BU BLOKTAKİ HER İŞLEM GERİ ALINAMAZ.** Projedeki diğer bütün "tehlikeli"
işlemler geri alınabilirdi; bu değil. Bu yüzden **varsayılan hiçbir şey
yapmamak**:

```
marka:silinecekleri-temizle              → yalnızca GÖSTERİR
marka:silinecekleri-temizle --onayla     → siler
tenant:delete <alan-adı>                 → yalnızca GÖSTERİR
tenant:delete <alan-adı> --onayla        → siler
```

> ⚠️ 3A'da kuru çalışma **ayrı bir bayraktı** (`--kuru`) çünkü o komut yazma
> yapıyordu ve geri alınabilirdi. Burada tersine çevrildi.

**3G-K1 · Üç şart da zorunlu, üçü de ayrı bir felaketi engelliyor.**
```
status = closed        askıdaki ya da ödeyen marka silinmesin
closed_at NOT NULL     ⚠️ aşağıda
closed_at <= sınır     süresi dolmamış marka silinmesin
```

**3G-K2 · Silme = şema + dosyalar + merkez kayıt, TEK yoldan.**
> `tenant:delete` ve zamanlanmış temizlik aynı servisi çağırıyor. İki ayrı yol
> yazılsaydı biri dosyaları unuturdu — **bugün diskte tam bundan 38 öksüz
> klasör vardı** (ölçüldü: 40 klasör, 2 gerçek marka). 1A'dan devredilen borç.

**3G-K3 · Marka silme ZAMANLANMIYOR.**
> Öksüz dosya temizliği haftalık koşuyor, ama marka silme **elle**. Geri
> alınamaz bir işlem gece kendiliğinden koşmamalı.

**⚠️ `whereNotNull('closed_at')` BUGÜN ÖLÜ — ölçüldü, ve yine de duruyor.**

Kırma denemesinde kaldırıldı, hiçbir test düşmedi. Sebep PostgreSQL:
`SELECT (NULL::timestamptz <= now())` → `NULL`, satır zaten `WHERE`'den düşüyor.

Bu, 2F ve 3E'deki "ölü savunmayı kaldır" kararından **bilinçli bir sapma**:

| blok | durum | karar |
|---|---|---|
| 2F | kolon `NOT NULL` → senaryo **imkânsız** | kaldırıldı |
| 3E | başka bir yer zaten koruyor | kaldırıldı |
| 3G | senaryo **mümkün**, koruma **dolaylı** (SQL semantiği) | **tutuldu** |

Fark: burada koruma "SQL'in NULL davranışını bilmene" bağlı ve işlem geri
alınamaz. Açık yazmak hem okunabilirlik hem ikinci kapı.

---

**★★ BU BLOK GERÇEK HASAR VERDİ — ve ders bundan çıktı.**

Test `--onayla` ile öksüz temizliği çalıştırdı ve **geliştirme ortamındaki
gerçek marka klasörlerini sildi**: 3 ürün görseli gitti (veritabanı kaydı
kaldı), `storage/framework` de silinip test süiti çöktü
(`Please provide a valid cache path`).

```
veritabanı testte AYRI   tikmarka_test
disk testte AYRI DEĞİL   aynı storage/
```

**Düzeltme:** dosya silen servis artık **kök parametresi** alıyor; test kendi
geçici klasöründe çalışıyor. **Onarım:** `storage/framework` yeniden kuruldu,
dosyasız kalan görsel kayıtları temizlendi (vitrin `null` görsel döndürüyor,
kırık bağlantı yok). **Kural** CLAUDE.md'ye yazıldı.

⚠️ Ayrıca `dosyalariSil()` artık boş kimliği reddediyor — boş olsaydı yol
`storage/tenant` olur ve yanlış klasör silinebilirdi.

**Dört kırma denemesi:**

| kırılan | düşen |
|---|---|
| durum şartı kalktı | 1 test |
| dosya silme kalktı | 1 test |
| `tenant` ön ek kontrolü kalktı (sistem klasörleri!) | 1 test |
| `whereNotNull` kalktı | ⚠️ **hiçbiri** → ölü olduğu ölçüldü, gerekçesi yazıldı |

**Doğrulandı (gerçek koşu):** sahte öksüz klasör oluşturuldu → onaysız komut
gösterdi ve silmedi → `--onayla` sildi → `storage/app`, `framework`, `logs`
yerinde. İki vitrin 200.

**⚠️ Bu blokta YAPILMAYAN:** marka verisinin dışa aktarılması (7 numaralı
kararın "kapanışta veri indirme" parçası). 2G'deki `DataExporter` müşteri
verisi için; marka geneline genişletmek ayrı bir iş ve Faz 3'ün kalanına
bakılarak karar verilecek.

---

### 3H — BİTTİ ✅ (12 test)

Kod: `app/Platform/Domains/` (`DnsChecker`, `SystemDnsChecker`,
`FakeDnsChecker`, `CustomDomainService`) · `app/Platform/Models/Domain.php` ·
`app/Http/Panel/DomainController.php` · `DomainCheckController` (kapatıldı) ·
`docker/Caddyfile` · `tests/Tenancy/OzelAlanAdiTest.php`

**★ AKIŞ — 6 numaralı kararın uygulaması:**

```
1  marka panelde alan adını yazar     → kayıt, verified_at = null
2  biz TALİMAT veririz                → CNAME · A · TXT (üçünden biri)
3  marka kendi DNS panelinde ekler    ← BİZİM erişimimiz YOK
4  marka "kontrol et" der             → DNS sorgusu
5  doğruysa verified_at dolar         → ask ucu artık 200 diyor
6  ilk ziyarette Caddy sertifika alır (on-demand TLS)
```

**★★ ASIL İŞ: `ask` UCUNU KAPATMAK.**

Uç 0.5'te yazılmıştı ama **doğrulanmamış alan adına da 200 diyordu**. On-demand
TLS o hâlde açılsaydı, marka paneline `google.com` yazan biri yüzünden Caddy o
alan adı için ACME doğrulaması dener, düşer ve **Let's Encrypt kotamız yanardı**
— haftada 50 sertifikayla sınırlıyız (3-K5).

> ⚠️ Uç TLS el sıkışmasının **kritik yolunda**: yalnızca veritabanına bakıyor,
> DNS sorgusu yapmıyor. Yapsaydı her yeni bağlantı bir ağ turu kadar beklerdi.

**3H-K1 · ÜÇ YOLDAN BİRİ yeterli: CNAME · A · TXT.**
> Tek yol dayatılsaydı markaların bir kısmı alan adını hiç bağlayamazdı: bazı
> sağlayıcılar kök alan adında CNAME'e izin vermiyor.

**3H-K2 · Belirteç alan adı başına RASTGELE.**
> Sabit olsaydı bir markanın belirtecini gören başkası kendi alan adını
> doğrulatabilirdi.

**3H-K3 · Başarısız kontrol 200 dönüyor, 4xx DEĞİL.**
> En olağan durum bu — DNS değişikliği yayılmamış olabiliyor. 4xx dönseydi panel
> "bir şeyler bozuk" gösterirdi. Talimat da cevapta tekrar dönüyor: 3. adım
> **insan işi** ve destek yükünün tamamı orada.

**3H-K4 · Merkez alan adlarımız ve ayrılmış adlar ALINAMIYOR.**
> Alınabilseydi marka kendi paneline merkez adresimizi yazar, kapı görevlisi
> merkez isteklerini o markaya yönlendirir ve **kontrol düzlemimizi
> kaybederdik**. Kök alan adımızın altı da kapalı — 3D'deki ayrılmış adlar
> listesinin arka kapısı olurdu.

**3H-K5 · SON alan adı silinemiyor.**
> Silinseydi marka hiçbir adresten erişilemez hâle gelir ve paneline girip
> düzeltemezdi (1A.3'teki "sahip kendi rolünden `staff.manage`'i kaldıramaz"
> ile aynı düşünce).

**★ İKİ HATA TESTLERLE ORTAYA ÇIKTI:**

**1 · Kolon eklemek yetmedi — cast yok.** `verified_at` bir **metin** olarak
geliyordu ve `?->toIso8601String()` "Call to a member function on string" ile
patlıyordu. Paketin `Domain` modeli bizim kolonumuzu bilmiyor; kendi modelimiz
yazıldı ve `tenancy.domain_model` ona çevrildi. *(3B'de `tenants` tablosunda
aynı ders `getCustomColumns()` ile çıkmıştı.)*

**2 · Yeni açılan markaların alan adı doğrulanmamış doğuyordu.** Migration
mevcut kayıtları doldurmuştu ama **ileriye dönük yolu düzeltmiyordu**: on-demand
TLS açıldığı an yeni açılan her marka sertifika alamazdı. `TenantProvisioning`
artık kendi alan adını doğrulanmış yazıyor (DNS'ini zaten biz yönetiyoruz), test
yardımcısı da hizalandı.

**Dört kırma denemesi — ikisi TESTİN zayıflığını gösterdi:**

| kırılan | sonuç |
|---|---|
| ask ucu doğrulanmamışa 200 dedi | 1 test düştü ✓ |
| belirteç sabitlendi | 1 test düştü ✓ |
| merkez alan adı kontrolü kalktı | ⚠️ **hiçbiri** → test `localhost` kullanıyordu, o zaten "nokta yok" diye eleniyormuş; `127.0.0.1`'e çevrildi |
| doğrulama tarihi her kontrolde tazelendi | ⚠️ **hiçbiri** → iki çağrı aynı saniyedeydi; tarih geriye çekilerek ölçülür oldu |

**⚠️ GELİŞTİRMEDE SINANAMAYAN kısım dürüstçe kaydediliyor:** `.localhost`
adreslerine Let's Encrypt sertifika veremiyor, Caddy kendi iç otoritesini
kullanıyor. `on_demand_tls` bloğu yazıldı ve Caddy sorunsuz yükledi, ama
**gerçek sertifika alma akışı ancak gerçek bir alan adında sınanabilir**.
Bugün ölçülen: ask ucu doğru cevap veriyor (doğrulanmış 200, doğrulanmamış 404).

⚠️ Caddyfile'da ilk denemede **ikinci bir global blok** açılmıştı; Caddy
yapılandırmayı hiç yüklemezdi. Mevcut bloğa taşındı.

**Doğrulandı (gerçek HTTPS):** `marka-a.localhost` → 200 · `hicyok.example` →
404 · marka panelden `gercek-magaza.example` ekledi, talimat (CNAME/A/TXT)
cevapta geldi, doğrulanmamışken ask ucu **404** verdi · kayıt silindi (204).

---

### Bitiş ölçütü

Bir ziyaretçi siteye gelir, mağazasını **kendisi** kurar, 14 gün kartsız
dener, kartını girer, aboneliği başlar, planının sınırına dayanır, üst plana
geçer; ödemesi başarısız olursa kademeli olarak kısıtlanır; isterse kendi
alan adını bağlar; ayrılırsa verisini indirir ve bir yıl sonra izi silinir.

**Devredilen borçlar bu fazda kapanıyor:** `tenants:backfill` (3A) ·
sahip varsayılan parolası `123` (3D) · Caddyfile'a elle alan adı (3H).

---

##### Bu kararların dayandığı kaynaklar

> · **iyzico** — [Abonelik](https://docs.iyzico.com/urunler/abonelik/abonelik-entegrasyonu)
>   (ürün → plan → abonelik, webhook) ·
>   [Ödeme planı](https://docs.iyzico.com/urunler/abonelik/abonelik-entegrasyonu/odeme-plani)
>   (`trialPeriodDays`, `paymentInterval`, `recurrenceCount`) ·
>   [Abonelik işlemleri](https://docs.iyzico.com/on-hazirliklar/api-reference-beta/abonelik/abonelik/abonelik-islemleri)
>   (durumlar, iptal/yükseltme/kart güncelleme)
> · **ikas** — [e-ticaret paketleri](https://ikas.com/tr/e-ticaret-paketleri)
>   (ürün + kullanıcı sınırı, sipariş sınırı YOK)
> · **Shopify** — [plan limitleri](https://craftshift.com/shopify-limits-2026-complete-guide/) ·
>   [donmuş mağaza](https://help.shopify.com/en/manual/your-account/manage-billing/billing-charges/frozen-store)
>   (panel de vitrin de kapalı — biz ayrıldık) ·
>   [veri saklama](https://help.shopify.com/en/manual/your-account/manage-orgs-and-stores/manage-pricing-plan/deactivate-store)
>   (2 yıl)
> · **Let's Encrypt** — [hız sınırları](https://letsencrypt.org/docs/rate-limits/)
>   (50/kayıtlı alan adı/hafta; yenilemeler ayrı)
> · **Caddy** — [on-demand TLS](https://caddyserver.com/on-demand-tls)
>   (`ask` ucu hem kötüye kullanımı engelliyor hem Caddy'nin kendi
>   sınırlarını devre dışı bırakıyor)
> · **Dunning** — [SaaS pratiği](https://baremetrics.com/blog/ultimate-dunning-management-guide)
>   (3-7 gün nezaket, 10-14 günde askı, tam kapatma yerine kademeli bozulma)
> · **KVKK / TTK** — [saklama süreleri](https://nitelikliveri.com/kvkk-kavramlar/kanunlara-gore-kisisel-verilerin-saklanma-sureleri/)
>   (sipariş/fatura 10 yıl) ·
>   [veri sorumlusu ve veri işleyen](https://www.cottgroup.com/tr/blog/kvkk-gdpr/item/kvkk-ve-gdpr-kapsaminda-veri-sorumlusu-ve-veri-isleyen) ·
>   [Kurul kararı 2021/1258](https://www.kvkk.gov.tr/Icerik/7286/2021-1258)
>   (sözleşme bitince veri işleyen silmeli)

---

## ✅ FAZ 3 KAPANIŞ — satılabilirlik

**549 test · lint · analyse · CI hepsi yeşil.** (Faz 2 sonu 440 → **+109**)

| blok | ne getirdi | test |
|---|---|---|
| 3A | eksik varsayılanları tamamlama — var olanı ezmeden | 15 |
| 3B | merkez tablo: timestamptz · jsonb · abonelik alanları | 9 |
| 3C | kontrol düzlemi: üçüncü kimlik alanı, marka yaşam döngüsü | 16 |
| 3D | self-servis marka açma: komut ve kayıt ucu tek yoldan | 13 |
| 3E | abonelik: plan, deneme, nezaket, iptal, denetim | 20 |
| 3F | plan kotaları — alan adı bilmeyen bir kapı görevlisi | 12 |
| 3G | kapatılan markanın kalıcı silinmesi | 12 |
| 3H | özel alan adı: doğrulama ve on-demand TLS | 12 |

Faz 2'de mağaza satabiliyordu ama **ürünü biz açıyorduk** (elle `tenant:create`).
Faz 3'ten sonra ürün kendi kendini satıyor: ziyaretçi gelir → mağazasını kendi
kurar → 14 gün kartsız dener → kartını girer → sınıra dayanır, yükseltir →
ödemesi düşerse kademeli kısıtlanır → isterse kendi alan adını bağlar →
ayrılırsa bir yıl sonra izi silinir.

---

### ★ FAZ 3'ÜN TAŞIYICI DERSİ

**"Yeşil test" yetmiyordu; Faz 3 "yeşil kod"un da yetmediğini gösterdi.**

#### 1 · Kod çalışıyor gibi görünür, kırılan şey SORGUDUR

Fazın en sinsi hatası. 3B'de ölçüldü:

```
$tenant->name            →  DOĞRU değeri veriyor          ✅
kolon veritabanında      →  NULL                          ⚠️
veri nerede              →  data json'ında
where('trial_ends_at')   →  hiçbir şey bulmuyor, HATA DA VERMİYOR
```

Okuma yolu sağlam olduğu için **hiçbir belirti yok**. Kırılan tek şey sorgu —
yani "denemesi bitenleri bul" sessizce boş dönerdi ve hiçbir markanın deneme
süresi hiç bitmezdi. Paketin varsayılanı (`getCustomColumns()` → `['id']`)
ezildi; alan iki yerde birden durursa **`data` kazanıyor** (ölçüldü), bu yüzden
taşırken `data - 'anahtar'` ile silinmesi gerekti.

#### 2 · Kırma denemesi rutin oldu — ve verimi ARTTI

Faz 2'de üç blokta yalan test bulmuştu; Faz 3'te **altı blokta**:

| blok | ne çıktı |
|---|---|
| 3B | kolon/`data` ayrımı — test kolonu değil okuma yolunu ölçüyordu |
| 3D | temizlik testi hiçbir şey ölçmüyordu (boş alan adı zaten doğrulamadan dönüyordu) → 260 karakterle gerçek hata |
| 3E | iki ölü savunma; biri kaldırıldı, biri gerçek testle korundu |
| 3F | test artığı — `firstOrCreate` eski satırı buluyordu → `updateOrCreate` |
| 3G | `whereNotNull` ölü çıktı — **ama bilerek bırakıldı** (aşağıda) |
| 3H | merkez kontrolü testi aslında BİÇİM kontrolünü ölçüyordu (`localhost`'ta nokta yok, zaten eleniyordu) |

#### 3 · ⚠️ TESTİN KENDİSİ GERÇEK HASAR VERDİ — yeni ders sınıfı

3G'de test `--onayla` ile komutu çalıştırdı ve **geliştirme ortamındaki gerçek
marka klasörlerini sildi** (3 ürün görseli). `storage/framework` de gitti; süit
`Please provide a valid cache path` ile tamamen çöktü.

> Sebep: test ile uygulama **aynı `storage/` klasörünü paylaşıyor**.
> `RefreshDatabase` veritabanını izole eder, **diski etmez**.
> → geri alınamaz işlem yapan koda kök dizin **parametre olarak** girer.

#### 4 · "Ölü savunma kaldırılır" kuralının istisnası yazıldı

2F'de ölü savunma kaldırılmıştı. 3G'de yine ölçüldü, yine ölü çıktı — ve
**bırakıldı**. Fark gerekçeye yazıldı:

```
2F   kolon NOT NULL      → senaryo İMKÂNSIZ                    → kaldır
3E   başka yer koruyor   → gerçek koruma orada                 → kaldır
3G   senaryo MÜMKÜN, koruma DOLAYLI (SQL'in NULL semantiğine
     bağlı) ve işlem GERİ ALINAMAZ                             → BIRAK
```

Ölçüt "ölü mü" değil; **senaryo mümkün mü** ve **hata geri alınabilir mi**.

#### 5 · `null` iki farklı şey demek olabilir

3F'de kota kontrolü "kiracı yok" ile "planı yok" durumlarının ikisini de `null`
görüyordu → merkez bakım komutları deneme sınırına takılıyordu. `kotaDisi()`
ile ayrıldı.

#### 6 · Gerçek HTTP yine süitin göremediğini gösterdi

- **3C** — merkez rotalar `web.php`'deydi, yani CSRF'liydi. **Bütün testler
  yeşildi** çünkü `postJson` kullanıyorlar; gerçek `curl` "token mismatch" aldı.
  ⚠️ Karar 1A.2'de **zaten verilmişti ve unutuldu** → yorum yetmiyor, middleware
  listesine bakan yapısal test yazıldı.
- **3E** — ikinci abonelik 500 (istisna eşlenmemiş) → 409; imzasız webhook 401
  yerine 400 (gizli anahtar boş) → ayrı istisna + `Log::critical`.

#### 7 · Plan gerçekle çelişti, plan güncellendi

- **3F** `DENEME_PERSONEL` 1 → 3. Sınır 1 iken `IzinTest` kırıldı: deneme
  markası personel davetini **hiç deneyemiyordu** — satın almaya ikna edecek
  özelliği göremezdi.
- **3H** `verified_at` cast'ı: paketin `Domain` modeli bizim kolonumuzu
  bilmiyor, tarih metin olarak geliyordu → kendi modelimiz yazıldı.

---

### ⚠️ Bitiş ölçütü TAM KARŞILANMADI — dürüst kayıt

Ölçüt şuydu: *"…ayrılırsa **verisini indirir** ve bir yıl sonra izi silinir."*

**İkinci yarı 3G'de yapıldı, birincisi YAPILMADI.** Marka geneli veri dışa
aktarma yok. 2G'deki `DataExporter` **müşteri** verisi için; marka geneline
genişletmek ayrı bir iş.

> KVKK açısından bugün eksik değil — silme yükümlülüğü karşılanıyor. Ama
> **söz verilen ölçüt bu**; Faz 4 planına borç olarak giriyor.

### Devredilen borçların durumu — ölçüldü, varsayılmadı

| borç | durum |
|---|---|
| `tenants:backfill` komutu (3A'dan) | ✅ **kapandı** |
| sahip varsayılan parolası `123` (3D) | ⚠️ **daraldı, kapanmadı** — gerçek internet akışı `min:8` gerçek parola istiyor; kalan yalnızca `tenant:create` artisan komutunun varsayılanı, internetten erişilmiyor |
| Caddyfile'a elle alan adı (3H) | ⚠️ **üretimde kapandı** — `on_demand_tls` + `ask` ucu çalışıyor; geliştirmede `.localhost` zorunlu olarak elle kalıyor (LE `.localhost`'a sertifika vermez) |

### Açık borçlar — Faz 4'e gidiyor

| borç | not |
|---|---|
| `IyzicoSubscriptionProvider` | gerçek sağlayıcı + sandbox doğrulaması (1E → 1E.7 deseni: önce sahte, sonra gerçek) |
| marka geneli veri dışa aktarma | bitiş ölçütünün eksik parçası |
| wildcard sertifika | haftalık kayıt sayacı tetikleyince (3-K5) |
| `declare(strict_types=1)` | tek Pint kuralı, 0.3'ten devrediyor |

---

## Faz 4 — Arayüz  ◀ **AÇILDI**

Üç fazdır sistemin **yüzü yok**. M-3 "arayüz sonra" demişti; sonra geldi.

> ⚠️ Bu faz açılmadan önce diskte commit edilmemiş bir keşif duruyordu
> (3 Blade dosyası + `routes/web.php`, 120 satır). Karar netleşince
> **atıldı** — yığın seçimi onu kısmen geçersiz kılıyordu ve yarım bir
> başlangıcı taşımak, kararı ona uydurma baskısı yaratırdı.

### M-3 — arayüz teknolojisi *(karar burada veriliyor)*

Değerlendirilen öneri şuydu: **Inertia.js + Vue/React + Vite SSR, tek Laravel
projesi, harici API katmanı olmadan.** Öneri ana akım ve sağlam; Laravel'in
resmî başlangıç seti bu. Ama üç ölçüm onu **olduğu gibi** almayı engelledi.

---

#### 4-K1 · Yığın **yüzeye göre** bölünüyor — tek yığın değil

```
marka alan adı    /            VİTRİN            Blade (sunucu render)
                  /yonetim     MARKA PANELİ      Inertia + Vue
                  /api/*       vitrin API        (Faz 1-2'den, değişmiyor)
                  /panel/*     panel API         (Faz 1-2'den, değişmiyor)

merkez alan adı   /yonetim     KONTROL DÜZLEMİ   Inertia + Vue
                  /platform/*  merkez API        (Faz 3'ten, değişmiyor)
```

> ★ **"Üç paneli neyle ayıracağız" sorusunun cevabı burada:** *alan adı* bizi
> markadan ayırıyor, *yol* vitrini yönetimden ayırıyor. `/yonetim` her iki
> alan adında da "bu alan adının sahibinin yönetim yeri" demek — marka alan
> adında markanın, merkezde bizim. Ayrımı zaten kurulu olan kiracılık yapıyor,
> yeni bir mekanizma icat edilmiyor.

**Gerekçe — üç yüzeyin ihtiyaçları ZIT:**

| | halka açık | SEO | marka başına görünüm | etkileşim |
|---|---|---|---|---|
| vitrin | ✅ | **hayati** | **zorunlu** | orta |
| panel | ❌ | yok | yok — herkese aynı | **yoğun** |
| yönetim | ❌ | yok | yok | orta |

Tek yığın dayatmak, bir yüzeyin ihtiyacını diğerine ödetmek olurdu. Rakiplerin
hepsi de aynı yerden ayrılmış: **Shopify** vitrinde Liquid şablonları, yönetimde
React kullanıyor; **Spree** kiracı başına tema + ayrı yönetim; **Saleor/Medusa**
headless vitrin + ayrı yönetim.

> ⚠️ **Bedeli dürüstçe:** iki yığın öğrenilecek. Bilerek kabul edildi — çünkü
> alternatifin bedeli (aşağıdaki K2 ve K5) daha ağır.

---

#### 4-K2 · **SSR AÇILMIYOR** — ve bu M-2.4'ün aynısı

Önerinin en riskli parçası buydu. Inertia SSR ayrı bir **Node süreci**
çalıştırıyor (`:13714`); süreç uzun ömürlü ve **bütün markalar için ortak**.

```
tarayıcı → Laravel → props (marka A'nın verisi)
                        ↓
              Node süreci :13714   ← UZUN ÖMÜRLÜ · TÜM MARKALAR ORTAK
                        ↓
                    HTML → tarayıcı
```

Vue'nun kendi belgesi buna **cross-request state pollution** diyor: modül
seviyesinde tanımlanan durum bütün istekler arasında paylaşılıyor, bir
kullanıcıya ait veriyle değiştirilirse **başka bir isteğe sızıyor**. Bizde bu
"kullanıcı" değil **MARKA** sızması demek.

> ★ **Bu, M-2.4'te pgBouncer'ı reddetme gerekçemizin birebir aynısı.** Orada
> da paylaşılan uzun ömürlü bir şey (fiziksel bağlantı) kiracı durumunu
> (`search_path`) taşıyordu ve A markasının şeması B'ye geçiyordu. Aynı şekil,
> farklı katman.

Ve şu satır bu projenin tam merkezine denk geliyor:

> *"Yerelde yakalayamazsın, çünkü geliştirme sunucun aynı anda tek istek
> işliyor."* — yani **eşzamanlı yük olmadan görünmüyor.**

**İkinci gerekçe: SSR SESSİZ bozuluyor.** Inertia belgesinde yazılı — SSR
başarısız olursa *zarifçe istemci tarafı render'a düşüyor*:

```
SSR bozuldu  →  sayfa ÇALIŞIYOR          ✅
             →  testler YEŞİL             ✅
             →  Google boş sayfa görüyor  ⚠️  SEO sessizce gitti
```

Belge bunu açıkça uyarıyor: *"SSR hataları testlerde fark edilmeyebilir —
istemci render'ı başarılı olur ama kullanıcılar sunucu render'lı HTML'i hiç
almaz."* `throw_on_error` seçeneği tam bunun için var. Vitrin için SEO ürünün
kendisi; sessizce kaybolamaz.

**Peki SEO'dan vazgeçiyor muyuz? Hayır — vitrin zaten Blade, yani zaten
sunucuda render ediliyor.** SSR'ın çözdüğü sorun bizde K1 sayesinde hiç
doğmuyor. Panelde ve yönetimde SEO gerekmiyor, orada SSR'a zaten gerek yok.

> **Kazanç:** Node süreci hiç yok → sızma riski yok, sessiz düşüş yok, Faz
> 6'da bir dağıtım parçası eksik. Ayrıca `inertia-laravel#730` (tek projede
> çoklu Inertia örneği) sorunu da doğmuyor: panel ile yönetim **ayrı alan
> adlarında**.

---

#### 4-K3 · API **kalıyor** — arayüz onu değil `Domain`'i çağırır

Önerideki "harici API katmanına ihtiyaç duymadan" cümlesi bizde tersine dönüyor:
biz sıfırdan başlamıyoruz. **Ölçüldü:** 119 + 15 rota, 36 controller, 3.932
satır, token tabanlı Sanctum (K-12), 549 test bu uçlara vuruyor.

Inertia bu API'yi *kullanmaz* — kendi controller'ı prop döndürür, kimliği
oturum tabanlıdır. Yani Inertia bir katmanı kaldırmıyor, **var olanın yanına
ikinci bir sunum katmanı** koyuyor.

**Bu kabul edilebilir, çünkü şansımız yaver gitti:** iş mantığı `app/Domain/`'de
ve controller yalnızca çeviriyor. Kural bu yüzden şöyle yazılıyor:

```
Inertia controller  →  app/Domain/ servisi        ✅
Inertia controller  →  API controller'ı çağırmak  ❌ ASLA
```

> ⚠️ İkincisi yapılırsa HTTP üstünden kendi kendimize istek atmış oluruz:
> yavaş, kimlik bağlamı kayıp, hata ayıklaması cehennem.

**API atılamaz** — mobil uygulama, marka entegrasyonları ve Faz 5 onu
gerektiriyor. Araştırmada da yazılı: Inertia mobil API için uygun değil,
sonradan REST gerekirse controller mantığı çoğaltılır. Biz o tuzağa `Domain`
katmanı sayesinde düşmüyoruz.

---

#### 4-K4 · İki kapı, aynı yetkiler: panel **oturum**, API **token**

Inertia oturum tabanlı çalışır, mevcut API token tabanlı. İkisi de aynı
kullanıcı tablolarına ve aynı `izin:` kontrolüne bağlanacak.

> ⚠️ Bu, 3C'de CSRF dersini yeniden gündeme getiriyor — ama bu kez **doğru
> tarafında**: oturum tabanlı panel `web` grubunda olacak ve CSRF **isteniyor**.
> API `api` grubunda kalıyor. 3C'deki hata rotayı yanlış gruba koymaktı; burada
> grup bilinçli seçiliyor.

---

#### 4-K5 · Tema = **AYAR**, şablon değil. Marka Blade YAZAMAZ.

Vitrin markanın sitesi; iki marka aynı görünüyorsa satamayız. Ama "marka kendi
şablonunu yazsın" **yapılamaz** — ve bu bir tercih değil, güvenlik sınırı:

> ⚠️ **Blade PHP'dir ve kum havuzu YOKTUR.** Kullanıcının yazdığı Blade'i
> render etmek doğrudan **uzaktan kod çalıştırma**dır. Laravel'in kendi
> belgesi uyarıyor; Cachet'te (#4621) tam bu yaşandı: kimliği doğrulanmış
> kullanıcı şablon oluşturup sunucuda kod çalıştırabiliyordu.
>
> **Bizde bunun bedeli tek marka değil:** şema bazlı kiracılıkta sunucuda kod
> çalıştıran biri `search_path`'i değiştirip **bütün markaların verisine**
> ulaşır. Yani tek markanın teması, tüm platformun sonu olurdu.

Shopify'ın Liquid'i tam bu yüzden var: *"Kullanıcıların düzenleyebilmesi için
yapıldı, ve kullanıcının yazdığı kodu sunucunda çalıştırmak istemezsin."*
Keyfi kod yok, dosya sistemi yok, sınırsız döngü yok.

**Kararımız:** marka **ayar seçer**, şablon yazmaz.

```
settings.theme  →  renkler · logo · yazı tipi · ana sayfa blok sırası
                   · seçilebilir düzen çeşitleri
şablon          →  BİZDE, sürümlü, markaya kapalı
```

`SettingGroup::Theme` **Faz 1'den beri duruyor** ve yorumunda "(Faz 4)" yazıyor
— yeni bir yer açmaya gerek yok, ayar altyapısı hazır.

> **İleriye açık kapı:** yeterince marka "kendi şablonumu yazayım" derse yol
> Liquid benzeri **kum havuzlu** bir motor, Blade değil. Ölçüm olmadan
> eklenmez (K-6 / M-2.0).

---

### Bloklar

| | konu |
|---|---|
| **4A** | vitrin iskeleti — Blade düzeni, tema ayarlarının okunması, `/` JSON'dan HTML'e |
| **4B** | vitrin akışı — katalog · ürün · sepet · ödeme, uçtan uca gerçek satın alma |
| **4C** | panel iskeleti — Inertia + Vue kurulumu, oturum kimliği, yetki köprüsü |
| **4D** | panel — katalog yönetimi (**ürün ekleme buradan görünür hâle geliyor**) |
| **4E** | panel — sipariş, kargo, iade ekranları |
| **4F** | kontrol düzlemi arayüzü — merkez alan adında |
| **4G** | tema — ayar seti, marka başına görünüm, önizleme |
| **4H** | blok kapanışı — iki markada gerçek tarayıcı koşusu |

### 4A — BİTTİ ✅ (15 test)

Kod: `app/Domain/Settings/ThemeSettings.php` · `DefaultSettings` (tema) ·
`app/Http/Storefront/{HomeController,CartToken,StorefrontViewData}.php` ·
`resources/views/storefront/` · `RequirePublishedStore` (HTML dalı) ·
`bootstrap/app.php` · `routes/tenant.php` · `tests/Tenancy/VitrinTest.php`

**Toplam 564 test** (Faz 3 sonu: 549).

**★ 4A'nın işi üç ENGELİ kaldırmaktı — üçü de "arayüz yokken verilmiş,
şimdi vadesi gelen" karar:**

| engel | eski gerekçe | neden artık geçersiz |
|---|---|---|
| `ForceJson` **global** | *"arayüz olmadığı için (M-3) login rotası yok"* | arayüz VAR: her sayfa "JSON istiyorum" sayılırdı, form hataları 422 JSON dönerdi |
| sepet kimliği **yalnızca başlıkta** | *"çerez değil, çünkü M-3 seçilmedi"* | vitrin sunucuda render ediliyor; tarayıcı düz gezinmede özel başlık **gönderemez** |
| `/` **`api` grubunda JSON** | vitrin yoktu | HTML sayfası `web` grubunda olmalı: oturum, çerez, ilerde CSRF |

⚠️ `ForceJson` **kaldırılmadı, daraltıldı** (`api` grubuna): 2E'de ölçülen
500 hatası API istemcileri için hâlâ gerçek.

**4A-K1 · Tema ayarı da bir GİRİŞ KAPISI — 4-K5 tek başına yetmiyor.**

> 4-K5 "marka şablon yazamaz" diyor ve kapıyı kapatıyor. Ama ayarın kendisi
> **pencere**: renk doğrudan bir `<style>` bloğuna giriyor. Marka panelden
> `red; } body { background: url(https://baskasi.example/x) ` kaydedebilseydi
> çıkan sayfa markanın yazmadığı CSS'i çalıştırırdı.
>
> Bu yüzden okuma yolu **beyaz liste**: renk `#rrggbb` kalıbına, yazı tipi
> sabit listeye, düzen sabit listeye uyar ya da **varsayılana düşer**.
> Geçersiz değer sayfaya hiç ulaşmıyor.

⚠️ **Doğrulama YAZMA yolunda değil OKUMA yolunda.** Ayar veritabanına başka
yollardan da girebiliyor (tohumlayıcı, artisan, elle SQL); yazarken
doğrulamak o yolları açık bırakırdı.

**4A-K2 · Çerez ŞİFRELENMİYOR — ve bu bir tuzağı kapatıyor.**

`EncryptCookies` yalnızca `web` grubunda çalışıyor; `api` grubunda çerez
middleware'i hiç yok. Şifreleme açık kalsaydı aynı çerez iki grupta **iki
farklı değer** olurdu:

```
vitrin sayfası (web) → çözülmüş token → sepet BULUNUR
sepet ucu      (api) → şifreli metin  → sepet BULUNMAZ
```

Hata vermezdi: müşteri sayfada sepetini görür, eklemeye çalışınca yeni boş
sepet açılırdı. ⚠️ Güvenlik düşmüyor — token zaten aynı değerle
`X-Cart-Token` başlığında düz metin gidiyor ve doğrulaması veritabanına
karşı yapılıyor.

**★★ KIRMA DENEMELERİ — dördü yapıldı, BİRİ testin yalanını gösterdi**

| kırılan | sonuç |
|---|---|
| renk beyaz listesi kaldırıldı | ✅ enjeksiyon testi düştü |
| `CartToken` çerez dalı kaldırıldı | ✅ iki sepet testi düştü |
| `ForceJson` tekrar global yapıldı | ✅ "kapalı mağaza HTML" testi düştü |
| çerez şifreleme istisnası kaldırıldı | ⚠️ **"çerez şifrelenmiyor" testi YEŞİL KALDI** |

> ★ Sonuncusu bir **yalan testti**: yalnızca `api` grubuna vuruyordu ve orada
> `EncryptCookies` zaten yok — istisna o yolda hiç rol oynamıyor. Test
> yeniden yazıldı: artık **tek çerezle iki gruba birden** vuruyor ve istisna
> kaldırılınca düşüyor.

**★ TEST YARDIMCISI ÖLÇÜLECEK ŞEYİ YOK EDİYORDU** — 2E'nin akrabası:

```
withCookie()             değeri ŞİFRELİYOR   (bizim çerezimiz şifresiz)
withUnencryptedCookie()  düz gönderiyor      ✓
getJson()                şifresiz çerezi SESSİZCE DÜŞÜRÜYOR
```

Çerez testleri `getJson` ile yazılsaydı istek **çerezsiz** giderdi ve
"çerez okunuyor" iddiası hiç ölçülmezdi. Hata da vermezdi.

**★ Devredilen borç KENDİLİĞİNDEN işledi.** Tema ayarları sonradan eklendiği
için mevcut markalarda yoktu — 3A'nın aracı üç markada da 4 eksiği buldu ve
tamamladı (`tenants:run marka:eksikleri-tamamla`). ⚠️ Vitrin ayar olmadan da
çalışıyordu, çünkü okuma yolu varsayılana düşüyor; backfill onları
**panelden düzenlenebilir** yapıyor.

**Ayrıca:** `AlanAdiTest`'in ilk testi yeniden yazıldı ve ölçtüğü şey
güçlendi. Eskiden `/` bir hata ayıklama ucuydu ve `tenant('id')` basıyordu;
test yalnızca "kiracı değişkeni kuruldu"yu ölçüyordu — şemadan tek satır
okunmuyordu. Artık markanın kendi ayarından gelen mağaza adı aranıyor,
yani `search_path` gerçekten sınanıyor.

**Doğrulandı (iki markada gerçek HTTPS):** ikisi de 200 + `text/html`, her
biri kendi adıyla · A'ya `#0ea5e9` yazıldı, sayfaya girdi · B'ye enjeksiyon
yazıldı, **varsayılana düştü ve `kotu.example` sayfada hiç yok** · sepete
ekleme `Set-Cookie` (düz, `httponly`, `samesite=lax`) döndü, çerezle sayfada
"Sepet 2", çerezsiz boş · B kapatıldı → tarayıcıya **HTML 503**, API
istemcisine **JSON 503**.

**⚠️ Bu blokta YAPILMAYAN:** ürün detay sayfası, sepet sayfası ve ödeme
akışı (4B). Ana sayfadaki ürün kartları bugün ana sayfaya dönüyor — ölü
`href="#"` yerine çalışan bir adres bırakıldı.

---

### 4B — BİTTİ ✅ (12 test + 1 yapısal)

Kod: `app/Http/Storefront/{ProductPageController,CartPageController,CheckoutPageController,CartResolver}.php` ·
`PaymentReturnController` (HTML dalı) · `ForceJson` (istisna listesi) ·
`resources/views/storefront/sade/{urun,sepet,odeme,odeme-donus}.blade.php` ·
`tests/Tenancy/VitrinAkisTest.php` · `tests/Feature/SepetKimligiTest.php`

**Toplam 577 test** (4A sonu: 565).

**4B-K1 · Vitrin formları JAVASCRIPT'SİZ çalışıyor.**

> Her işlem bir `<form method="post">`, cevabı yönlendirme (PRG deseni).
> Sunucuda render edilen bir vitrinin JS'e bağımlı olması M-3'ün amacını
> bozardı; müşteri betik yüklenmeden de alışveriş yapabilmeli.
>
> ⚠️ PRG zorunlu: doğrudan HTML dönseydi müşterinin sayfayı yenilemesi
> aynı ürünü tekrar sepete eklerdi.

**★★ 4A'DAN KAÇAN HATA: düzeltme SINIRA değil TEK YERE yapılmıştı.**

4A'da sepet kimliğine çerez desteği eklendi — ama yalnızca `CartController`'a.
Üç yer başlığı doğrudan okumaya devam etti ve **sonuçları sessizdi**:

```
CouponController    tarayıcıdan kupon → "sepet bulunamadı"
CheckoutController  tarayıcıdan ödeme → "sepet bulunamadı"
AuthController      giriş → misafir sepeti BİRLEŞMİYOR → SEPET GİDER
```

Hiçbiri hata vermiyordu; hepsi "sepetin yok" diyordu. Üçü de [CartToken]'a
taşındı, ortak çözümleme [CartResolver]'a alındı ve kural artık **yapısal
testle ölçülüyor** (`SepetKimligiTest`): `CartToken` dışında hiçbir dosya
başlığı okuyamaz.

> ⚠️ 3C'deki dersin aynısı: karar yorumla korunmuyor, **ölçen test**
> gerekiyor. Kırma denemesi doğruladı — başlık tekrar doğrudan okununca
> test düşüyor.

**★★ GERÇEK KOŞU İKİ HATA DAHA GÖSTERDİ — ikisi de ÖDEME DÖNÜŞ EKRANINDA**

Süit yeşilken `curl` bulduğu için ikisi de kayda değer; ikisi de müşterinin
**ödemesini yeni bitirdiği** ekranda oluyordu:

| hata | sebep |
|---|---|
| tarayıcıya **ham JSON** | uç `api` grubunda; `ForceJson` `Accept`'i eziyor, yazdığım HTML dalı hiç çalışmıyor |
| **500** — `Undefined variable $errors` | düzen `$errors` bekliyor ama onu paylaşan middleware yalnızca `web` grubunda |

Uç `web`'e taşınamıyor: sağlayıcı POST ediyor ve CSRF üretemez. Çözüm
`ForceJson`'a **dar** bir istisna listesi (`HTML_UCLARI`) ve düzende
`isset($errors)` koruması. İkisi için de test yazıldı ve kırma denemesiyle
doğrulandı.

**★ İKİ TESTİM YANLIŞ VARSAYIMLA YAZILMIŞTI — kod haklı çıktı**

| yazdığım | gerçek |
|---|---|
| "eski sözleşme sürümü **reddedilmeli**" | karar reddetmek değil **görüleni kaydetmek** (1A.4 · 1D-K2): sipariş müşterinin ekranındaki sürümü taşıyor |
| stok 0'da "**Stok yetersiz**" yazmalı | 1C-K2 stok bitmesini üç "artık satın alınamaz" durumundan biri sayıyor → "artık satışta değil". İki mesaj ayrı dallardan geliyor; **ikisi de** ölçülüyor artık |

**★ Blade tuzağı:** `@section('ad', Str::limit($x, 150))` kısa biçimi
virgülde kırılıyor ve **görünümü derlenemez** yapıyor. Belirti sinsi —
Larastan "görünüm bulunamadı" diyor, sayfa çalışıyor görünüyor.

**Doğrulandı (iki markada gerçek tarayıcı akışı, `curl` + çerez kavanozu):**
ana sayfa → ürün bağlantısı → ürün sayfası (CSRF alanı ve varyant kimliği
formda) → sepete ekle **302** → sepet sayfası "Sepet 2" + "Ürün sepete
eklendi" → ödeme sayfası (sözleşme sürümü gömülü) → sipariş **302 →
`sandbox-cpp.iyzipay.com`** · sipariş `TM-2026-000015`, 699,80 TL,
`pending`, sözleşme sürümü kayıtlı · dönüş ekranı tarayıcıya **HTML
"Ödemeniz işleniyor"**, API'ye **JSON**. B markasında aynı akış çalıştı ve
**A'nın sepeti B'de görünmedi**.

**⚠️ Bu blokta YAPILMAYAN:** müşteri girişi/kayıt sayfaları, adres defteri,
sipariş geçmişi ve yorum yazma ekranı. Uçları var (Faz 1-2), sayfaları yok.

---

### Bitiş ölçütü

Bir marka **hiç `curl` kullanmadan** mağazasını kurar: giriş yapar, ürün ekler,
temasını seçer, mağazasını yayına alır; bir müşteri tarayıcıdan girip ürünü
bulur, sepete atar, öder; marka siparişi panelden görür ve kargolar. Üçü de
kendi yüzeyinden, kimse diğerinin ekranını göremeden.

> ⚠️ **Faz 3'ten devredilen borç bu faza giriyor:** marka geneli veri dışa
> aktarma (kapanışta "verini indir"). Arayüzü olan bir işlev olduğu için
> doğal yeri burası — 4F'de kontrol düzlemine bağlanacak.

##### Bu kararların dayandığı kaynaklar

> · **Inertia** — [SSR belgeleri](https://inertiajs.com/docs/v3/advanced/server-side-rendering)
>   (ayrı Node süreci, `:13714`, sessiz düşüş ve `throw_on_error`) ·
>   [Inertia 2.0](https://laravel.com/blog/announcing-inertia-20-redefining-frontend-development-for-laravel)
>   (ertelenmiş prop, ön yükleme, yoklama) ·
>   [#730 çoklu örnek](https://github.com/inertiajs/inertia-laravel/issues/730) (açık)
> · **Vue** — [SSR: cross-request state pollution](https://vuejs.org/guide/scaling-up/ssr.html)
> · **Blade güvenliği** — [Laravel Blade belgeleri](https://laravel.com/docs/12.x/blade)
>   (kullanıcı verisi şablona gömülmez) ·
>   [Cachet #4621](https://github.com/cachethq/cachet/issues/4621) (kum havuzsuz şablon → RCE)
> · **Shopify Liquid** — [neden kum havuzlu](https://github.com/Shopify/liquid)
>   ("kullanıcının yazdığı kodu sunucunda çalıştırmak istemezsin")
> · **Karşılaştırma** — [Livewire vs Inertia 2026](https://dev.to/hafiz619/livewire-4-vs-inertiajs-3-which-laravel-frontend-stack-should-you-use-in-2026-47p4) ·
>   [Laravel arayüz mimarisi 2026](https://redberry.international/laravel-frontend-architecture/) ·
>   [Spree çok kiracılı tema](https://spreecommerce.org/open-source-multi-tenant-alternative-to-shopify/)

---

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
