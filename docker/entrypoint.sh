#!/bin/sh
set -e

# Asegurar existencia de directorios críticos de almacenamiento y cache
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Asegurar permisos de lectura y escritura para el servidor y worker
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

exec "$@"
