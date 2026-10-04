ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-apache

# PDO MySQL (+ mbstring is already bundled in the official image)
RUN docker-php-ext-install pdo pdo_mysql

# Apache: mod_rewrite + allow .htaccess overrides
RUN a2enmod rewrite headers \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy app files
COPY . /var/www/html/

# Document root -> public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot ${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/html>|<Directory ${APACHE_DOCUMENT_ROOT}>|g' /etc/apache2/apache2.conf

# Writable runtime folder (logs / cache / rate-limit files)
RUN mkdir -p /var/www/html/runtime \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 /var/www/html/runtime

# Render injects $PORT (default 10000). Make Apache listen on it.
RUN printf '#!/bin/sh\nset -e\nPORT="${PORT:-80}"\nsed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf\nsed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
 && chmod +x /usr/local/bin/start.sh

EXPOSE 80
CMD ["/usr/local/bin/start.sh"]
