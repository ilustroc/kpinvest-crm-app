# Entorno dev compatible con la version PHP CLI del hosting IMPULSE.
# La familia de SO/servidor web no reproduce CloudLinux/LiteSpeed exactamente.
FROM composer:2.9.8 AS composer_bin
FROM php:8.3.35-apache

# Instalar bibliotecas requeridas por Laravel, Excel y generación de PDF.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      git unzip zip curl ca-certificates \
      libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
      libicu-dev libonig-dev libzip-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
      bcmath gd intl mbstring mysqli pdo_mysql soap zip pcntl opcache exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Usar como DocumentRoot solo la carpeta publica del proyecto Laravel.
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    Options FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
       > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY --from=composer_bin /usr/bin/composer /usr/local/bin/composer
COPY docker/crm/php-dev.ini /usr/local/etc/php/conf.d/99-kpcrm-dev.ini
WORKDIR /var/www/html
EXPOSE 80
