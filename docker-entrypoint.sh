#!/bin/bash
set -e

# Crear directorios necesarios
mkdir -p /var/www/html/var/log
mkdir -p /var/www/html/var/cache
mkdir -p /var/www/html/var/tmp
mkdir -p /var/www/html/var/www/facturacion/autorizados
chmod -R 777 /var/www/html/var

# Configurar permisos
chown -R www-data:www-data /var/www/html/var

# Ejecutar el comando original
exec "$@"
