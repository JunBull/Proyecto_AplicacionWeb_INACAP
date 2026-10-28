FROM php:8.3-cli

# Directorio de trabajo
WORKDIR /var/www/html

# Dependencias del sistema y bibliotecas para extensiones requeridas
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    zip \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    pkg-config \
    ca-certificates \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# Configuración e instalación de extensiones PHP (pdo_pgsql, pgsql, gd, zip, bcmath, pcntl)
# Nota: mbstring, openssl, fileinfo y curl ya vienen integradas de forma nativa en php:8.3-cli
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        bcmath \
        pcntl

# Instalación de Node.js 20.x y npm para compilación de assets Vite/Tailwind
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Instalación de Composer oficial
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Script de entrada para verificación de permisos de storage y bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN tr -d '\r' < /usr/local/bin/entrypoint.sh > /usr/local/bin/entrypoint_clean.sh \
    && mv /usr/local/bin/entrypoint_clean.sh /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# Puerto de exposición del servidor Laravel
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
