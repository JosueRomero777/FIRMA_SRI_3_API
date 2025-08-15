#!/bin/bash
set -e

# Cargar variables de entorno desde .env si existe
if [ -f /var/www/html/.env ]; then
    export $(cat /var/www/html/.env | grep -v '^#' | xargs)
fi

# Crear directorios necesarios usando variables de entorno
mkdir -p /var/www/html/var/log
mkdir -p /var/www/html/var/cache
mkdir -p ${UPLOAD_DIR:-/var/www/html/var/tmp}
mkdir -p /var/www/facturacion/autorizados
mkdir -p /var/www/facturacion/firmados
mkdir -p /var/www/facturacion/sin_firma
mkdir -p /var/www/facturacion/temporales
mkdir -p /var/www/facturacion/logs

# Configurar permisos
chmod -R 777 /var/www/html/var
chmod -R 777 /var/www/facturacion
chown -R www-data:www-data /var/www/html/var
chown -R www-data:www-data /var/www/facturacion

# Ejecutar el comando original
exec "$@"
