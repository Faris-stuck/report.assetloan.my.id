FROM php:8.3-apache

# Note: Node.js removed - npm builds happen during CI/CD phase, not in production container

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libmagickwand-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_sqlite gd zip intl mbstring \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis \
    && printf "expose_php = Off\nupload_max_filesize = 5M\npost_max_size = 16M\n" > /usr/local/etc/php/conf.d/laporin-security.ini \
    && printf "ServerName report.assetloan.my.id\nServerTokens Prod\nServerSignature Off\nTraceEnable Off\n" > /etc/apache2/conf-available/laporin-security.conf \
    && sed -i "s/^Listen 80$/Listen 8080/" /etc/apache2/ports.conf \
    && a2enmod rewrite headers \
    && a2enconf laporin-security \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start.sh /usr/local/bin/laporin-start
RUN chmod +x /usr/local/bin/laporin-start \
    && for path in app bootstrap config database lang public resources routes; do if [ -d "/var/www/html/$path" ]; then find "/var/www/html/$path" -type d -exec chmod 755 {} +; find "/var/www/html/$path" -type f -exec chmod 644 {} +; fi; done \
    && touch /var/www/html/.env \
    && chmod 640 /var/www/html/.env \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 700 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/html/.env

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8080/up || exit 1

EXPOSE 8080
CMD ["laporin-start"]
