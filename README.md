# TıkMarka

[![CI](https://github.com/yakupcinar/TikMarka/actions/workflows/ci.yml/badge.svg)](https://github.com/yakupcinar/TikMarka/actions/workflows/ci.yml)

Tek markanın kendi müşterisine sattığı e-ticaret uygulaması (D2C) — **çok kiracılı**
kurulmuş: aynı kod tabanı N markaya hizmet eder, her marka kendi alan adında, kendi
verisiyle.

Pazaryeri **değil**. Satıcı hesabı, komisyon, hakediş, sepetin satıcılara bölünmesi yok.

> 🚧 **Geliştirme aşamasında.** Şu an Faz 0 (altyapı). Ayrıntılı yol haritası: [`PLAN.md`](PLAN.md)

---

## Mimari

```
  tarayıcı
     │ https://markam.com
     ▼
   caddy ────────▶ app (php-fpm 8.4) ──┬──▶ postgres 17    tek db, marka başına şema
   TLS · public/   Laravel 12          ├──▶ redis          cache + kuyruk
                                       └──▶ mailpit        yerel mail (dev)
                   worker ─────────────┘
                   queue:work · aynı imaj
```

**Kiracı ayrımı şemayla yapılır, `tenant_id` kolonuyla değil.**

```
  tek PostgreSQL veritabanı
  ├── public          merkez: kiracılar, alan adları, abonelikler
  ├── tenant_amarka   products · orders · customers · settings
  └── tenant_bmarka   (aynı tablolar, ayrı şema)

  İstek geldiğinde alan adına bakılır ve search_path o markanın şemasına
  kilitlenir. Diğer şema o istek süresince yok hükmündedir — sorgu yanlış
  yazılsa bile başka markanın verisi görünmez.
```

Kararların gerekçeleri: [`docs/pre-setup.md`](docs/pre-setup.md) ·
Veri modeli: [`docs/domain-model.md`](docs/domain-model.md) ·
Tek sayfalık özet: [`docs/summary.md`](docs/summary.md)

---

## Kurulum

Gereken tek şey **Docker**. Yerel PHP, Composer veya PostgreSQL kurulumu gerekmiyor.

```bash
git clone https://github.com/yakupcinar/TikMarka.git
cd T-kMarka
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

Sonra: **https://marka-a.localhost**

> Sertifika uyarısı normaldir — yerelde Caddy'nin kendi iç otoritesi kullanılıyor
> (Let's Encrypt `.localhost` adreslerine sertifika veremez).

**Servisler**

| Adres | Ne |
|---|---|
| https://marka-a.localhost · https://marka-b.localhost | Kiracı alan adları |
| http://localhost:8025 | Mailpit — giden mailler burada yakalanır |
| `localhost:5433` | PostgreSQL (host portu 5433, konteyner içi 5432) |

---

## Komutlar

```bash
docker compose exec app composer lint       # biçimlendir (Pint)
docker compose exec app composer analyse    # statik analiz (Larastan, seviye 8)
docker compose exec app composer test       # testler (Pest)
```

Testler ayrı bir veritabanında (`tikmarka_test`) ve **PostgreSQL üzerinde** koşar —
SQLite'ta değil, çünkü şema, `citext`, `jsonb` ve `SELECT FOR UPDATE` orada yok.

---

## Teknoloji

| Katman | Seçim | Neden |
|---|---|---|
| Backend | PHP 8.4 · Laravel 12 | — |
| Veritabanı | PostgreSQL 17 | şema bazlı kiracılık · `jsonb` · `citext` · satır kilidi |
| Kiracılık | `stancl/tenancy` (şema modu) | izolasyon yapıdan gelir, kod disiplininden değil |
| Cache / kuyruk | Redis | — |
| Ters vekil | Caddy | özel alan adları için on-demand TLS |
| Kalite | Pint · Larastan (8) · Pest | — |

Arayüz teknolojisi henüz **seçilmedi** — backend çalışır hâle gelene kadar erteleniyor
(karar M-3). Geliştirme boyunca "gözümüz" testler.

---

## Durum

```
  Faz 0  altyapı + kiracılık zemini    ▓▓▓▓▓▓▓░░░   0.1–0.4 bitti
  Faz 1  çekirdek mağaza               ░░░░░░░░░░
  Faz 2  olgunlaşma                    ░░░░░░░░░░
  Faz 3  satılabilirlik                ░░░░░░░░░░
  Faz 4  arayüz                        ░░░░░░░░░░
  Faz 5  entegrasyonlar                ░░░░░░░░░░
  Faz 6  dağıtım                       ░░░░░░░░░░
```

---

## Lisans

Tüm hakları saklıdır. Kod herkese açık olarak görüntülenebilir; kullanım, kopyalama
veya dağıtım için izin gerekir.


## Örnek Hesaplar

*	https://marka-a.localhost/ · https://marka-b.localhost/ // mehmet@ornek.test / 123
* https://marka-a.localhost/yonetim // sahip@marka-a.localhost / 123
* https://localhost/yonetim // yakup@tikmarka.test / 123


* Iyzico Örnek Hesaplar: Test Kullanıcısı (isim soyisim) test@gmail.com (mail)
* Numara / ccv / tarih
* 5168 8800 0000 0002 / 123 / 12/29 (master)
* 

* 4111 1111 1111 1129 / 123 / 12/29 (Yetersiz Bakiye)
* 4122 1111 1111 1118 / 123 / 12/29 (Geçersiz Kart Numarası)
---

## İyileştirme

> Açık kusurlar ve fikirler. Biten maddeler **silinmiyor** — aşağıdaki
> "Yapıldı" bölümüne taşınıyor ki tekrar kontrol edilebilsin.

### Açık kusurlar

**Vitrin — sipariş / ödeme**

* Vitrinden sipariş ödemesini yaptım, vitrinde "ödendi, hazırlanıyor" yazıyor; marka paneline baktım oraya ya düşmemiş ya da saati yanlış düşmüş — bunun testini yap.
* Sepete ürün koydum, ödeme kısmına kadar geldim, sonra geri çıktım. Siparişlerimde "ödeme bekleniyor" diye duruyor. Sağ üstteki sayaç 2 gösteriyor ama içine girince boş. İşlemi tekrarlayınca siparişler arttı, sağ üstteki sayı 2'de sabit kaldı.
* Vitrinde **iade seçeneği yok** — uçları var (`api/orders/{siparis}/returns`), ekranı yok.
* `https://marka-a.localhost/odeme/ode/` — sandbox değeriyle ödeme çalıştı, SMS'i girince aşağıdaki hataya düştü. Geçerli/geçersiz kart fark etmeksizin vitrinde kullanıcıya **mesaj dönmüyor** ve sayfanın içindeki sayfa (web in web) kalıyor.
* Kayıtlı a "shipping.full name metin olmalıdır. shipping.phone metin olmalıdır. shipping.city metin olmalıdır. shipping.district metin olmalıdır. shipping.line1 metin olmalıdır." uyarısı alıyor ve ödeme ekranına gitmiyor butona basınca

```
ERR_BLOCKED_BY_LOCAL_NETWORK_ACCESS_CHECKS
marka-a.localhost is blocked
The connection is blocked because it was initiated by a public page
to connect to devices or servers on your local network.
```

> ⚠️ Bu hatanın kendisi **bizim kusurumuz değil**: Chrome, genel ağdaki bir
> sayfanın (iyzico) yerel ağa (`.localhost`) dönmesini engelliyor. Gerçek
> alan adında olmaz. Ölçmek için tünel gerekiyor — `make kaldir`.
> Çerçeveden çıkış betiği yazılı ve doğru; sayfa hiç yüklenmediği için
> çalışamıyor. "Mesaj dönmüyor" da bunun sonucu.

**Marka paneli — katalog**

* Yeni koleksiyonu "elle seç" ile oluşturdum ama ürün seçtirmiyor. Ürünler kısmında ya da başka bir yerde ürün koleksiyona konabilmeli.
* Varyant ekleyebiliyorum ama **ikinci varyantta bozuk bir sayfa** açılıyor. Ayrıca varyantlara **eksen** ekleyemiyorum (Renk, Beden…).
* Ürünü ekleyince "şimdi varyantları ekleyebilirsin" yazısı geliyor ama **varyant ve görsel bölümü gelmiyor**; sayfa değiştirip ürüne tekrar tıklayınca geliyor. Oluşturmaya bastığım anda o bölümler de gelsin.

**Merkez yönetim**

* Açılan yeni markaları Caddy'ye **elle eklemek** gerekiyor. Bu işi yönetim paneline koyalım: gelen marka isteğini onay/red edeyim.

### Fikirler

* Ürün sayfasının altında benzer ürünler, beğenilenler (Hepsiburada gibi)
* Ürüne tıklamayı sayma, kullanıcı başına veri tutma; marka panelinde düzgün formatta bir bölüm
* Vitrinde ürün favorileme yok
* Hepsiburada gibi büyük e-ticaret sitelerinin niş özelliklerini tespit edip liste oluşturalım

---

## Yapıldı

> ⚠️ Buradaki maddeler **ölçüldü ve kırma denemesinden geçti**, ama liste
> silinmiyor: kendin de kontrol edebilesin diye nerede sınayacağın yazılı.

| Kusur | Nerede kontrol edilir | Blok |
|---|---|---|
| Kurallı koleksiyon "kural bir nesne olmalı" diyordu | Panel → Koleksiyonlar → tür "Kurallı" seç, koşul editörü **oluşturma formunda** açılır | 4.5H |
| Koleksiyonların vitrinde kullanıldığı yer yoktu | `/koleksiyonlar` ve `/koleksiyon/{slug}`; başlıktaki bağlantı yalnızca aktif koleksiyon varsa görünür | 4.5H |
| Kategori kuralı yazınca koleksiyon 404 veriyordu | Kural değerinde kategori artık **listeden seçiliyor**; kategorisi silinse bile sayfa düşmüyor | 4.5H.1 |
| Adres kaydı "başlık alanı zorunludur" diyordu, ekranda yeri yoktu | Vitrin → Hesabım → Adresler → "Ev, İş…" alanı | 4.5G |
| `a@a` doğrulamayı geçiyor, iyzico reddediyordu | Ödemede `a@a` yaz → **sipariş oluşmaz**, "alan adı geçersiz görünüyor" | 4.5G |
| Ödeme hatası tarayıcıya ham JSON dönüyordu | Ödeme başlatılamadığında ekranda **Türkçe mesaj**; API'ye hâlâ JSON | 4.5G |
| Vitrinde verdiğim siparişleri göremiyordum | Giriş yap → sipariş ver → Hesabım → **Siparişlerim**'de görünür | 4.5I |
| Kayıtlı adres ödemede sorulmuyordu / "line" uyarıları veriyordu | Adres kaydet → Ödeme → **liste + seçim**, "Başka adrese gönder" formu açar | 4.5I |
| Ürün oluşturunca varyant sayfasına gitmiyor | `POST /yonetim/urunler` → `302 → /yonetim/urunler/{uuid}` — **ölçüldü, zaten doğruydu** | 4.5G |

> ⚠️ "Vitrinde siparişleri göremiyorum" maddesinin **iade yarısı hâlâ
> açık** — o yüzden yukarıdaki listede duruyor.
>
> ⚠️ Ürün oluşturma yönlendirmesi doğru çalışıyor ama **açılan sayfada
> varyant/görsel bölümü gelmiyor** — o ayrı bir kusur ve açık listede.
