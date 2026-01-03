FROM php:8.2-apache

# 1. Install System Dependencies (GD, Zip, etc.)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql

# 2. NUCLEAR OPTION: Fix Apache MPM Conflict
# Remove ALL MPMs first, then enable ONLY mpm_prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

# 3. Configure Port Handling (Build Time)
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/' /etc/apache2/sites-available/000-default.conf

# 4. Copy Application Code
COPY . /var/www/html/

# 5. Install PHP Dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 6. Set Permissions
RUN chown -R www-data:www-data /var/www/html

# 7. Setup Entrypoint Script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 8. Start
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
