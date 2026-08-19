# =============================================
# PHP-FPM Dockerfile
# assetloan.my.id — Self-contained (NO volume mount)
# Password samakan dengan source code: EMPTY
# =============================================

FROM php:8.3-fpm-alpine

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli opcache

# Install timezone
RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime \
    && echo "Asia/Jakarta" > /etc/timezone \
    && rm -rf /var/cache/apk/*

# Working directory
WORKDIR /var/www/html

# Copy SOURCE CODE into image (tidak mount volume)
COPY api/           ./api/
COPY admin/         ./admin/
COPY assets/        ./assets/
COPY config/        ./config/
COPY hermes/        ./hermes/
COPY manager/       ./manager/
COPY user/          ./user/
COPY pic-barang/    ./pic-barang/
COPY phpmailer/     ./phpmailer/
COPY tools/         ./tools/
COPY index.html     ./index.html
COPY register.html  ./register.html
COPY forgot-password.html ./forgot-password.html
COPY peminjaman.sql ./peminjaman.sql

# Set ownership
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Environment variables (password samakan source code: EMPTY)
ENV DB_HOST=mysql \
    DB_PORT=3306 \
    DB_NAME=peminjaman \
    DB_USER=root \
    DB_PASSWORD=***XPOSE 9000

CMD ["php-fpm", "--nodaemonize"]
