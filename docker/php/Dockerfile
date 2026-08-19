FROM php:8.4-fpm

# Uzantı kurulumunu tek komuta indiren hazır betik.
# Olmasaydı her uzantı için ayrıca sistem kütüphanesi kurmak gerekirdi.
COPY --from=mlocati/php-extension-installer:latest \
     /usr/bin/install-php-extensions /usr/local/bin/

# pdo_pgsql  PostgreSQL bağlantısı           (M-2)
# redis      cache + kuyruk sürücüsü
# intl       Türkçe tarih ve sayı biçimlendirme
# bcmath     hassas ondalık matematik — para hesabı float ile yapılmaz
# gd         ürün görseli boyutlandırma
# zip        composer paketleri açar
RUN install-php-extensions \
        pdo_pgsql \
        redis \
        intl \
        bcmath \
        gd \
        zip

# Composer'ı hazır imajdan kopyala — ayrıca kurmaya gerek yok.
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# ─────────────────────────────────────────────────────────────────────
# NODE — panel arayüzünün derleyicisi. (4C-K1)
#
# ⚠️ AYRI SERVİS DEĞİL, AYNI İMAJ. Proje kuralı "yerel makinede PHP yok,
# her şey Makefile'da"; Node'u dışarıda bırakmak geliştiricinin makinesine
# bağımlılık eklerdi ve "bende çalışıyor" sınıfı hataların kapısını açardı.
#
# ⚠️ İmajı büyütüyor (~60 MB) ve bu BİLİNÇLİ bir takas: tek konteyner,
# tek komut. Üretimde derleme yapılmayacak (varlıklar önceden derlenip
# gönderilecek), yani bu maliyet yalnızca geliştirme ve CI'da.
COPY --from=node:22-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /var/www/html

# ─────────────────────────────────────────────────────────────────────
# ADLANDIRILMIŞ BİRİMLERİN İZİNLERİ (4.5C · 4.5D)
#
# `node_modules` ve derlenmiş Blade önbelleği bağlı klasörün DIŞINDA,
# adlandırılmış Docker birimlerinde tutuluyor — gerekçesi
# docker-compose.yml'de (macOS bind mount'ta `errno=35` kilidi).
#
# ⚠️ Docker boş bir adlandırılmış birimi İLK BAĞLANDIĞINDA imajdaki
# karşılığından dolduruyor — SAHİPLİK VE İZİNLER DÂHİL. Klasörler imajda
# yoksa birim `root:root 755` doğuyor ve Blade derleyici geçici dosyasını
# oraya yazamıyor.
#
# ⚠️ ÖLÇÜLDÜ (4.5D): belirti yanıltıcıydı — hata "izin yok" değil
# `tempnam(): file created in the system's temporary directory` idi.
# PHP sessizce sistem geçici klasörüne düşüyor, sonra `rename()` dosya
# sistemleri arasında başarısız oluyor ve müşteri kayıt sayfası 500
# veriyordu.
RUN mkdir -p /var/www/html/storage/framework/views /var/www/html/node_modules \
    && chmod 777 /var/www/html/storage/framework/views /var/www/html/node_modules
