FROM composer:2 AS composer

FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl git unzip libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libmagickwand-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_sqlite gd zip intl mbstring opcache \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && printf "expose_php = Off\nupload_max_filesize = 5M\npost_max_size = 16M\n" > /usr/local/etc/php/conf.d/laporin-security.ini \
    && printf "opcache.enable = 1\nopcache.enable_cli = 0\nopcache.memory_consumption = 128\nopcache.interned_strings_buffer = 16\nopcache.max_accelerated_files = 20000\nopcache.validate_timestamps = 0\nopcache.save_comments = 1\nmemory_limit = 192M\nrealpath_cache_size = 4096K\nrealpath_cache_ttl = 600\n" > /usr/local/etc/php/conf.d/laporin-performance.ini \
    && printf "ServerName report.assetloan.my.id\nServerTokens Prod\nServerSignature Off\nTraceEnable Off\n" > /etc/apache2/conf-available/laporin-security.conf \
    && sed -i "s/^Listen 80$/Listen 8080/" /etc/apache2/ports.conf \
    && a2enmod rewrite headers \
    && a2enconf laporin-security \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . /var/www/html
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start.sh /usr/local/bin/laporin-start
COPY docker/start-worker.sh /usr/local/bin/laporin-worker-start

# Autoloader HARUS dibangun ulang di sini, bukan hanya di `composer install`
# di atas: pada tahap itu hanya composer.json/composer.lock yang ada di image,
# sehingga classmap --optimize tidak mungkin memuat satu pun kelas App\.
# Selama ini yang menyelamatkan produksi adalah `COPY . /var/www/html` menimpa
# vendor/ hasil build dengan vendor/ milik host -- yang classmap-nya memang
# lengkap karena dibuat saat app/ sudah ada. Akibatnya isi vendor produksi
# ditentukan oleh keadaan direktori di server: sekali ada yang menjalankan
# `composer install` biasa (dengan dev) di host, phpunit/faker/mockery ikut
# ter-bake ke image produksi tanpa ada yang menyadarinya.
# Dengan vendor/ dikecualikan di .dockerignore + dump-autoload di sini, isi
# vendor produksi murni hasil `--no-dev` dan classmap-nya tetap lengkap.
RUN composer dump-autoload --optimize --no-dev --no-scripts
RUN chmod +x /usr/local/bin/laporin-start /usr/local/bin/laporin-worker-start \
    && for path in app bootstrap config database lang public resources routes vendor; do if [ -d "/var/www/html/$path" ]; then find "/var/www/html/$path" -type d -exec chmod 755 {} +; find "/var/www/html/$path" -type f -exec chmod 644 {} +; fi; done \
    && touch /var/www/html/.env \
    && chmod 640 /var/www/html/.env \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 700 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/html/.env

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
  CMD curl -fsS http://localhost:8080/up >/dev/null || exit 1

EXPOSE 8080
CMD ["laporin-start"]
