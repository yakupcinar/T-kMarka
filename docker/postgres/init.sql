-- Veritabanı ilk kez oluşturulurken bir kez çalışır.
-- (Volume zaten doluysa TEKRAR ÇALIŞMAZ — yeni satır eklersen
--  `docker compose down -v` gerekir.)

-- citext = case-insensitive text. "Ali@site.com" ile "ali@SITE.com"
-- aynı değer sayılır. E-posta benzersizliği bunun üzerine kurulu
-- (docs/domain-model.md §0).
CREATE EXTENSION IF NOT EXISTS citext;

-- Testler ayrı veritabanında koşar ki geliştirme verisi silinmesin.
-- PostgreSQL'de test ediyoruz çünkü şema/citext/jsonb/FOR UPDATE
-- SQLite'ta yok (phpunit.xml).
CREATE DATABASE tikmarka_test OWNER tikmarka;

\connect tikmarka_test
CREATE EXTENSION IF NOT EXISTS citext;
