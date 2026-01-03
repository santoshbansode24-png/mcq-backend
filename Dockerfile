FROM php:8.2-apache

# 1. Install System Dependencies & PHP Extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql

# 2. Configure Apache Environment
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN a2enmod rewrite

# 3. Copy Application Code
COPY . /var/www/html/

# 4. Install PHP Dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 5. Set Permissions
RUN chown -R www-data:www-data /var/www/html

# 6. EXTREMELY ROBUST START COMMAND
# This handles MPM fixing AND Port configuration in one atomic command.
# We replace the listening port with the $PORT env var provided by Railway.
CMD echo "Server starting..." && \
    # Fix MPM (Nuclear option)
    rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf && \
    a2enmod mpm_prefork && \
    # Fix Port
    echo "Binding to 0.0.0.0:${PORT}" && \
    sed -i "s/Listen 80/Listen 0.0.0.0:${PORT}/" /etc/apache2/ports.conf && \
    sed -i "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf && \
    # Start
    apache2-foreground
