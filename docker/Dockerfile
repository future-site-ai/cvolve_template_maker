# CVolve Template Maker image. Build context: the folder that contains both
# cvolve_template_maker/ and cvolve/ (the shared template kit lives in cvolve/packages/template-kit).
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip git \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY cvolve_template_maker/docker/php.ini /usr/local/etc/php/conf.d/maker.ini
COPY cvolve_template_maker/docker/apache.conf /etc/apache2/sites-available/000-default.conf

ENV APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1
# Keep the same relative layout so the Composer path repository (../cvolve/packages/template-kit) resolves
COPY cvolve/packages/template-kit /srv/cvolve/packages/template-kit
WORKDIR /srv/cvolve_template_maker

COPY cvolve_template_maker/composer.json cvolve_template_maker/composer.lock cvolve_template_maker/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY cvolve_template_maker/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && APP_SECRET=build php bin/console importmap:install \
 && APP_SECRET=build php bin/console asset-map:compile \
 && APP_SECRET=build php bin/console cache:warmup \
 && mkdir -p var && chown -R www-data:www-data var

COPY cvolve_template_maker/docker/entrypoint.sh /usr/local/bin/maker-entrypoint
RUN chmod +x /usr/local/bin/maker-entrypoint
ENTRYPOINT ["maker-entrypoint"]
CMD ["apache2-foreground"]
