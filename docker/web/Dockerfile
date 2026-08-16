FROM php:8.5.9-cli-alpine AS php-base

RUN apk add --no-cache cups-client icu-libs libjpeg-turbo libpng sqlite-libs \
    && apk add --no-cache --virtual .build-dependencies icu-dev libjpeg-turbo-dev libpng-dev sqlite-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install gd intl pdo_sqlite \
    && apk del .build-dependencies

FROM php-base AS dependencies

COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

FROM php-base AS runtime

ARG EASY_PRINT_CREATED=1970-01-01T00:00:00Z
ARG EASY_PRINT_REVISION=unknown
ARG EASY_PRINT_VERSION=dev

LABEL org.opencontainers.image.created="${EASY_PRINT_CREATED}" \
    org.opencontainers.image.description="Printer-agnostic, self-hosted web interface for CUPS" \
    org.opencontainers.image.documentation="https://github.com/Thiago-Caffaro/Easy_Print/wiki" \
    org.opencontainers.image.licenses="MIT" \
    org.opencontainers.image.revision="${EASY_PRINT_REVISION}" \
    org.opencontainers.image.source="https://github.com/Thiago-Caffaro/Easy_Print" \
    org.opencontainers.image.title="Easy Print" \
    org.opencontainers.image.version="${EASY_PRINT_VERSION}"

RUN addgroup -S -g 10001 easyprint \
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
STOPSIGNAL SIGTERM

HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r '$base = rtrim((string) getenv("APP_BASE_PATH"), "/"); exit(false === @file_get_contents("http://127.0.0.1:8080" . $base . "/health/live") ? 1 : 0);'

ENTRYPOINT ["/usr/local/bin/easy-print-entrypoint"]
