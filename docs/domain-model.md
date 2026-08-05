# TıkMarka — Domain Modeli / Şema Taslağı

> `pre-setup.md`'deki kararların (M-1, M-2) veri modeline dönüşmüş hâli.
> Henüz migration değil — tartışılıp onaylanacak taslak. Tarih: **2026-08-03**

**Varsayımlar** (değişirse şema değişir):

- Tek para birimi: **TRY**. Çoklu para birimi kapsam dışı.
- Para tipi: **`numeric(12,2)`** — kayıpsız ondalık. Float hiçbir yerde kullanılmayacak.
- **Fiyatlar KDV dâhil saklanır.** Türkiye'de tüketiciye gösterilen fiyat vergi dâhildir;
  vergi bu tutarın içinden ayrıştırılır (§8).
- Tüm `id` alanları `bigserial`; dışarıya açılan kayıtlarda ayrıca `uuid`.
- Tüm tablolarda `created_at`, `updated_at`. Silme **soft delete** (`deleted_at`).
- Zaman: `timestamptz`, UTC saklanır.
- Büyük/küçük harf duyarsızlık gereken alanlar (e-posta):
  **`citext` DEĞİL — sınırda küçültme + `CHECK` kısıtı.**

  > ⚠️ **0.5/1A.1'de denendi, kiracı şemasında ÇALIŞMIYOR.** `citext` eklentisi
  > `public` şemasında duruyor; marka bağlantısının `search_path`'i yalnızca
  > `tenant_xxx` olduğu için operatörleri bulunamıyor ve PostgreSQL **sessizce**
  > düz metin karşılaştırmasına düşüyor — `Ali@site.com` ile `ali@site.com`
  > farklı sayılıyor, hata da vermiyor. `public`'i `search_path`'e eklemek de
  > çözüm değil: o zaman marka şemasında olmayan bir tablo sessizce merkezdekine
  > düşer ve izolasyon delinir.
  >
  > **Uygulanan desen:** değer modelde küçültülür (sınırda normalleştirme, alan
  > adlarında da böyle) + veritabanında `CHECK (email = lower(email))` ham SQL
  > yolunu da kapatır. Benzersizlik düz `unique` ile sağlanır.
  >
  > `public` şemasındaki tablolar (`domains`) `citext` kullanmaya devam edebilir.

---

## 1. İki şema katmanı

M-2 gereği tek veritabanı, kiracı başına ayrı şema. Model iki ayrı dünyada yaşıyor:

```
┌─ şema: public ─────────────── MERKEZ (platform) ──────────┐
│                                                            │
│   tenants ──< domains                                      │
│      │                                                     │
│      └──< subscriptions >── plans                          │
│                                                            │
│   platform_users            ← biz                          │
│                                                            │
│   Ürün / sipariş / müşteri BURADA YOK                      │
└────────────────────────────────────────────────────────────┘

┌─ şema: tenant_<marka> ─────── MARKA (her biri aynı yapı) ──┐
│                                                            │
│              ┌──────────┐          ┌───────────┐           │
│              │ customers│          │   users   │ (personel)│
│              └────┬─────┘          └─────┬─────┘           │
│         ┌─────────┼─────────┐            │                 │
│   ┌─────▼───┐ ┌───▼───┐ ┌───▼────┐  ┌────▼────┐            │
│   │addresses│ │ carts │ │ orders │  │  roles  │            │
│   └─────────┘ └───┬───┘ └───┬────┘  └─────────┘            │
│                   │         │                              │
│            ┌──────▼────┐    ├──< fulfillments               │
│            │cart_items │    │        └──< fulfillment_items │
│            └──────┬────┘    │                     │         │
│                   │         └──< order_items ◄────┘         │
│                   │                   │                     │
│   categories ──< products ──< product_variants               │
│                     └──< product_images                      │
│                                       │                      │
│                             stock_reservations               │
│                                                              │
│   settings      payments ──< refunds      events             │
└──────────────────────────────────────────────────────────────┘
```

> ⚠️ **Kiracı şemasında `tenant_id` kolonu yoktur** (M-2.1). `search_path` kapsamı
> belirliyor. Bunun güzel bir yan etkisi: `slug`, `sku`, `email` gibi alanlardaki
> `unique` kısıtları **doğal olarak marka içinde** çalışır. İki farklı marka aynı ürün
> slug'ını kullanabilir ve bunun için tek satır ek kod yazılmaz.

