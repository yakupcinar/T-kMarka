-- Veritabanı ilk kez oluşturulurken bir kez çalışır.
--
-- citext = case-insensitive text. "Ali@site.com" ile "ali@SITE.com"
-- aynı değer sayılır. E-posta alanlarında benzersizlik bunun üzerine
-- kurulu (docs/domain-model.md §0).
CREATE EXTENSION IF NOT EXISTS citext;
