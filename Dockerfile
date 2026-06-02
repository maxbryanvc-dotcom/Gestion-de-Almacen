# ============================================================
# SIGMA ERP — Imagen Docker
# PHP 8.2 + Apache + todas las extensiones necesarias
# ============================================================

FROM php:8.2-apache

# Instalar extensiones del sistema
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    curl \
    unzip \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP necesarias para el sistema
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        xml \
        xmlwriter \
        fileinfo \
        opcache

# Activar mod_rewrite de Apache (para .htaccess)
RUN a2enmod rewrite

# Configurar Apache para permitir .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

# Instalar Composer globalmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar código del proyecto
COPY . /var/www/html/

# Instalar dependencias de Composer (PHPWord, PhpSpreadsheet, DomPDF)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permisos correctos para uploads
RUN mkdir -p /var/www/html/uploads/plantillas/word \
             /var/www/html/uploads/plantillas/excel \
             /var/www/html/uploads/plantillas/pdf \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

# Puerto del servidor web
EXPOSE 80