---

## 2. Merkez şema (`public`)

### `tenants`
| Alan | Tip | Not |
|------|-----|-----|
| id, uuid | | |
| name | varchar(120) | marka adı (bizim gördüğümüz) |
| schema_name | varchar(63) unique | `tenant_ayk` |
| connection | varchar(50) null | null = varsayılan sunucu. M-2.1'deki ölçek kaçış kapısı |
| status | enum | `provisioning` / `active` / `suspended` / `archived` |

### `domains`
| Alan | Tip | Not |
|------|-----|-----|
| tenant_id | FK | |
| domain | citext unique | `markam.com`, `panel.markam.com`, `markam.tikmarka.com` |
| is_primary | boolean | vitrinin kanonik adresi — yönlendirme buraya yapılır |

> Bir kiracının birden çok alan adı olur: kalıcı adres, `www` varyantı, panel alt alan adı
> ve kurulumdaki geçici adres (M-2.5). Kiracı çözümlemesi bu tabloya bakar (M-2.2).

### `plans` / `subscriptions`
| Tablo | Alanlar |
|-------|---------|
| `plans` | key, name, price, `limits` (jsonb — ürün sayısı, personel sayısı) |
| `subscriptions` | tenant_id, plan_id, status, trial_ends_at, current_period_end |

> Faz 4'e kadar tek satırlık bir "varsayılan plan" ile yaşayabilir. Tabloların şimdi
> açılması, `tenants`'a durum alanı eklemekten daha ucuz.

### `platform_users`
Kontrol düzlemine (M-2.2, `app.tikmarka.com`) girecek olan biziz. Marka personeliyle
**hiçbir ilişkisi yok** — ayrı şemada, ayrı tabloda, ayrı kimlik akışında.

---

## 3. Kimlik — müşteri ve personel ayrı tablolar

**Karar: `customers` ve `users` ayrı tablolar.** TıkRota'da alıcı ve satıcı aynı `users`
tablosundaydı; burada ayrılıyorlar.

> **Neden ayrı:** TıkRota'da ayrım gereksizdi çünkü *aynı kişi* hem alıcı hem satıcı
> olabiliyordu — tek kimlik doğal çözümdü. Burada iki popülasyon farklı: markanın müşterisi
> ile markanın çalışanı aynı kişi değil. Ayrıca:
> - İki farklı yüzeyden giriş yapıyorlar (vitrin ve panel — M-2.2)
> - Alanları örtüşmüyor: müşteride pazarlama izni ve sipariş geçmişi, personelde rol var
> - Tek tabloda birleştirilirse "müşteri bir hatayla panele girebilir mi" sorusu sürekli
>   canlı kalır. Ayrı tabloda bu soru **sorulamaz** hale gelir.

### `customers` — markanın müşterileri
| Alan | Tip | Not |
|------|-----|-----|
| id, uuid | | |
| email | varchar(190) unique null | **null olabilir** — misafir siparişi (§6). `citext` değil — §0'daki nota bak |
| password | varchar null | misafir müşteride null |
| name, phone | varchar | |
| accepts_marketing | boolean default false | KVKK — açık rıza, varsayılan kapalı |
| email_verified_at | timestamptz null | |

> ⚠️ `email` hem `unique` hem `null` olabilir. PostgreSQL'de bu sorunsuz çalışır (birden
> çok `null` benzersizliği bozmaz) ve misafir siparişini mümkün kılan şey budur.

### `users` — marka personeli
| Alan | Tip | Not |
|------|-----|-----|
| id, uuid | | |
| email | varchar(190) unique | `citext` değil — §0 |
| password | varchar | |
| name | varchar(120) | |
| is_owner | boolean default false | kurulumda oluşan sahip; **silinemez, rolü düşürülemez** |

> `is_owner` bir rol değil bir emniyet kilidi: son yöneticinin kendini yetkisiz bırakıp
> panele kilitlenmesini engeller.

### `roles` / `role_user` / `role_permissions` — izin bazlı RBAC
| Tablo | Alanlar |
|-------|---------|
| `roles` | name, is_system (varsayılan roller silinemez) |
| `role_user` | role_id, user_id |
| `role_permissions` | role_id, `permission` varchar |

