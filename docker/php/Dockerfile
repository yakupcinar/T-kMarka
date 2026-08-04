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

WORKDIR /var/www/html
