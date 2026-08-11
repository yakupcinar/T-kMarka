# CLAUDE.md

TıkMarka — çok kiracılı D2C e-ticaret. Laravel 12 / PHP 8.4 / PostgreSQL 17,
marka başına ayrı **şema** (`tenant_<uuid>`), merkez veriler `public` şemasında.

## Önce bunları oku

| Dosya | İçerik |
|---|---|
| `PLAN.md` | Yol haritası + **şu an neredeyiz**. Her madde gerekçesiyle yazılı. |
| `docs/summary.md` | Tek sayfalık özet — hızlı bağlam için buradan başla. |
| `docs/pre-setup.md` | Mimari kararlar (M-1…M-4) ve **neden** öyle olduğu. |
| `docs/domain-model.md` | Veri modeli, tablo tablo. |

Bir karara katılmıyorsan önce `pre-setup.md`'deki gerekçesini oku; çoğu tuzak
orada zaten yazılı.

## Komutlar — hepsi konteyner içinde

Yerel makinede PHP/Composer **yok**.

```bash
docker compose exec -T app composer lint      # Pint (biçim)
docker compose exec -T app composer analyse   # Larastan seviye 8
docker compose exec -T app composer test      # Pest

docker compose exec -T app composer migrate:landlord   # merkez şema
docker compose exec -T app php artisan tenants:migrate # tüm markalar
docker compose exec -T app php artisan tenant:create "Ad" alan-adi.localhost
```

Adresler: `marka-a.localhost` · `marka-b.localhost` · `localhost` (merkez).
Sertifika uyarısı normal (`tls internal`), `curl -k` kullan.

## Sessiz hataya yol açan kurallar

Bunların hepsi **hata vermeden yanlış sonuç** üretir. Projede en az bir kez yaşandı.

- **Migration klasörü.** `database/migrations/` kökü bilerek **boş**.
  Marka tablosu → `--path=database/migrations/tenant`,
  merkez tablosu → `--path=database/migrations/landlord`.
  Köke düşen dosya kazara merkez şemaya gider.
- **`timestampsTz()`** kullan, `timestamps()` değil. Laravel'in varsayılanı
  saat dilimi taşımayan damga üretiyor (`docs/domain-model.md` §0).
- **`citext` marka şemasında çalışmıyor** — eklenti `public`'te, marka
  `search_path`'i görmüyor, sessizce düz metin karşılaştırmasına düşüyor.
  E-posta için: modelde küçültme + `CHECK (email = lower(email))`.
- **`$fillable`** = "neyi **asla** dışarıdan almam" listesi. Yetki/sahiplik
  alanları (`is_owner`, `is_system`, `customer_id`) buraya **girmez**.
- **Kod değiştikten sonra** `docker compose restart worker scheduler` —
  kuyruk işçisi kodu belleğe alıyor, bayat kodla çalışmaya devam eder.
- **Marka verisine dokunan zamanlanmış görev** `tenants:run <komut>` ile
  sarılır; doğrudan yazılan görev merkez bağlamda koşar ve hiçbir şey yapmaz.
- **Kolon varsayılanı modele ULAŞMAZ.** `->default(true)` yalnızca diske
  yazarken uygulanır; `create()`'ten dönen nesnede alan hiç yoktur ve `null`
  okunur. Üç kez ısırdı: `accepts_marketing` (1A.2) · `is_system` (1A.6) ·
  `is_active` (1B.3). Çözüm modelde `protected $attributes = [...]`;
  `refresh()` de işe yarar ama ek sorgu ve her çağrı yerinde hatırlanmalı.
- **Sürümlenmesi gereken şey `settings`'e konmaz.** Ayar "şu an geçerli
  değer"dir, geçmişi yoktur. Yasal metinler bu yüzden
  `legal_document_versions`'ta ve o tablo **salt-ekleme** — `UPDATE`/`DELETE`/
  `TRUNCATE` veritabanı tetiğiyle reddediliyor. Yayınlamak = yeni satır.
- **Yerel `lint` yeşil ≠ CI yeşil. Otorite CI.** Bir kez `lint:check`
  yerelde geçti, CI'da düştü (`class_attributes_separation`); fark 20 koşu
  boyunca fark edilmedi. Sebep kesinleşmedi — muhtemelen Pint'in geçici
  klasördeki önbelleğinde bayat kayıt. Gönderimden sonra durumu gör:
  ```
  curl -s "https://api.github.com/repos/yakupcinar/T-kMarka/commits/main/check-runs" \
    | python3 -c "import sys,json;[print(c['name'],c['conclusion']) for c in json.load(sys.stdin)['check_runs']]"
  ```
  Hata ayrıntısı **anotasyonlarda** (günlükler yönetici yetkisi ister);
  `.github/ci-kontrol.sh` çıktıyı oraya basıyor.
- **Yeni marka geliştirmede HTTPS'e çıkmaz.** `docker/caddy/Caddyfile`'da alan
  adları elle sayılı; `tenant:create` başarılı görünür ama site açılmaz.
  Alan adını ekleyip `docker compose restart caddy`. (Faz 3: on-demand TLS.)

## Yapı

```
app/Platform/   merkez şema (Tenant)          app/Models/    marka şeması modelleri
app/Tenancy/    kiracılık KOMUTLARI           app/Http/      Platform · (Panel · Storefront)
app/Domain/     iş mantığı — kiracıdan habersiz
```

⚠️ Kiracılık **tek klasörde toplanmıyor** — `app/Tenancy/` yalnızca komutları
tutuyor (142 satır). Kiracılığa dokunan yerlerin tamamı:
`config/tenancy.php` (paket ayarı, tohumlayıcı sınıfı) · `routes/tenant.php`
(kapı görevlisi middleware zinciri) · `bootstrap/app.php` (takma adlar,
istisna eşlemeleri) · `tests/Pest.php` (kiracı kurulumu ve temizlik).
Bir kiracılık davranışı ararken bu beşine bak.

`app/Domain/` içindeki hiçbir dosya `Tenancy` sınıflarını import etmez ve
"hangi kiracıdayım" diye sormaz (M-2.7). **Ölçüldü:** `app/Domain/` içinde
`App\Tenancy`, `tenant(`, `tenancy(` geçişi sıfır.

**İş kuralı controller'a yazılmaz.** Kural: bir kontrol, HTTP dışından
(artisan komutu · kuyruk işi · tohumlayıcı) atlanabiliyorsa `app/Domain/`'e
girer. Controller yalnızca çevirir: isteği al, servisi çağır, cevabı biçimle.

Testler: `tests/Feature/` → `RefreshDatabase` var. `tests/Tenancy/` → **yok**
(transaction, şema oluşturmayı bozuyor); temizlik `tests/Pest.php`'de.

## Çalışma biçimi

- Belgeler ve kod yorumları **Türkçe**, tanımlayıcılar İngilizce.
- Bir madde bitince: `lint` + `analyse` + `test` üçü de yeşil olmadan commit yok.
- Plan canlıdır: gerçek planla çelişirse **plan güncellenir**, gerekçesiyle.
- Commit mesajlarına co-author/imza satırı **eklenmez**.