İzinler serbest metin değil, kodda tanımlı sabit bir liste: `product.view`,
`product.write`, `order.view`, `order.fulfill`, `order.refund`, `customer.view`,
`settings.write`, `staff.manage`, `finance.view` …

Kurulumda dört varsayılan rol açılır: **Sahip · Yönetici · Katalog · Sipariş & Destek.**

> **Neden sabit enum rol değil:** markanın gerçek bir organizasyonu var ve "depocu sipariş
> görsün ama iade yapamasın" gibi istekler ilk aydan gelir. Enum rollerde her yeni istek
> kod değişikliği demektir; izin tablosunda bir satır. Maliyeti iki ek tablo.
>
> ⚠️ **Kapsam sınırı:** izin listesi **kodda sabittir**, panelden yeni izin türü
> üretilemez. Üretilebilir olsaydı her izin için ayrıca "bu izin neyi kontrol ediyor"
> eşlemesi gerekirdi — bu, izin sistemini kendi başına bir projeye çevirir.

---

## 4. Mağaza yapılandırması — `settings`

**White-label iskeletin kalbi burası** (pre-setup M-1). Markaya özel olan her şey koddan
değil buradan gelir.

### `settings`
| Alan | Tip | Not |
|------|-----|-----|
| group | varchar(40) | `store` / `theme` / `checkout` / `shipping` / `tax` / `payment` / `legal` |
| key | varchar(80) | `(group, key)` birlikte unique |
| value | **jsonb** | tip serbest — metin, sayı, liste, iç içe nesne |
| is_encrypted | boolean default false | true ise `value` şifreli saklanır |

**Örnek içerik:**

| group | key | Örnek |
|-------|-----|-------|
| `store` | `name`, `logo_path`, `contact_email`, `phone` | marka kimliği |
| `theme` | `colors`, `fonts`, `home_blocks` | vitrin görünümü (Faz 2) |
| `checkout` | `guest_enabled`, `min_order_total` | |
| `shipping` | `flat_fee`, `free_threshold` | §7.1 |
| `tax` | `default_rate`, `prices_include_tax` | §8 |
| `payment` | `provider`, `api_key`, `secret` | **`is_encrypted = true`** |
| `legal` | `kvkk_text`, `distance_sales_text`, `return_policy` | pre-setup §3/0 |

> **Neden `.env` değil:** her markanın kendi ödeme hesabı, kendi kargo anlaşması var.
> Bunlar `.env`'e yazılırsa her marka için ayrı imaj/deploy gerekir — M-2'nin "tek kod
> tabanı, tek deploy" kararı çöker. Sırlar kiracının kendi şemasında, şifreli durur
> (Laravel `encrypted` cast).

> ⚠️ **Sabit yazma yasağı.** Kodda hiçbir yerde marka adı, logosu, rengi, KDV oranı veya
> yasal metin geçmeyecek. Kabul testi basit: `grep -ri "tıkmarka" app/` **boş dönmeli**
> (kiracılık altyapısı hariç).

---

## 5. Katalog

### `categories`
| Alan | Tip | Not |
|------|-----|-----|
| id, parent_id | FK null | ağaç |
| name, slug | varchar | |
| path | ltree veya varchar | atalarıyla tam yol — derin sorgu hızlandırır |
| position | smallint | menü sırası |

### `products`
| Alan | Tip | Not |
|------|-----|-----|
| id, uuid | | |
| category_id | FK null | |
| title | varchar(200) | |
| slug | varchar(220) unique | marka içinde benzersiz (§1) |
| description | text | |
| brand, model | varchar null | markanın kendi alt markaları olabilir |
| attributes | **jsonb** | kategoriye özel alanlar + GIN index |
| **tax_rate** | numeric(5,2) | KDV oranı (%1 / %10 / %20). §8 |
| status | enum | `draft` / `active` / `archived` |
| search_vector | tsvector | Faz 3 arama, GIN index |

> **Ürünün sahibi yok.** TıkRota'daki `seller_id` burada yok — şemanın tamamı zaten tek
> markaya ait. M-2'nin iş mantığı üzerindeki en görünür kazancı bu.

