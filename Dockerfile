ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql dom mbstring xml xmlwriter \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php-development.ini /usr/local/etc/php/conf.d/zz-development.ini

WORKDIR /var/www/html
