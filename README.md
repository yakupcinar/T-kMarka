# TıkMarka

[![CI](https://github.com/yakupcinar/T-kMarka/actions/workflows/ci.yml/badge.svg)](https://github.com/yakupcinar/T-kMarka/actions/workflows/ci.yml)

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
git clone https://github.com/yakupcinar/T-kMarka.git
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
