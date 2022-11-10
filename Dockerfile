FROM wordpress:5.9.2-apache as base

COPY ./.docker/php/z-custom.ini $PHP_INI_DIR/conf.d/

RUN rm -rf /var/www/html/*

FROM base as develop

RUN pecl -q install xdebug
RUN docker-php-ext-enable xdebug
COPY ./.docker/php/xdebug.ini /usr/local/etc/php/conf.d/

RUN sed -i 's!/var/www/html!/var/www/web!g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www
