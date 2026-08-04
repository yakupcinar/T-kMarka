# TıkMarka — Pre-Setup

> Kod yazmadan önceki hazırlık dosyası. Alınan kararlar ve **gerekçeleri** burada durur.
> Karar değişirse buradan güncellenir; plan (`PLAN.md`) buraya bakar, tersi değil.
> Tarih: **2026-08-03**

---

## 0. Bu dosyanın amacı

TıkMarka, tek bir markanın kendi ürünlerini doğrudan müşterisine sattığı (D2C) bir
e-ticaret uygulaması. Pazaryeri değil: satıcı hesabı, komisyon, hakediş, satıcı bazlı
sipariş bölünmesi **yok.**

Ama tek bir markaya özel de değil. Aynı çekirdek birden çok markaya **abonelik hizmeti
olarak** sunulacak. Bu dosyanın işi, o iki cümlenin veri modeline ve mimariye ne yaptığını
kayda geçirmek.

> 📌 **Mimari yöntem TıkRota projesinden devralındı** (karar-önce-gerekçe disiplini, K-n
> formatı, servis katmanı kuralları, `numeric(12,2)`, sipariş = fotoğraf). TıkRota'nın
> kendisi bu projenin kapsamında değil; yalnızca yöntemi ve pazaryeri-dışı domain modeli
> referans alınıyor. Bu projenin kararları **M-n** ile numaralanır.

---

## 1. Referans model — İkas ne satıyor?

M-1'in dayandığı gözlem. Türkiye'de aynı işi yapan İkas'ın (ve global karşılığı
Shopify'ın) fiilî teslim modeli:

| Yüzey | Kim kullanır | Nerede durur | Markaya özel mi |
|-------|--------------|--------------|------------------|
| **Vitrin** (storefront) | Markanın müşterisi | **Markanın kendi alan adında** (`markam.com`) | Görünüm özel, motor ortak |
| **Yönetim paneli** | Markanın personeli | Sağlayıcının alan adında (`panel.ikas.com`) | Hayır — herkes aynı panele girer |
| **API** | Markanın geliştiricisi | Sağlayıcının alan adında | Ortak, anahtar bazlı |

**Marka hiçbir şey kurmuyor.** Ne kaynak kod, ne sunucu görüntüsü, ne kurulum paketi.
Aylık ödüyor ve erişim alıyor; her şey sağlayıcının altyapısında çalışıyor.

**Mobil uygulama standart pakette yok.** Markanın kendi adıyla mağazasına ait müşteri
uygulaması ayrı ücretli bir eklenti; çoğu marka almıyor, müşteriler mobil tarayıcıdan
vitrine giriyor. Panel uygulaması ise sağlayıcının kendi markasıyla, tüm markalar için tek.

⚠️ **Güvenilirlik notu.** Yukarıdakiler İkas'ın herkese açık ürün ve fiyatlandırma
sayfalarından çıkarılmış **dışarıdan gözlemdir.** Mühendislik detayları (kiracı ayrımını
nasıl yaptıkları, vitrinin sunucuda mı istemcide mi çizildiği) kamuya açık değil ve
buradaki hiçbir karar o bilinmeyene dayanmıyor.

**Alınacak ders:** ürün, kod teslimi değil **çalışır durumda tutulan bir hizmet**. Bu,
"kolayca klonlayıp satarım" fikrinin neden yanlış eksende olduğunu gösteriyor — asıl
problem kopyalamak değil, N kopyayı aynı anda güncel ve ayakta tutmak.

---

## 2. Alınan kararlar

### M-1 — TıkMarka bir abonelik hizmeti (SaaS) olacak, kaynak kod teslimi yapılmayacak

Marka aylık/yıllık abone olur. Uygulama **bizim altyapımızda** çalışır. Markaya verilen
şey erişimdir: kendi alan adında bir vitrin, personeli için bir yönetim paneli.

