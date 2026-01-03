FROM php:8.2-apache

# 1. Install System Dependencies & PHP Extensions
# (GD and Zip are required for PHPWord/Excel)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql

# 2. Fix Apache MPM Conflict (The Root Cause of "Application failed to start")
# We forcefully disable mpm_event and enable mpm_prefork
RUN a2dismod mpm_event || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

# 3. Configure Port Handling
# Railway sets a random $PORT. Apache defaults to 80.
# We modify ports.conf to listen on all interfaces at runtime port.
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/' /etc/apache2/sites-available/000-default.conf

# 4. Copy Application Code
COPY . /var/www/html/

# 5. Install PHP Dependencies
# We act as "root" to allow plugins to run
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 6. Set Permissions
RUN chown -R www-data:www-data /var/www/html

# 7. Start Apache
# We use a shell command to substitute $PORT before starting
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf && \
    sed -i "s/:80/:${PORT:-80}/g" /etc/apache2/sites-enabled/000-default.conf && \
    apache2-foreground