### `product_variants`
| Alan | Tip | Not |
|------|-----|-----|
| product_id | FK | |
| sku | varchar(64) unique | |
| barcode | varchar(64) null | |
| options | **jsonb** | `{"renk":"Kırmızı","beden":"M"}` |
| price | numeric(12,2) | **KDV dâhil** (§8) |
| compare_at_price | numeric(12,2) null | üstü çizili fiyat |
| cost_price | numeric(12,2) null | maliyet — kâr raporu için, vitrine **asla** çıkmaz |
| stock | integer default 0 | |
| is_active | boolean | |

> ⚠️ Tek seçenekli üründe bile (kitap) bir varyant kaydı açılır. İstisna yok — yoksa
> sepet/sipariş/stok kodunun tamamı iki ayrı durumu ele almak zorunda kalır.

> `cost_price` tek satıcılı modelin getirdiği yeni alan: pazaryeri platformu satıcının
> maliyetini bilmez, ama marka kendi maliyetini bilir ve kâr raporu ister.

### `product_images`
| Alan | Tip | Not |
|------|-----|-----|
| product_id | FK | |
| variant_id | FK null | doluysa o varyanta özel |
| path, alt | varchar | `path` kiracı köküne göre (M-2.4) |
| position | smallint | sıra |

---

## 6. Sepet — misafir alışverişi var

**Karar: misafir sepeti ve misafir ödeme desteklenecek.** TıkRota'daki "giriş şart"
kararının tersi.

> **Neden ters çevrildi:** pazaryerinde üyelik doğal bir beklentidir — kullanıcı zaten
> platforma geliyor. Markanın kendi sitesinde ise zorunlu üyelik dönüşümü doğrudan düşürür
> ve markaya satılan bir üründe bu bir "özellik eksiği" değil, satın alma engelidir.
> Bedeli: `customer_id` nullable + oturum anahtarı + giriş sonrası sepet birleştirme.
> Sonradan eklemek `carts` şemasını ve tüm sepet servisini değiştirmek demek olduğu için
> Faz 1'de.

### `carts`
| Alan | Tip | Not |
|------|-----|-----|
| customer_id | FK **null** | misafirde null |
| session_token | varchar(64) null | misafirde dolu, `(session_token)` unique |
| status | enum | `active` / `converted` / `abandoned` |
| last_activity_at | timestamptz | terk edilmiş sepet işi için (Faz 3) |

> ⚠️ Kısıt: `customer_id` ve `session_token`'dan **tam olarak biri** dolu olmalı.
> Veritabanı seviyesinde `CHECK` ile zorlanır — uygulama katmanına bırakılırsa bir gün
> ikisi de boş bir sepet oluşur ve kime ait olduğu bilinemez.

**Birleştirme kuralı:** misafir sepeti varken giriş yapılırsa satırlar müşterinin mevcut
sepetine taşınır; aynı varyant iki sepette de varsa **adetler toplanmaz, büyük olan alınır.**

> **Neden toplanmıyor:** kullanıcı iki farklı cihazda aynı ürünü 2'şer adet eklediyse
> niyeti 4 almak değildir. Toplama, sessizce yanlış siparişe yol açar; büyüğü almak en
> kötü ihtimalle sepette fazladan bir adet bırakır ve kullanıcı bunu görür.

### `cart_items`
| Alan | Tip | Not |
|------|-----|-----|
| cart_id, variant_id | FK | birlikte unique |
| quantity | integer | |

> ⚠️ Burada **fiyat yok.** Sepet canlıdır, fiyatı her seferinde varyanttan okur. Marka
> fiyatı değiştirirse sepette de değişir — doğru davranış budur. Ödeme adımında
> "fiyat değişti" uyarısı gösterilir.

### `addresses` — müşterinin adres defteri
| Alan | Tip |
|------|-----|
| customer_id | FK |
| title, full_name, phone | varchar |
| city, district, neighborhood | varchar |
| line1, line2, postal_code | varchar |

> ⚠️ Bu **defterdir**, sipariş adresi değil. Siparişe adres kopyalanır (§7).

---

## 7. Sipariş — üç katman

TıkRota'da katmanlar `orders → seller_orders → order_items` idi. Tek satıcıda ortadaki
katman **silinmez, anlamı değişir:**

```
orders             → ödeme ve fatura seviyesi
fulfillments       → sevkiyat/paket seviyesi
order_items        → donmuş satırlar
fulfillment_items  → hangi satır, hangi pakette, kaç adet
```

