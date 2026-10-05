# Imagem de produção do CRUD ITI (PHP 8.3 + Apache), usada no deploy do Render.
# Local:  docker build -t crud-iti . && docker run --env-file .env.docker -p 8080:8080 crud-iti
FROM php:8.3-apache-bookworm

# intl (validadores/formulários do Laminas), pdo_mysql (Doctrine) e opcache.
# mbstring, dom, xml e fileinfo já vêm na imagem oficial.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql opcache \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PORT=8080

WORKDIR /var/www/app

# Dependências numa camada própria: só refaz quando o composer.lock muda.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-plugins --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && composer check-platform-reqs --no-dev \
    && mkdir -p data/cache data/DoctrineORMModule/Proxy \
    && rm -f data/cache/*.php \
    && chown -R www-data:www-data data \
    && chmod 755 bin/iniciar-container.sh

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-crud-iti.ini"
RUN sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf

EXPOSE 8080
ENTRYPOINT ["/var/www/app/bin/iniciar-container.sh"]
