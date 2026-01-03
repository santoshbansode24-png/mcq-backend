FROM php:8.1-apache

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Fix for "More than one MPM loaded" error
# Fix for "More than one MPM loaded" error
# Nuclear Option: Wipe ALL MPMs first, then enable only prefork
# This handles any pre-installed MPMs regardless of name (event, worker, etc)
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
RUN a2enmod mpm_prefork

# Copy source code
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/

# Configure Apache Document Root to be in the root folder (default)
# (If your api is in /api subfolder, this is fine)