> ⚠️ **Ortadaki katmanı silme dürtüsüne direniyoruz.** Tek markalı bir sipariş de birden
> çok pakette çıkabilir: bir ürün stokta, biri tedarikte; ya da farklı depolardan. Katman
> silinirse kısmi sevkiyat, kısmi iptal ve kısmi iade kodu bir daha temizlenmemek üzere
> bozulur. Bu, TıkRota'daki "Tuzak 3"ün tek satıcılı hâli.

### `orders`
| Alan | Tip | Not |
|------|-----|-----|
| id, uuid | | |
| order_number | varchar(20) unique | müşteriye gösterilen numara |
| customer_id | FK null | misafir siparişinde null |
| email | varchar(190) | **her zaman dolu** — misafir siparişinin tek iletişim kanalı |
| **payment_status** | enum | `pending` / `paid` / `partially_refunded` / `refunded` / `failed` / `cancelled` |
| **fulfillment_status** | enum | `unfulfilled` / `partial` / `fulfilled` — `fulfillments`'tan türetilir, önbelleklenir |
| items_total | numeric(12,2) | satır toplamları |
| discount_total | numeric(12,2) default 0 | **Faz 1'de hep 0** — §8.2 |
| shipping_total | numeric(12,2) | |
| **tax_total** | numeric(12,2) | içeriden ayrıştırılan KDV — §8 |
| grand_total | numeric(12,2) | |
| shipping_* / billing_* | varchar | adres **kopyaları** |
| **billing_tax_number** | varchar(11) null | kurumsal fatura — §8.3 |
| **billing_tax_office** | varchar(100) null | kurumsal fatura — §8.3 |
| **terms_accepted_at** | timestamptz | mesafeli satış sözleşmesi onay anı |
| **terms_version** | varchar(20) | onaylanan sözleşme sürümü |
| placed_at | timestamptz | |

> ⚠️ **`terms_*` alanları sonradan eklenemez.** Marka sözleşme metnini değiştirdiğinde eski
> siparişler eski sürüme bağlı kalmalı — hangi metnin hangi siparişte onaylandığı ancak
> **o an** yakalanabilir. Sipariş bir fotoğraftır; bu da fotoğrafın parçası.
> `settings.legal.distance_sales_text` her değiştiğinde sürüm numarası artar (§4).

> **Neden tek `status` değil iki ayrı alan:** "ödenmiş ama gönderilmemiş" ile "gönderilmiş
> ama iade edilmiş" aynı eksende ifade edilemez. Tek alana sıkıştırılırsa `paid_shipped`,
> `paid_partially_shipped_partially_refunded` gibi kombinasyon patlaması başlar. İki
> bağımsız eksen, iki bağımsız alan.

### `fulfillments`
| Alan | Tip | Not |
|------|-----|-----|
| order_id | FK | |
| status | enum | `pending` / `shipped` / `delivered` / `cancelled` |
| carrier, tracking_number | varchar null | |
| shipped_at, delivered_at | timestamptz null | |

### `fulfillment_items`
| Alan | Tip |
|------|-----|
| fulfillment_id, order_item_id | FK |
| quantity | integer |

> ⚠️ Bir `order_item`'ın toplam sevk edilen adedi, sipariş adedini **geçemez.** Kısmi
> sevkiyatın tek doğrulama kuralı budur ve tek bir serviste uygulanır.

### `order_items` — donmuş satırlar
| Alan | Tip | Not |
|------|-----|-----|
| order_id | FK | |
| variant_id | FK null | referans; varyant silinse bile satır yaşar |
| **product_title** | varchar | KOPYA |
| **variant_options** | jsonb | KOPYA — "Kırmızı / M" |
| **sku** | varchar | KOPYA |
| **unit_price** | numeric(12,2) | KOPYA — satın alma anındaki fiyat (KDV dâhil) |
| quantity | integer | |
| discount_amount | numeric(12,2) default 0 | Faz 1'de 0 |
| line_total | numeric(12,2) | (unit_price × quantity) − discount_amount |
| **tax_rate** | numeric(5,2) | KOPYA — o anki KDV oranı. §8 |
| **tax_amount** | numeric(12,2) | `line_total`'ın içinden ayrıştırılan vergi |

> ⚠️ Kalın alanların hepsi **kopya.** Ürüne join'lenip fiyat oradan okunursa, marka yarın
> fiyatı değiştirdiğinde geçmiş siparişlerin tutarı da değişir. **Sipariş bir fotoğraftır.**