**Reddedilen alternatif — kaynak kod teslimi (klon).** Her markaya deponun bir kopyasını
verip kendi sunucusunda barındırmasını istemek ilk bakışta en ucuz seçenek: kodda hiç
kiracılık kavramı olmaz. Bedeli üçüncü müşteride ortaya çıkar:

- Çekirdekteki bir güvenlik yaması N ayrı kopyaya **elle** taşınır
- Her marka kendi kopyasında değişiklik yapar, kopyalar birbirinden ayrışır, geri birleşmez
- Gelir tek seferliktir; hizmet yükü süreklidir. İş modeli ile maliyet modeli ters yönde çalışır
- Ödeme/kargo entegrasyonlarındaki bir kırılma N ayrı kurulumda ayrı ayrı yaşanır

**M-1'in getirdiği zorunluluklar** (bunlar artık kapsam içi, "sonraya" listesine yazılamaz):

| Zorunluluk | Neden |
|------------|-------|
| Marka açma akışı tek komutla çalışacak | Her yeni müşteri elle kurulum gerektiriyorsa ürün değil, taslaktır |
| Özel alan adı + otomatik sertifika | Marka kendi alan adını kullanacak; `markam.tikmarka.com` kalıcı çözüm değil |
| Abonelik ve plan verisi | Deneme süresi, plan, askıya alma — kontrol düzleminin varlık sebebi |
| Yedekleme ve veri dışa aktarma | Markanın verisi bizde duruyor; "ayrılıyorum, verimi ver" cevaplanabilir olmalı |
| Yasal katman | KVKK aydınlatma, mesafeli satış sözleşmesi, cayma hakkı, e-arşiv — gerçek satış yapan bir mağazada bunlar özellik değil, çalışma şartı |

⚠️ **Bu kararın en büyük etkisi "bitti" tanımındadır.** Ticari bir hizmet, bitmiş bir kod
tabanı değil; ayakta tutulan bir sistemdir. Gözlemlenebilirlik, yedekleme ve hata takibi
bu yüzden Faz 6'nın süsü değil, ürünün parçası.

---

### M-2 — Kiracılık: tek kod tabanı, **marka başına ayrı PostgreSQL şeması**

Tek Laravel uygulaması, tek deploy, tek veritabanı sunucusu. Her marka için o veritabanı
içinde **ayrı bir şema**. Kiracı, isteğin alan adından çözülür.

**Kararın özü tek cümlede: izolasyon yapıdan alınır, kolondan değil.**

| | Ayrı veritabanı | **Ayrı şema** ← seçilen | `tenant_id` kolonu |
|---|---|---|---|
| İzolasyon | Fiziksel | **Yapısal** | Kod disiplini |
| Bağlantı havuzu | Kiracı başına | **Tek** | Tek |
| Migration | N kez, ağır | **N kez, hafif** | 1 kez |
| Yeni kiracı | `CREATE DATABASE` | **`CREATE SCHEMA`** | `INSERT` |
| Rahat çalıştığı N | < 100 | **< 1.000** | Sınırsız |

> **Neden `tenant_id` değil:** paylaşımlı şemada izolasyon, unutulmuş tek bir
> `where tenant_id = ?` kadar uzaktadır. E-ticarette bunun bedeli bir markanın müşteri ve
> sipariş verisini başka markaya göstermektir — sessiz, geç fark edilen, telafisi olmayan
> bir hata sınıfı. Ayrı şemada bu hata **yapısal olarak mümkün değildir**: `search_path`
> kapsamı belirler, sorgunun doğru yazılmasına bağlı bir şey kalmaz.
>
> **Riskin simetrik olmaması belirleyici oldu.** Yapısal izolasyon seçilir ve N beklenenden
> büyürse sonuç *operasyonun ağırlaşmasıdır* — yorucu ama geri dönülebilir. `tenant_id`
> seçilir ve tek bir kapsam unutulursa sonuç *veri sızmasıdır* — geri dönülemez.
> Belirsizlik varken hata payı ucuz olan tarafa yaslanılır.

