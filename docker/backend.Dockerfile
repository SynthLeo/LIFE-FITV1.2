FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers rewrite
# El código se monta como volumen en /var/www/html/backend (ver docker-compose.yml)
