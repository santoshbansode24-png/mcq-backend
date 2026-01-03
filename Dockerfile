FROM php:8.1-apache

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Fix for "More than one MPM loaded" error
# Fix for "More than one MPM loaded" error
# Manually remove conflicting MPMs to ensure only prefork is active
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
RUN rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
RUN a2enmod mpm_prefork

# Copy source code
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/

# Configure Apache Document Root to be in the root folder (default)
# (If your api is in /api subfolder, this is fine)
