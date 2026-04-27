FROM php:8.2-apache
WORKDIR /var/www
RUN a2enmod rewrite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get update && apt-get install -y libzip-dev zip && docker-php-ext-install zip