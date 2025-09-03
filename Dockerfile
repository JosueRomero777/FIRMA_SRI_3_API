FROM php:8.4-apache

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libssl-dev \
    openssl \
    libcurl4-openssl-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        xml \
        opcache \
        curl \
        zip \
        soap \
    && docker-php-ext-enable opcache

# Habilitar módulos de Apache
RUN a2enmod rewrite headers

# Configurar el directorio de trabajo
WORKDIR /var/www/html

# Copiar el código fuente
COPY . .

# Configurar Apache para Slim Framework
COPY apache-slim.conf /etc/apache2/sites-available/000-default.conf

# Instalar dependencias de Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Crear directorios necesarios y configurar permisos
RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
    && mkdir -p /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/facturacion/autorizados \
    && mkdir -p /var/tmp \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/www/facturacion \
    && chown -R www-data:www-data /var/tmp \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/facturacion \
    && chmod -R 775 /var/tmp

# Configurar el punto de entrada
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

# Puerto expuesto
EXPOSE 80

# Comando por defecto
CMD ["apache2-foreground"]