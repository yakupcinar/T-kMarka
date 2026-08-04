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
0.4  ⏳  Pest test altyapısı
```