### 7.1 Kargo — mağaza ayarı

Tek satıcı olduğu için TıkRota'daki satıcı bazlı kargo hesabı düşüyor. Kargo `settings`
üzerinden, sipariş seviyesinde tek kalem:

```
items_total >= settings.shipping.free_threshold  →  shipping_total = 0
aksi hâlde                                       →  shipping_total = settings.shipping.flat_fee
```

Ağırlık/desi bazlı hesap, bölgesel tarife ve kargo firması entegrasyonu **Faz 5.**

---

## 8. Vergi ve tutar hesabı

`pre-setup.md` §3/0'daki şartın karşılığı: **e-fatura entegrasyonu ertelendi, vergi
alanları Faz 1'de açılıyor.**

### 8.1 Fiyat KDV dâhildir

`product_variants.price` tüketiciye gösterilen, **vergi dâhil** tutardır. Vergi bu tutarın
içinden ayrıştırılır:

```
tax_amount = line_total − ( line_total / (1 + tax_rate/100) )
```

**Örnek:** 120,00 ₺ satır, %20 KDV → net 100,00 ₺, vergi **20,00 ₺**.

> **Neden dâhil:** Türkiye'de tüketiciye vergi hariç fiyat göstermek hem alışılmadık hem
> mevzuata aykırıdır. Şema vergi hariç kurulursa vitrinin her yerinde vergi eklenerek
> gösterim yapılır ve yuvarlama farkları sepet ile ödeme arasında tutarsızlık üretir.

### 8.2 Toplam formülü — tek yerde

```
order_items.line_total  = (unit_price × quantity) − discount_amount
order_items.tax_amount  = line_total − (line_total / (1 + tax_rate/100))

orders.items_total      = Σ line_total
orders.tax_total        = Σ tax_amount  + kargo bedelinin vergisi
orders.grand_total      = items_total − discount_total + shipping_total
```

> ⚠️ **`tax_total` `grand_total`'a EKLENMEZ.** Fiyatlar zaten vergi dâhil olduğu için
> `tax_total` bilgi amaçlıdır — faturada gösterilir, tahsil edilen tutarı değiştirmez.
> Bu, vergi dâhil modelde en sık yapılan hatadır ve sonucu her siparişte müşteriden
> fazladan KDV tahsil etmektir.

> ⚠️ **Vergi indirimden SONRA hesaplanır.** `tax_amount`, `unit_price × quantity`'den
> değil `line_total`'dan türetilir. Faz 3'te kupon geldiğinde bu sıra korunmazsa iade
> tutarları faturayla tutmaz.

**Şart:** bu hesap **tek bir yerde** yapılacak (`Order` servisi içinde bir toplam
hesaplayıcı). `discount_total` Faz 1'de hep 0'dır ama kolon ve formül baştan durur —
sonradan eklemenin maliyeti hesabın *formülünü* değiştirmektir, ki o zaman her çağrı yeri
tek tek güncellenmek zorunda kalır.

### 8.3 Kurumsal fatura

`orders.billing_tax_number` + `billing_tax_office` dolu ise fatura kuruma kesilecek
demektir. Faz 1'de yalnızca **toplanır ve biçimsel doğrulanır** (VKN 10 hane / TCKN 11
hane); e-fatura servisine gönderim Faz 5.

---

## 9. Stok ve eşzamanlılık

### `stock_reservations`
| Alan | Tip | Not |
|------|-----|-----|
| variant_id | FK | |
| order_id | FK null | ödeme başlarken bağlanır |
| quantity | integer | |
| expires_at | timestamptz | +15 dakika |
| status | enum | `held` / `committed` / `released` |

**Akış:**

```
1. Checkout başlar
2. BEGIN TRANSACTION
3. SELECT stock FROM product_variants WHERE id = ? FOR UPDATE   ← satır kilidi
4. stock >= istenen ? rezervasyon oluştur : hata
5. COMMIT
6. Ödeme al  (dış servis — transaction DIŞINDA)
7. Başarılı → rezervasyon committed, stock düşülür
   Başarısız → rezervasyon released
8. Süresi dolan rezervasyonlar zamanlanmış işle serbest bırakılır
```

> ⚠️ Ödeme çağrısı asla transaction içinde yapılmaz — dış servis yavaşlarsa veritabanı
> satırları dakikalarca kilitli kalır.

