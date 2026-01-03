#!/bin/bash
set -e

# Dynamically set the Apache port based on Railway's PORT environment variable
# If PORT is not set, default to 80
PORT=${PORT:-80}

echo "Configuring Apache to listen on port $PORT..."

# Replace default port 80 in ports.conf and default site config
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/000-default.conf

# Execute the CMD from Dockerfile (usually apache2-foreground)
exec "$@"
