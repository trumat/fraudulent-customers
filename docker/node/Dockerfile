FROM node:24-alpine

ARG UID=1000
ARG GID=1000

ENV npm_config_cache=/tmp/.npm

RUN mkdir -p /var/www/html/node_modules \
    && chown "${UID}:${GID}" /var/www/html/node_modules

WORKDIR /var/www/html

USER ${UID}:${GID}

CMD ["sh", "-c", "[ -f node_modules/.package-lock.json ] || npm ci; exec npm run dev"]
