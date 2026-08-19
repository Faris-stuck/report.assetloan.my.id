# =============================================
# Nginx Dockerfile
# assetloan.my.id — Self-contained (NO volume mount)
# =============================================

FROM nginx:alpine

# Set timezone
RUN apk add --no-cache tzdata openssl \
    && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime \
    && echo "Asia/Jakarta" > /etc/timezone \
    && rm -rf /var/cache/apk/*

# Copy SOURCE CODE into image
COPY api/           /var/www/html/api/
COPY admin/         /var/www/html/admin/
COPY assets/        /var/www/html/assets/
COPY config/        /var/www/html/config/
COPY hermes/        /var/www/html/hermes/
COPY manager/       /var/www/html/manager/
COPY user/          /var/www/html/user/
COPY pic-barang/    /var/www/html/pic-barang/
COPY phpmailer/     /var/www/html/phpmailer/
COPY tools/         /var/www/html/tools/
COPY index.html     /var/www/html/index.html
COPY register.html  /var/www/html/register.html
COPY forgot-password.html /var/www/html/forgot-password.html
COPY peminjaman.sql /var/www/html/peminjaman.sql

# Copy nginx config
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Generate self-signed SSL certificate (untuk Docker)
# Production: ganti dengan certbot / Let's Encrypt
RUN mkdir -p /etc/nginx/ssl \
    && openssl req -x509 -nodes -days 365 \
        -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/server.key \
        -out /etc/nginx/ssl/server.crt \
        -subj "/C=ID/ST=Jakarta/L=Jakarta/O=Komatsu/CN=assetloan.my.id" \
    && chmod 600 /etc/nginx/ssl/server.key

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
