#!/bin/bash
set -e

# Fix for "More than one MPM loaded" error
# We do this at runtime to ensure it persists regardless of build steps
if [ -d "/etc/apache2/mods-enabled" ]; then
    echo "Checking for MPM conflicts..."
    # Remove all MPMs to ensure a clean slate
    rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
    
    # Enable mpm_prefork (required for mod_php)
    echo "Enabling mpm_prefork..."
    if command -v a2enmod >/dev/null 2>&1; then
        a2enmod mpm_prefork
    else
        # Fallback manual linking
        ln -sf ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/
        ln -sf ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/
    fi
fi

# Dynamically set the Apache port based on Railway's PORT environment variable
# If PORT is not set, default to 80
PORT=${PORT:-80}

echo "Configuring Apache to listen on port $PORT..."

# Replace default port 80 in ports.conf and default site config
# Force binding to 0.0.0.0 to ensure external access
sed -i "s/Listen 80/Listen 0.0.0.0:$PORT/" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/000-default.conf

echo "=== DEBUG: ports.conf ==="
cat /etc/apache2/ports.conf
echo "=== DEBUG: 000-default.conf ==="
cat /etc/apache2/sites-available/000-default.conf
echo "=== DEBUG: apache2ctl -S ==="
apache2ctl -S
echo "=== STARTING APACHE ==="

# Execute the CMD from Dockerfile (usually apache2-foreground)
exec "$@"
