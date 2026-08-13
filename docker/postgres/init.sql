-- Veritabanı ilk kez oluşturulurken bir kez çalışır.
-- (Volume zaten doluysa TEKRAR ÇALIŞMAZ — yeni satır eklersen
--  `docker compose down -v` gerekir.)

-- citext = case-insensitive text. "Ali@site.com" ile "ali@SITE.com"
-- aynı değer sayılır. E-posta benzersizliği bunun üzerine kurulu
-- (docs/domain-model.md §0).
CREATE EXTENSION IF NOT EXISTS citext;

-- pg_trgm = üçlü harf benzerliği. Aramada yazım hatası toleransı
-- ("tsiort" → "tisort") bunun üzerine kurulu (2C).
--
-- ⚠️ Eklenti `public`'te duruyor ve MARKA ŞEMASINDAN GÖRÜNMÜYOR —
-- citext'in ve ltree'nin başına gelenin aynısı. ÖLÇÜLDÜ:
--     similarity(...)          → "No function matches the given name"
--     public.similarity(...)   → 0.27  ✓
-- Bu yüzden tüm çağrılar ve indeks sınıfı `public.` ile nitelikli
-- yazılıyor (`public.gin_trgm_ops`).
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Testler ayrı veritabanında koşar ki geliştirme verisi silinmesin.
-- PostgreSQL'de test ediyoruz çünkü şema/citext/jsonb/FOR UPDATE
-- SQLite'ta yok (phpunit.xml).
CREATE DATABASE tikmarka_test OWNER tikmarka;

\connect tikmarka_test
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
