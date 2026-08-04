# TıkMarka — Geliştirme Planı

> **Bu dosya projenin tek yol haritasıdır.** Tüm geliştirme buna göre ilerler.
> Kararların gerekçeleri `docs/pre-setup.md`'de, veri modeli `docs/domain-model.md`'de.
> Son güncelleme: **2026-08-03**

```
┌─ YOL HARİTASI ────────────────────────────── şu an: Faz 0.4 ───┐
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

- [ ] **Pest** kur, örnek bir test yaz ve geçtiğini gör
- [ ] Test veritabanını ayır (`tikmarka_test`)
- [ ] `RefreshDatabase` davranışını doğrula: her test temiz veritabanıyla başlasın
  > **Neden:** testler birbirinin verisine bulaşırsa, tek başına geçen test paket hâlinde
  > patlar. Teşhisi çok zordur, baştan engellenir.
- [ ] **Kiracı testleri için düzen kur:** test başında geçici şema oluşturulsun, sonunda
      düşürülsün
  > **Neden:** kiracı testleri merkez şemayla aynı düzeni kullanamaz. Bu şimdi kurulmazsa
  > 1A'dan itibaren her test dosyasında elle kurulum tekrarlanır.

### 0.4b Atılacak alıştırma — Laravel'in temel döngüsü *(öğrenme adımı)*

- [ ] Bir `Note` migration'ı, bir `Note` modeli, bir test yaz — çalıştır, testi yeşil gör
- [ ] **Sonra hepsini sil**

> **Neden bu adım var:** 0.5'teki çok kiracılık, "normal" Laravel'in **üstüne kurulan**
> bir katman. Altındaki katmanı bir kere kendi elinle yapmadan üstündekini anlamak zor —
> her şey aynı anda yeni olur. Bu alıştırmadan sonra 0.5, "bunun üstüne ne ekleniyor"
> sorusuna dönüşür.
>
> Plan kuralı 1'i (iş mantığından önce kiracılık) bozmuyor: bu proje kodu değil, silinecek
> bir egzersiz. Depoya commit edilmez.

### 0.5 Kiracılık zemini ← **bu bloğun kalbi**

- [ ] `stancl/tenancy` kur, **şema bazlı** (`PostgreSQLSchemaManager`) yapılandır (M-2)
  > ⚠️ **Önce Laravel 12 uyumluluğunu doğrula.** Paket framework'ün başlatma sürecine
  > giriyor (M-2.6), dolayısıyla sürüm uyumu ilan edilmiş olmalı — "muhtemelen çalışır"
  > kabul edilmez. Uyumlu sürüm yoksa karar M-2.6'ya geri döner (elle yazmak), plan
  > buna göre güncellenir.
- [ ] Migration'ları ikiye ayır: `database/migrations/landlord` ve `database/migrations/tenant`
- [ ] Merkez şema migration'ları: `tenants`, `domains` (`docs/domain-model.md` §2)
  > `plans` / `subscriptions` Faz 3'e bırakıldı — Faz 0'da tek bir varsayılan planla yaşanır.
- [ ] Alan adı çözümleme middleware'i (M-2.2): host → `domains` → şema
- [ ] **M-2.4'ün beş tuzağını çöz** — hepsi `app/Tenancy/` altında, tek yerde:
  - [ ] **Kuyruk:** temel `Job` sınıfı kiracı kimliğini taşısın ve çalışmadan önce bağlamı
        yeniden kursun
  - [ ] **Cache:** tüm anahtarlar kiracı önekli
  - [ ] **Dosya:** kiracı kökü `tenants/{uuid}/`
  - [ ] **Zamanlanmış iş:** zamanlayıcı kiracı listesi üzerinde dönsün
  - [ ] **`search_path`:** bağlam istek sonunda **sıfırlansın**
- [ ] `php artisan tenant:create` komutu — şema oluştur, migrate et, alan adı bağla
      (M-2.5'in 1–3 ve 6. adımları; varsayılan veri ve sahip kullanıcı 1A'da eklenecek)
- [ ] Caddy `ask` ucu: `GET /tenancy/domain-check?domain=` → `domains`'e bak, 200/404 dön
  > ⚠️ **M-4.1/1 — bu uç olmadan on-demand TLS açılmaz.** Açılırsa IP'mize yönlendirilen
  > her alan adı için sertifika alınmaya çalışılır ve kotamız yanar.
- [ ] `app/` dizin yapısını kur (M-2.7): `Platform/`, `Tenancy/`, `Domain/`, `Http/`
- [ ] **Testler — bu bloğun asıl çıktısı:**
  - [ ] İki kiracı oluşturuluyor, ikisinin de şeması geliyor
  - [ ] A kiracısında yaratılan kayıt B kiracısından **görünmüyor**
  - [ ] Tanımsız alan adı **404** dönüyor
  - [ ] Kuyruğa atılan iş **doğru kiracının** şemasında çalışıyor
  - [ ] Aynı cache anahtarı iki kiracıda **farklı değer** dönüyor
  - [ ] Bir istek sonrası `search_path` sıfırlanmış oluyor

### 0.6 Sürekli entegrasyon (CI)

- [ ] GitHub Actions iş akışı: her `push` ve PR'da `lint` + `analyse` + `test`
  > **Neden:** "bende çalışıyordu" durumunu ortadan kaldırır.
- [ ] İş akışında PostgreSQL ve Redis servislerini tanımla
- [ ] **Docker Hub girişi** ekle (`DOCKERHUB_USERNAME` / `DOCKERHUB_TOKEN`)
  > **Neden:** Docker Hub'ın anonim indirme limiti var ve CI sunucuları paylaşımlı IP
  > kullandığı için build'ler beklenmedik bir sabah `toomanyrequests` ile durur.
- [ ] Bilerek bozuk bir kod atıp CI'ın **kırmızı** döndüğünü doğrula, sonra düzelt
  > **Neden:** hiç kırmızı görmediğin bir CI, gerçekten çalıştığını kanıtlamaz.

### 0.7 Belgeler

- [ ] `README.md` — projenin ne olduğu, kurulum adımları, komutlar
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
- [ ] **İki ayrı guard: `customer` ve `staff`**
  > **Neden:** `docs/domain-model.md` §3 gereği müşteri ve personel ayrı tablolar. Tek
  > guard kullanılırsa "bu token hangi tabloya ait" sorusu her istekte tekrar sorulur ve
  > bir gün yanlış cevaplanır. İki guard, ayrımı framework seviyesinde zorunlu kılar.

#### 1A.1 Migration ve modeller (kiracı şeması)

- [ ] `customers` — `email` (**citext, nullable**), `password` nullable, `accepts_marketing`
  > **Neden nullable:** misafir siparişini mümkün kılan alan bu (`domain-model.md` §3).
- [ ] `users` (personel) — `email` citext unique, `is_owner`
- [ ] `roles`, `role_user`, `role_permissions`
- [ ] `settings` — `group`, `key`, `value` (jsonb), `is_encrypted`
- [ ] `addresses`
- [ ] Modeller + ilişkiler
- [ ] Enum sınıfları: `CustomerStatus`, `OrderPaymentStatus` (şimdiden), `SettingGroup`
  > **Neden:** durumları serbest metin yerine PHP enum'u olarak tanımlamak yazım hatasını
  > statik analizde yakalatır.
- [ ] Factory'ler: `CustomerFactory`, `UserFactory`, `AddressFactory`
  > **Neden:** sonraki her blokta test verisi buradan üretilecek. Şimdi doğru kurulursa
  > 1B–1F'de tek satır tekrar yazılmaz.
- [ ] `tenants:migrate` ve `tenants:migrate:fresh` sorunsuz çalışıyor

#### 1A.2 Kimlik doğrulama uçları

- [ ] Müşteri: `POST /api/register` · `POST /api/login` · `POST /api/logout` · `GET /api/me`
- [ ] Personel: `POST /panel/login` · `POST /panel/logout` · `GET /panel/me`
  > Personelde **kayıt ucu yok** — personel davetle gelir (1A.3).
- [ ] Giriş ve kayıt uçlarına **hız sınırlama** (throttle)
  > **Neden:** kaba kuvvet saldırısının en ucuz önlemi. M-4.1/3 gereği hız sınırlama
  > vekilde değil burada yapılıyor — bu yüzden atlanamaz.
- [ ] Testler: başarılı kayıt · yinelenen e-posta reddi · yanlış parola · geçersiz token
- [ ] **Kritik test:** müşteri token'ı ile `/panel/*` ucuna erişim **401/403** dönüyor

#### 1A.3 İzin sistemi

- [ ] İzin listesini kodda sabit tanımla: `product.view`, `product.write`, `order.view`,
      `order.fulfill`, `order.refund`, `customer.view`, `settings.write`, `staff.manage`,
      `finance.view`
- [ ] Dört varsayılan rolü seed et: **Sahip · Yönetici · Katalog · Sipariş & Destek**
- [ ] Gate/Policy katmanı — izin kontrolü tek yerden
- [ ] `GET / POST / DELETE /panel/staff` — personel davet ve yönetimi (`staff.manage`)
- [ ] **`is_owner` kilidi:** sahibin rolü düşürülemez, kendisi silinemez
  > **Neden:** son yöneticinin kendini yetkisiz bırakıp panele kilitlenmesini engeller.
  > Bu bir rol değil, emniyet kilidi.
- [ ] Testler: izinsiz personel **403** alıyor · sahip kilidi çalışıyor · davet edilen
      personel giriş yapabiliyor

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
- Çoklu depo · çoklu para birimi · çoklu dil
- Bölgesel/desi bazlı kargo tarifesi
- Pazaryeri kanal entegrasyonları (Trendyol, Hepsiburada)
- Panelden özelleştirilebilir izin türleri (`domain-model.md` §3 kapsam sınırı)
