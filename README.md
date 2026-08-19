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


* Iyzico Örnek Hesaplar: Test Kullanıcısı (isim soyisim)
* Numara / ccv / tarih
* 5168 8800 0000 0002 / 123 / 12/29 (master)
* 

* 4111 1111 1111 1129 / 123 / 12/29 (Yetersiz Bakiye)
* 4122 1111 1111 1118 / 123 / 12/29 (Geçersiz Kart Numarası)
---

## İyileştirme

* Ürün sayfasının altında benzer ürünler, beğenilenler (hepsiburada gibi)
* Ürünlere tıklamayı sayma
* Hepsiburada gibi büyük e-ticaret sitelerinin bu tarz niş özelliklerini tespit edip liste oluşturalım
* Vitrinde kullanıcı için ürün favorileme yok, eski siparişlerim olmalı

* Koleksiyonlar kurallı tanımlarken uyarı veriyor kural bir nesne olmalı diye, kullanıcı panelinde koleksiyonların kullanıldığı bir yer görmedim eksik var.
* Marka panelinde varyant ekleyebiliyorum üründe ama bu varyantlara özellik ekletmiyor oraya onu da ekleyelim
* Marka panelinde adres kaydı başlık alanı zorunludur diyor baktım ama frontda onun için yer yok.
* "https://marka-a.localhost/odeme/ode/" da ödeme için sandbox değeri girdim ve çalıştı ama sms'i girince buraya attı "Test Kullanıcı" isimli bir hesapla yaptım ve ürün gidiyor stoğa bakınca, geçersiz kartları denediğimde stok azalmıyor ama iki geçerli veya geçersiz olsun sonucunda bir mesaj döndürmüyor vitrinde kullanıcıya aynı zamanda sayfanın içindeki sayfa kalıyor bu aşağıdaki yazıya dönüşüyor web in web yani
"
ERR_BLOCKED_BY_LOCAL_NETWORK_ACCESS_CHECKS
marka-a.localhost is blocked
The connection is blocked because it was initiated by a public page to connect to devices or servers on your local network.
"
* Vitrinde siparişlere bakıyorum ama göremiyorum yeni verdiğim siparişleri kullanıcı olarak ve iade seçeneği de yok şu anda 
* Açılan yeni Markaları tekrar elle eklemek gerekiyor Caddy üzerinden, ben o işlemi de yönetim paneline koyalım diyorum yeni gelen Marka isteğini onay/red yapayım olur mu.

* a@a bizim doğrulamamızı geçiyor, iyzico reddediyor. Bedeli: sipariş oluşuyor, stok bağlanıyor, sonra ödeme patlıyor ve stok 60 dakika kilitli kalıyor.
PaymentProviderException tarayıcıya JSON dönüyor.
---

