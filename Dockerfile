FROM php:8.2-apache

# System deps + PHP extensions for SIGDoc (PDO MySQL, mbstring, zip for Composer)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev ca-certificates \
    && update-ca-certificates \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app (vendor may already exist; composer install still ensures lock is applied)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p uploads logs backups \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R ug+rwX uploads logs backups

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/sigdoc-entrypoint.sh
RUN chmod +x /usr/local/bin/sigdoc-entrypoint.sh

ENV APACHE_DOCUMENT_ROOT=/var/www/html

EXPOSE 80

ENTRYPOINT ["sigdoc-entrypoint.sh"]
CMD ["apache2-foreground"]