> **Neden ayrı veritabanı değil, ayrı şema:** ayrı veritabanının izolasyonunu neredeyse
> tamamen verirken bağlantı havuzunu tek tutar ve kiracı oluşturmayı ucuzlatır. Bu proje
> bir **öğrenme projesi** — yüzlerce markalık gerçek bir operasyon yükü beklenmiyor, ama
> kiracılık mekanizmasının doğru kurulması hedefin kendisi. Şema bazlı model bu ikisinin
> kesişimi.
>
> 📌 **Şema mı veritabanı mı sorusu bir mimari değil, bir ayardır.** İş mantığı kodu ikisinde
> de aynıdır (`tenant_id`'siz) ve `stancl/tenancy` ikisini de destekler — geçiş tek satır
> yapılandırma (M-2.6). Sonradan çevrilemeyecek olan şey `tenant_id` kararıdır; bu yüzden
> asıl karar orada verildi.

⚠️ **Abonelik iş modeli ile kiracılık teknik modeli aynı eksen değil.** "SaaS yapıyoruz"
demek "paylaşımlı şema" demek değildir. Abonelik, özel alan adı, bizim barındırmamız —
üçü de yapısal izolasyonla aynen çalışır (M-1). Bu iki eksen karıştırıldığı için sık sık
gereksiz yere `tenant_id`'ye gidilir.

#### M-2.0 İkas neden farklı yapıyor — ve neden bizi bağlamıyor

İkas'ın (kaynağı doğrulanmamış, §1) `tenant_id` bazlı paylaşımlı şema kullandığı yönünde
bilgi var. Doğru olması muhtemel ve **o ölçekte doğru karar da odur:** on binlerce mağazaya
ayrı şema açılamaz, self-servis ücretsiz denemede her kayıt için `CREATE SCHEMA`
çalıştırılamaz.

**Onların kısıtı ölçek; bizim ölçeğimiz yok.** Dört büyüklük mertebesi büyük bir şirketin
ölçek çözümünü ölçeği olmayan bir projeye kopyalamak, "gün 1'de mikroservis" hatasının
kiracılık versiyonudur.

⚠️ **Bu kararı geri çevirecek tek şey kayıt modelidir.** Self-servis kayıt + ücretsiz
deneme açılırsa (dakikada onlarca kiracı oluşabilir) `tenant_id` zorunlu hale gelir ve M-2
baştan yazılır. Bugünkü varsayım: kiracılar elle/satış süreciyle açılıyor.

**Şema bazlı modelin bilinen sınırları** (dürüstlük için, N büyürse buradan çatlar):
binlerce şemada PostgreSQL katalog tabloları şişer, `pg_dump` yavaşlar ve migration süresi
kiracı sayısıyla doğrusal artar. Sınıra yaklaşılırsa çözüm `tenant_id`'ye dönmek değil,
kiracıları birden çok veritabanı sunucusuna bölmektir — `tenants.connection` alanı bu
yüzden baştan duruyor.

#### M-2.1 İki katman — aynı veritabanı, farklı şemalar

```
┌─ tikmarka (tek PostgreSQL veritabanı) ──────────────────┐
│                                                          │
│  şema: public          ← MERKEZ (landlord)               │
│    tenants, domains, plans, subscriptions, platform_users│
│    Ürün / sipariş / müşteri BURADA YOK                   │
│                                                          │
│  şema: tenant_ayk      ← A markası                       │
│  şema: tenant_bmar     ← B markası    hepsi AYNI şema    │
│  şema: tenant_cvit     ← C markası    yapısına sahip     │
│    users, products, product_variants, orders,            │
│    settings, events …                                    │
└──────────────────────────────────────────────────────────┘
```

**Merkez (`public`)** — yalnızca platform verisi:

| Tablo | İçerik |
|-------|--------|
| `tenants` | uuid, marka adı, `schema_name`, `connection` (null = varsayılan sunucu), durum (`provisioning`/`active`/`suspended`) |
| `domains` | alan adı → kiracı eşlemesi; `is_primary` |
| `plans` / `subscriptions` | paket, deneme bitişi, abonelik durumu |
| `platform_users` | kontrol düzlemine girecek olan biziz |

> ⚠️ **Kiracı şemalarında `tenant_id` kolonu yoktur ve olmayacaktır.** `search_path` zaten
> kapsamı belirliyor. Bu sayede iş mantığı kodu tek markalı bir mağaza yazıyormuş gibi
> kalır: `Product::all()` doğru markanın ürünlerini döner. Kiracılığın iş mantığı
> üzerindeki toplam izi sıfırdır.

> 📌 `tenants.connection` bugün her kiracıda `null`. Varlık sebebi M-2'deki ölçek sınırı:
> kiracılar birden çok veritabanı sunucusuna bölünmek zorunda kalırsa bu alan dolar ve
> **iş mantığında hiçbir şey değişmez.**

#### M-2.2 İstek akışı

```
İstek gelir → host başlığına bakılır
    │
    ├─ app.tikmarka.com   → Kontrol düzlemi. Merkez DB'de kalır, kiracı açılmaz
    │
    └─ diğer her şey      → domains tablosunda aranır
                            bulunamazsa 404 (kayıtlı olmayan alan adı)
                              │
                              ├─ search_path ayarlanır      → tenant_ayk
                              ├─ cache öneki ayarlanır      → t7:
                              ├─ dosya kökü ayarlanır       → tenants/{uuid}/
                              └─ rotalar çözülür
                                    markam.com/*       → Vitrin
                                    panel.markam.com/* → Yönetim paneli
```

#### M-2.3 Yönetim paneli markanın alan adında duracak

`panel.markam.com` — İkas'ın aksine (§1) ortak bir panel alan adı kullanılmayacak.

> **Neden:** ortak panelde kullanıcı giriş yaptığında hangi markaya ait olduğunu bilmen
> gerekir; yani **merkez veritabanında bir kimlik dizini** (e-posta → kiracı) tutmak
> zorunda kalırsın. Bu, iki veritabanı arasında senkron tutulması gereken bir kopyadır ve
> M-2'nin tek kazancı olan temiz izolasyon sınırını deler. Panel markanın alan adında
> durursa host zaten kiracıyı belirler, kimlik akışı tamamen kiracının kendi
> veritabanında kalır ve merkez DB hiçbir son kullanıcıyı tanımaz.

Bir marka sahibinin birden çok mağazası olması senaryosu çıkarsa birleşik panel sonradan
eklenir — o zaman da yeni bir kimlik modeli değil, önüne konan bir yönlendirme katmanı olur.

#### M-2.4 Beş tuzak — kiracılıkta hatalar hep burada çıkar

Şemayı değiştirmek işin kolay kısmı. Hatanın çıktığı yerler bunlar ve beşi de
**tek bir yerde**, `app/Tenancy/` altında çözülür; her kullanım yerinde tekrar edilmez.

| Tuzak | Ne olur | Kural |
|-------|---------|-------|
| **Kuyruk** | İş kuyruğa girer, worker onu kiracı bağlamı olmadan alır ve **merkez bağlamda** çalıştırır. A markasının sipariş e-postası B'nin müşterisine gider | Paket kiracı kimliğini **işin gövdesine yazıyor**: `createPayloadUsing` ile `'tenant_id' => $id` ekleniyor (`Bootstrappers/QueueTenancyBootstrapper.php:128,157`), worker `JobProcessing` olayında onu okuyup kiracıyı devreye alıyor, `JobProcessed`/`JobFailed`'de geri dönüyor (`:62,65,78,79`) |

> ⚠️ **0.5'te bu tuzağa gerçekten düştük — sebebi kod değil, ÇALIŞAN SÜREÇTİ.**
> `worker` konteyneri paket kurulmadan önce başlatılmıştı; `queue:work` kodu belleğe
> aldığı için kiracılık dinleyicisi hiç kaydedilmemişti. İki farklı markanın işi de
> **merkez klasöre** yazdı ve ikincisi birincinin üstüne bindi.
>
> **Hiçbir hata çıkmadı** — iş `DONE` dedi, süresini yazdı, her şey normal göründü.
> `docker compose restart worker` sonrası doğru çalıştı.
>
> Sonuç: deploy adımlarında `queue:restart` (veya worker yeniden başlatma) bir "iyi olur"
> değil, **kiracı izolasyonunun şartı**.
| **Cache** | Tek Redis, ortak anahtar alanı. `product:12` iki markada iki farklı ürün | Paket bunu **önekle değil etiketle (tag)** çözüyor: kiracı devredeyken `cache` yöneticisi `Stancl\Tenancy\CacheManager` ile değiştiriliyor ve her çağrıya `tenant<id>` etiketi ekleniyor (`src/CacheManager.php:19,34`). ⚠️ **Şartı: etiket destekleyen bir depo.** Redis ve Memcached destekler; `file` ve `database` **desteklemez** — o sürücülerde kiracı cache'i çalışmaz |
| **Dosya** | Görseller ortak kökte; yol tahmin edilerek başka markanın görseline erişilir | Paket `storage_path()`'in **kökünü** değiştiriyor: `storage/tenant<kimlik>/app/…` (`Bootstrappers/FilesystemTenancyBootstrapper.php:38,42,64`). Kimlik uuid olduğu için yol tahmin edilemiyor. ⚠️ Bu klasörler `.gitignore`'a alınmak zorunda — yoksa markaların yüklediği dosyalar depoya girer |
| **Zamanlanmış iş** | Zamanlayıcı bir istekten doğmaz — alan adı yok, middleware yok, kiracı yok. Görev **merkez bağlamda** koşar ve hiçbir markanın verisine dokunamaz; hata da vermez | Pakette hazır çözümü **yok**, kural bizde: marka verisine dokunan her görev `tenants:run <komut>` ile sarılır (`routes/console.php`'de kural olarak yazılı). ⚠️ Ayrıca zamanlayıcıyı **çalıştıran bir süreç** gerekiyor — `docker-compose.yml`'e `scheduler` servisi eklendi; onsuz hiçbir görev hiç çalışmaz |
| **`search_path` sızması** | Şema bazlı modele özel: bir istekte kurulan `search_path` sonraki isteğe taşınırsa B markasının isteği A'nın şemasında çalışır | Paket bunu **sıfırlayarak değil, bağlantıyı imha ederek** çözüyor: her kiracıya `search_path`'i config'inde gömülü ayrı bir `tenant` bağlantısı açılıyor, geçişte eskisi `purge` ediliyor (`Database/DatabaseManager.php:41,51`). ⚠️ Bu koruma **Laravel'in kendi bağlantı yönetimi içinde** geçerli; pgBouncer gibi dış havuzlayıcıda fiziksel oturum paylaşıldığı için `transaction` modu ayrıca incelenmeli |

⚠️ Beşinin ortak özelliği **sessiz** olmalarıdır. Hata fırlatmazlar; yanlış veriyi
sakince işlerler. Teşhisi "bazen yanlış ürün görünüyor" şikâyetiyle başlar ve günler alır.
Baştan doğru kurulmasının sebebi budur.

#### M-2.5 Yeni marka açma

```
1. Kontrol düzleminde kayıt   → tenants satırı, durum: provisioning
2. Şema oluştur               → CREATE SCHEMA tenant_ayk
3. Migration çalıştır         → boş ama tam şema (database/migrations/tenant)
4. Varsayılanları ekle        → KDV, kargo, yasal metin şablonları, tema
5. Sahip kullanıcıyı oluştur  → e-posta ile davet
6. Geçici alan adı bağla      → markam.tikmarka.com
7. Durum: active
```

Tek komut: `php artisan tenant:create`. **Bu akış iskeletin satılabilirlik testidir** —
kurulum bir README talimat listesiyse elimizdeki ürün değil taslaktır (M-1).

Marka kendi alan adını bağlamak istediğinde: DNS'i bize yönlendirir, `domains` tablosuna
eklenir, TLS sertifikası **istek anında** otomatik alınır.

⚠️ **Özel alan adı + otomatik sertifika hafife alınan operasyonel iştir.** Ters vekil
seçimi bu yüzden Faz 0 kararıdır: Caddy bunu kutudan çıktığı gibi yapar, Nginx ile
yapılacaksa ayrı bir sertifika yönetim katmanı gerekir. Karar Faz 0'da alınacak.

#### M-2.6 Kiracılık kütüphanesi: `stancl/tenancy`

Elle yazmak yerine olgun paketi kullan. Paket hem şema hem ayrı-veritabanı modelini
destekliyor; **ikisi arasındaki geçiş tek satır yapılandırma** (M-2'deki "bu bir ayardır"
notunun somut karşılığı).

> **Neden:** elle yazması kolay olan kısım (host → şema çözümleme, ~150 satır) hatanın
> **çıkmadığı** yerdir. Hatanın çıktığı yer M-2.4'teki beş tuzak ve framework'ün
> başlatma sırasıdır — paketin çözdüğü tam olarak o. Kendi yazmak, o tuzakları tek tek
> ve sessiz hatalarla yeniden keşfetmek demektir.

⚠️ **Bilinerek kabul edilen bedel:** paket framework'ün bootstrap sürecine giriyor, hafif
bir bağımlılık değil. Sürüm yükseltmelerinde Laravel ile uyum takip edilmek zorunda.
Alternatif "aynı işi yapan, daha az test edilmiş kendi katmanımız" olduğu için takas net.

#### M-2.7 Kod sınırı — tek sert kural

```
app/
├── Platform/     ← merkez DB: kiracı, abonelik, alan adı, kontrol düzlemi
├── Tenancy/      ← çözümleme, bağlam, kurulum. Kiracılığın TAMAMI burada
├── Domain/       ← iş mantığı. Kiracıdan HABERSİZ
│   ├── Catalog/  Cart/  Order/  Payment/  Stock/ …
└── Http/
    ├── Storefront/   ← vitrin
    ├── Panel/        ← marka yönetimi
    └── Platform/     ← kontrol düzlemi

database/migrations/
├── landlord/     ← merkez şema
└── tenant/       ← marka şeması
```

> **Kural:** `app/Domain/` altındaki hiçbir dosya `Tenancy` sınıflarını import etmez ve
> "hangi kiracıdayım" diye sormaz.

Bu sınır korunduğu sürece iş mantığı test edilebilir, taşınabilir ve kiracılık modeli
değişse bile dokunulmadan kalır. Delinirse M-2'nin bütün kazancı kaybolur: kiracılık kodun
her yerine sızar ve geri çıkarılamaz.

---

### M-3 — Vitrin ve panel arayüzü en sona bırakılıyor

Arayüz teknolojisi (Blade+Livewire / Inertia / headless SPA) **şimdi seçilmeyecek.**
Backend çalışır hâle geldikten sonra ayrıca kararlaştırılacak. Geliştirme boyunca arayüz
yerine **testler ve HTTP istekleri** kullanılacak.

> **Şartı — pazarlık konusu değil:** iş mantığı controller'ın veya şablonun içine değil,
> **servis katmanına** yazılacak. Sepet toplamı, vergi ayrıştırması (domain-model §8),
> stok kontrolü, sipariş durum geçişleri — hiçbiri bir Blade dosyasında veya controller
> metodunda yaşamayacak.
>
> Aksi hâlde "arayüzü sonra seçeriz" kararı, pratikte "backend'i sonra yeniden yazarız"
> anlamına gelir. Bu şart tutulursa vitrin geldiğinde yazılacak şey neredeyse tamamen
> **yeni koddur**; mevcut kodda dokunulacak yer kalmaz.

**Şemaya etkisi yok.** Markaya özel görünüm verisi `settings.theme` grubunda `jsonb`
olarak duruyor (domain-model §4) — hangi teknoloji seçilirse seçilsin veri tarafı hazır.
Yani erteleme bir boşluk bırakmıyor, sadece bir dosya yazmayı geciktiriyor.

⚠️ **M-1 ile ilişkisi.** M-1'e göre markaya satılan şeyin kendisi vitrindir; dolayısıyla
vitrin yazılana kadar ürün **satılabilir değildir.** Bu bir çelişki değil, bir sıralama
kararı: önce çalışan bir çekirdek, sonra satılabilirlik. Öğrenme projesi hedefiyle
(M-2) tutarlı.

---

### M-4 — Ters vekil: Caddy

Nginx yerine **Caddy.** Karar tek bir gereksinimden türüyor: marka kendi alan adını
bağladığında sertifikanın **istek anında, elle müdahale olmadan** alınması (M-2.5).

**Caddy'de akış:** tanımadığı bir alan adına istek gelince Caddy uygulamaya sorar —
`GET /tenancy/domain-check?domain=markam.com`. Laravel `domains` tablosuna bakıp 200 veya
404 döner. 200 ise sertifikayı Let's Encrypt'ten kendisi alır, saklar, süresi dolunca
kendisi yeniler. Bizim tarafımızdaki iş bir tablo sorgusundan ibaret.

> **Neden Nginx değil:** Nginx'in yerleşik on-demand TLS'i yok. Aynı sonuç için üç yol var
> ve üçü de bakımı bize kalan fazladan bir parça getiriyor: certbot + her yeni markada
> reload tetikleyen bir iş (zamanlama sorunları), OpenResty + `lua-resty-auto-ssl` (yani
> Caddy'de hazır olanı elle kurmak), ya da ek vekil konteynerleri.

**Yan fayda:** PHP-FPM bağlantısı tek satır (`php_fastcgi app:9000`). TıkRota'dan devralınan
kural aynen geçerli: **Unix soketi değil TCP, adres ortam değişkeninden okunur** — böylece
ileride konteyner ayrımına geçilirse kodda hiçbir şey değişmez.

#### M-4.1 Üç tuzak — yazılmazsa bu tercih geri teper

**1. `ask` ucu zorunlu, opsiyonel değil.** Konulmazsa Caddy, IP'mize yönlendirilen
**her** alan adı için sertifika almaya çalışır. Biri rastgele bir alan adını bize
yönlendirir ve Let's Encrypt kotamızı yakar. On-demand TLS'in klasik hatası budur.

**2. Caddy'nin `/data` dizini kalıcı volume olacak.** Sertifikalar orada durur. Volume
verilmezse her konteyner yeniden başlatmada tüm sertifikalar sıfırdan istenir ve birkaç
deploy sonra hız limitine takılırız.

**3. Hız sınırlama uygulama katmanında kalacak.** Nginx'in hız sınırlaması olgun,
Caddy'ninki daha az denenmiş. Giriş ve ödeme uçlarının korunması Laravel `throttle` ile
yapılacak. **Bu bilerek verilmiş bir taviz**, farkında olunmadan oluşmuş bir boşluk değil.

⚠️ **Kabul edilen eksi:** Nginx sektörde çok daha yaygın; mevcut sistemlerde ve iş
ilanlarında karşılaşma ihtimali yüksek. Buna karşılık seçimin gerekçesi savunulabilir:
kiracı başına özel alan adı gerektiği için on-demand TLS'i yerleşik destekleyen vekil
seçildi — gereksinimden türeyen bir karar.

Caddy + Nginx'i birlikte kullanmak (TLS Caddy'de, servis Nginx'te) teknik olarak mümkün
ama bu ölçekte iki vekil bakmak, çözdüğü problemden büyük.

---

## 3. Açık sorular (sıradaki kararlar)

0. ✅ **M-1'in "zorunluluklar" tablosu — karara bağlandı: tamamı kapsamda, fazlara
   dağıtıldı.** Dağılım `PLAN.md`'nin faz haritasında. İnceleme sonucu maddelerin çoğu ya
   bedava ya birkaç satır; **iki tanesi ucuz değil** ve ikisi de plana ayrıca işlendi:
   *mesafeli satış sözleşmesi onayı* (`orders`'a kanıt kolonları, 1D) ve *KVKK veri silme*
   (silme değil anonimleştirme, Faz 2). Aşağıdaki gerekçe kayıt için duruyor.

   Bu bir **öğrenme projesi.** M-1 ise ticari bir hizmet varsayarak yasal katmanı (KVKK,
   mesafeli satış sözleşmesi, cayma hakkı, e-arşiv), yedeklemeyi ve veri dışa aktarmayı
   "kapsam içi, ertelenemez" saymıştı. Öğrenme projesinde bu, TıkRota K-6'daki *"dar kapsam,
   yüksek derinlik"* ilkesiyle çelişiyor.

   **M-1'in mimari kısmı tartışmaya açık değil** — abonelik, bizim barındırmamız, kaynak
   kod teslimi olmaması aynen duruyor. Ertelenen tek şey o zorunluluklar tablosu.

   **İnceleme sonucu: maddelerin hepsi sonradan eklenebilir.** Yedekleme koddan bağımsız;
   yasal metinler `settings`'te bir metin alanı; veri dışa aktarma şema bazlı model
   sayesinde (M-2) zaten `pg_dump -n <şema>`; cayma hakkı/iade Faz 3'te durum makinesi
   olarak zaten planlı. Tek gerçek yük **e-fatura entegrasyonu** — o da ertelenebilir.

   ⚠️ **Ama e-faturanın ertelenmesinin tek bir şartı var: vergi alanları Faz 1'de açılacak.**
   Her `order_item` satırında satın alma anındaki **KDV oranı ve tutarı donmuş olarak**
   saklanmalı; siparişte fatura adresi ve kurumsal alanlar (VKN, vergi dairesi) bulunmalı.

   > **Neden:** fatura kesmek, geçmiş bir siparişin vergisini yeniden üretmek demektir.
   > KDV oranları değişir. Sipariş anındaki oran saklanmamışsa altı ay sonra o siparişin
   > faturası doğru üretilemez. Bu, TıkRota §5.1'deki kupon kararının birebir aynısı —
   > *mekanizma sonraki faza, kolonlar bu faza.* Maliyeti üç kolon; sonradan eklemenin
   > maliyeti tutar hesabının formülünü değiştirmek.

   Bu şart `docs/domain-model.md` yazılırken uygulanacak. Tablonun kalanına dair karar
   faz haritasından önce verilecek.

1. ~~**Vitrin teknolojisi**~~ → **M-3 ile ertelendi.** Backend bittikten sonra konuşulacak.
2. ~~**Sipariş modeli**~~ → `domain-model.md` §7 ile karara bağlandı (üç katman:
   `orders` → `fulfillments` → `order_items`, `payment_status` / `fulfillment_status` ayrı).
3. ~~**Misafir ödeme**~~ → `domain-model.md` §6 ile karara bağlandı: **var.**
4. ~~**Marka personeli yetkilendirmesi**~~ → `domain-model.md` §3 ile karara bağlandı:
   izin bazlı RBAC, izin listesi kodda sabit.

5. ~~**Ters vekil**~~ → **M-4 ile karara bağlandı: Caddy.**

6. ~~**Geliştirme ortamı**~~ → karara bağlandı: **PHP 8.4 · Laravel 12 · PostgreSQL 17
   (`citext`) · Redis · Caddy · kuyruk işçisi ayrı konteyner · yerelde `*.localhost`.**
   Ayrıntılar `PLAN.md` §0.2.

**Sıradaki kararlar:**
7. **Ödeme sağlayıcısı** — Faz 1'de `FakePaymentProvider` ile yürünecek; gerçek sağlayıcı
   (iyzico / PayTR) seçimi arayüz arkasında olduğu için ertelenebilir. Anahtarlar kiracı
   şemasında şifreli duracak (domain-model §4).
8. **Faz haritası** — sıradaki dosya: `PLAN.md`.
