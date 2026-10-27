# =============================================================================
#  Arcadia — one image, two targets
#
#    dev   the local stack (docker-compose.yml): code mounted, edits are live
#    prod  PHP-FPM with the code and both bundles baked in (compose.prod.yml:
#          arc-app, arc-queue)
#    web   nginx with `public/` baked in, handing PHP to arc-app
#    ssr   Node running the Inertia SSR bundle (arc-ssr)
#
#  Both start from `base`, so local and production run the same PHP with the
#  same extensions and limits.
# =============================================================================

# ---- Node, borrowed into every target: Wayfinder's Vite plugin runs
#      `php artisan`, so assets are built where PHP is, and the SSR server needs
#      Node at runtime.
FROM node:22-bookworm-slim AS node

# ---- base: PHP as production runs it ----------------------------------------
FROM php:8.5.12-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libicu-dev libzip-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# GD is configured before it is installed, or JPEG and WebP support is silently
# absent. WebP is for reading uploads: guide covers arrive as WebP and are cut
# into JPEG crops. No `opcache`: PHP 8.5 builds it into the core, and asking to
# install it fails the build.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql intl mbstring exif pcntl bcmath gd zip

# Production's upload limits, so an image too large there is too large here.
RUN printf 'upload_max_filesize = 12M\npost_max_size = 25M\nmemory_limit = 256M\n' \
        > /usr/local/etc/php/conf.d/99-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /var/www

# ---- dev: nothing baked in; the compose file mounts the code ------------------
FROM base AS dev

# ---- assets: the client bundle and the SSR bundle -------------------------------
FROM base AS assets

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && npm run build:ssr \
    && npm prune --omit=dev

# The SSR bundle imports `vue`, `@inertiajs/vue3` and the rest from `node_modules`
# instead of inlining them, so `ssr` carries the runtime dependencies, pruned here.

# ---- prod: the code, its vendor and the built assets ----------------------------
FROM base AS prod

RUN printf 'opcache.enable=1\nopcache.validate_timestamps=0\nopcache.memory_consumption=128\n' \
        > /usr/local/etc/php/conf.d/98-opcache.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
COPY --from=assets /var/www/public/build ./public/build
# Inertia checks the SSR bundle exists before it calls arc-ssr, so it is here too.
COPY --from=assets /var/www/bootstrap/ssr ./bootstrap/ssr

# `public/storage` is what `storage:link` would make, relative so it resolves in
# `web` too, where the same volume is mounted at the same path.
RUN composer dump-autoload --optimize --no-dev \
    && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-pool.conf
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

USER www-data

# ---- web: nginx in front of arc-app, serving the built `public/` -------------
FROM nginx:stable-alpine AS web

COPY docker/prod/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/public /var/www/public

# ---- ssr: the Inertia render server ------------------------------------------
#  `package.json` carries `"type": "module"`, which ssr.js needs.
FROM node:22-bookworm-slim AS ssr

WORKDIR /var/www

COPY --from=assets /var/www/package.json ./package.json
COPY --from=assets /var/www/node_modules ./node_modules
COPY --from=assets /var/www/bootstrap/ssr ./bootstrap/ssr

USER node
EXPOSE 13714
CMD ["node", "bootstrap/ssr/ssr.js"]
