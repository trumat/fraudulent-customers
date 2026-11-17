FROM composer:2 AS composer

FROM php:8.5-cli-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache git unzip sqlite \
    && addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

USER app

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
