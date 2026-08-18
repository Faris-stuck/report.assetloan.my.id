FROM composer:2 AS composer

FROM debian:bookworm-slim AS ai-build

RUN apt-get update \
    && apt-get install -y --no-install-recommends build-essential cmake git curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /tmp
RUN git clone --depth 1 https://github.com/ggml-org/llama.cpp.git

RUN cmake -S /tmp/llama.cpp -B /tmp/llama.cpp/build \
        -DCMAKE_BUILD_TYPE=Release \
        -DBUILD_SHARED_LIBS=ON \
        -DGGML_NATIVE=OFF \
        -DLLAMA_BUILD_TESTS=OFF \
        -DLLAMA_BUILD_EXAMPLES=OFF \
        -DLLAMA_BUILD_SERVER=OFF \
        -DLLAMA_CURL=OFF \
    && cmake --build /tmp/llama.cpp/build -j2 --target llama

RUN mkdir -p /opt/laporin-ai/lib /opt/laporin-ai/models \
    && cp /tmp/llama.cpp/build/bin/libllama.so* /opt/laporin-ai/lib/ \
    && cp /tmp/llama.cpp/build/bin/libggml*.so* /opt/laporin-ai/lib/

COPY docker/ai/laporin_ai.cpp /tmp/laporin_ai.cpp

RUN g++ -std=c++17 -fPIC -shared /tmp/laporin_ai.cpp \
        -I/tmp/llama.cpp/include \
        -L/opt/laporin-ai/lib \
        -Wl,-rpath,/opt/laporin-ai/lib \
        -llama \
        -o /opt/laporin-ai/lib/liblaporin_ai.so

RUN curl -fL --retry 5 --retry-delay 2 \
        -o /opt/laporin-ai/models/qwen2.5-0.5b-instruct-q4_k_m.gguf \
        'https://huggingface.co/Qwen/Qwen2.5-0.5B-Instruct-GGUF/resolve/main/qwen2.5-0.5b-instruct-q4_k_m.gguf?download=true' \
    && chmod 0555 /opt/laporin-ai/lib/*.so* \
    && chmod 0444 /opt/laporin-ai/models/*.gguf

FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl git unzip libffi-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libmagickwand-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_sqlite gd zip intl mbstring ffi \
    && pecl install imagick \
    && docker-php-ext-enable imagick ffi \
    && printf "expose_php = Off\nupload_max_filesize = 5M\npost_max_size = 16M\nffi.enable = preload\nffi.preload = /opt/laporin-ai/laporin_ai.h\nopcache.preload = /opt/laporin-ai/preload.php\nopcache.preload_user = www-data\n" > /usr/local/etc/php/conf.d/laporin-security.ini \
    && printf "ServerName report.assetloan.my.id\nServerTokens Prod\nServerSignature Off\nTraceEnable Off\n" > /etc/apache2/conf-available/laporin-security.conf \
    && sed -i "s/^Listen 80$/Listen 8080/" /etc/apache2/ports.conf \
    && a2enmod rewrite headers \
    && a2enconf laporin-security \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=ai-build /opt/laporin-ai /opt/laporin-ai

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
RUN chmod +x /usr/local/bin/laporin-start /usr/local/bin/laporin-worker-start \
    && for path in app bootstrap config database lang public resources routes vendor; do if [ -d "/var/www/html/$path" ]; then find "/var/www/html/$path" -type d -exec chmod 755 {} +; find "/var/www/html/$path" -type f -exec chmod 644 {} +; fi; done \
    && touch /var/www/html/.env \
    && chmod 640 /var/www/html/.env \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 700 storage bootstrap/cache \
    && chmod 0755 /opt/laporin-ai \
    && chmod 0555 /opt/laporin-ai/lib/*.so* \
    && chmod 0444 /opt/laporin-ai/models/*.gguf /opt/laporin-ai/*.php /opt/laporin-ai/*.h \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/html/.env

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
  CMD curl -fsS http://localhost:8080/up >/dev/null || exit 1

EXPOSE 8080
CMD ["laporin-start"]