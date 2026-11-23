FROM php:8.4-apache

# Extension PDO MySQL (utilisée par src/Core/Database.php)
RUN docker-php-ext-install pdo_mysql

# DocumentRoot = /public, toutes les routes inconnues passent par index.php,
# et le header Authorization (Bearer) est transmis à PHP
RUN sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
 && printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride None' \
    '    FallbackResource /index.php' \
    '    CGIPassAuth On' \
    '</Directory>' \
    'SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1' \
    > /etc/apache2/conf-enabled/motus.conf

WORKDIR /var/www/html
