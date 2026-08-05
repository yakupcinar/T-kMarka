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

## Yapı

```
app/Platform/   merkez şema (Tenant)          app/Models/    marka şeması modelleri
app/Tenancy/    kiracılığın TAMAMI            app/Http/      Platform · (Panel · Storefront)
app/Domain/     iş mantığı — kiracıdan habersiz
```

`app/Domain/` içindeki hiçbir dosya `Tenancy` sınıflarını import etmez ve
"hangi kiracıdayım" diye sormaz (M-2.7).

Testler: `tests/Feature/` → `RefreshDatabase` var. `tests/Tenancy/` → **yok**
(transaction, şema oluşturmayı bozuyor); temizlik `tests/Pest.php`'de.

## Çalışma biçimi

- Belgeler ve kod yorumları **Türkçe**, tanımlayıcılar İngilizce.
- Bir madde bitince: `lint` + `analyse` + `test` üçü de yeşil olmadan commit yok.
- Plan canlıdır: gerçek planla çelişirse **plan güncellenir**, gerekçesiyle.
- Commit mesajlarına co-author/imza satırı **eklenmez**.
