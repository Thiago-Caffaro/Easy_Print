FROM composer:2.10.2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --classmap-authoritative \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

FROM php:8.5.9-cli-alpine AS runtime

RUN apk add --no-cache cups-client icu-libs sqlite-libs \
    && apk add --no-cache --virtual .build-dependencies icu-dev sqlite-dev \
    && docker-php-ext-install intl pdo_sqlite \
    && apk del .build-dependencies \
    && addgroup -S -g 10001 easyprint \
    && adduser -S -D -H -u 10001 -G easyprint easyprint

WORKDIR /app

COPY --from=dependencies /app/vendor ./vendor
COPY app ./app
COPY bin ./bin
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY --chmod=0755 docker/web/entrypoint.sh /usr/local/bin/easy-print-entrypoint

RUN mkdir -p /var/lib/easy-print/tmp \
    && chown -R easyprint:easyprint /var/lib/easy-print

ENV APP_ENV=production \
    APP_DEBUG=false \
    DATABASE_PATH=/var/lib/easy-print/easy-print.sqlite \
    TEMPORARY_PATH=/var/lib/easy-print/tmp

USER easyprint
EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r "exit(false === @file_get_contents('http://127.0.0.1:8080/') ? 1 : 0);"

ENTRYPOINT ["/usr/local/bin/easy-print-entrypoint"]