> ⚠️ **Kiracılık uyarısı:** 8. adımdaki zamanlanmış iş **her kiracı için** çalışmak
> zorunda (pre-setup M-2.4). Tek kiracıda çalışırsa diğer markaların stoğu kilitli kalır.

Çoklu depo kapsam dışı — stok varyantta tek sayıdır.

---

## 10. Ödeme ve iade

### `payments`
| Alan | Tip | Not |
|------|-----|-----|
| order_id | FK | |
| provider | varchar | `fake` / `iyzico` / `paytr` |
| provider_ref | varchar | sağlayıcı işlem no |
| amount | numeric(12,2) | |
| status | enum | `pending` / `authorized` / `captured` / `failed` |
| raw_response | jsonb | denetim izi — **maskelenmiş** |

> Sağlayıcı anahtarları burada değil `settings`'te, şifreli (§4). Her markanın kendi
> ödeme hesabı vardır.

> ⚠️ İki kural pazarlık dışı: **webhook imzası doğrulanmadan sipariş ödendi sayılmaz** ve
> **tutar sunucuda `orders.grand_total`'dan yeniden üretilir** — istemciden gelen tutara
> asla güvenilmez. Kart verisi hiçbir zaman sisteme girmez.

### `refunds` — Faz 3
| Alan | Tip | Not |
|------|-----|-----|
| payment_id | FK | |
| order_item_id | FK null | doluysa satır bazlı kısmi iade |
| amount | numeric(12,2) | |
| tax_amount | numeric(12,2) | iade edilen vergi — faturaya girer (§8) |
| reason | varchar | |
| status | enum | `requested` / `approved` / `rejected` / `completed` |

> Komisyon, hakediş, `payouts` **yok.** Tek satıcı olduğu için para doğrudan markanın
> kendi hesabına gider — TıkRota'nın en karmaşık üç tablosu burada tamamen düşüyor.

---

## 11. Olay kaydı

### `events`
| Alan | Tip | Not |
|------|-----|-----|
| customer_id | FK null | |
| anon_id | uuid null | giriş yapmamış ziyaretçi |
| type | varchar | `product_viewed`, `search_performed`, `cart_item_added`, `order_placed` |
| payload | jsonb | olaya özel alanlar |
| occurred_at | timestamptz | |

Kuyruk üzerinden yazılır ki istek yavaşlamasın.

> ⚠️ **Kiracılık uyarısı:** olay yazan iş, kiracı bağlamını taşımak zorunda
> (pre-setup M-2.4/kuyruk). Taşımazsa A markasının olayı B markasının şemasına yazılır.

Tüketicisi TıkRota'daki gibi bir keşif akışı **değil** — tek markalı mağazada keşfedilecek
satıcı yok. Burada besleyeceği şeyler: ürün önerisi, terk edilmiş sepet hatırlatması ve
markanın kendi satış raporu.

---

## 12. Faz dağılımı

| Faz | Tablolar |
|-----|----------|
| **1** | *(merkez)* tenants, domains · *(kiracı)* customers, users, roles, role_user, role_permissions, settings, addresses, categories, products, product_variants, product_images, carts, cart_items, orders, fulfillments, fulfillment_items, order_items, stock_reservations, payments, events |
| **2** | collections, collection_product · tema ayarları (`settings.theme`) |
| **3** | coupons, coupon_redemptions, refunds, reviews, arama index'leri |
| **4** | plans, subscriptions · kontrol düzlemi |
| **5** | kargo ve e-fatura entegrasyon tabloları |

---

## 13. Karar bekleyen noktalar

1. **Rezervasyon süresi** kaç dakika? (Öneri: 15)
2. **Sipariş numarası biçimi** — artan sayaç mı, tarih önekli mi? Kiracı içinde benzersiz
   olması yeterli (§1).
3. **Varsayılan KDV oranı** — ürün bazlı zorunlu mu, yoksa `settings.tax.default_rate`
   devralınabilir mi?
4. **Terk edilmiş sepet** ne kadar sonra `abandoned` sayılır?
5. **Kategori mi koleksiyon mu birincil?** Faz 2'de netleşecek; vitrin gezinme yapısını
   belirliyor.

Kalanların hiçbiri şemayı değiştirmiyor, uygulama sırasında kararlaştırılabilir.
**Şema taslağı bu hâliyle tamam.**
