# Imagem base oficial do PHP com Apache
FROM php:8.3-apache

# Dependências do sistema para as extensões do PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    curl \
    unzip \
    git \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo_mysql \
        zip \
        gd \
        mbstring \
        intl \
        bcmath \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer oficial
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Habilitar mod_rewrite e permitir .htaccess (essencial para rotas amigáveis do Map-OS)
RUN a2enmod rewrite headers \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Configurações do PHP recomendadas para produção
RUN { \
    echo 'upload_max_filesize = 64M'; \
    echo 'post_max_size = 64M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 300'; \
    echo 'date.timezone = America/Sao_Paulo'; \
    echo 'variables_order = "EGPCS"'; \
} > /usr/local/etc/php/conf.d/mapos.ini

# Definir diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos do projeto para o container
COPY . /var/www/html/

# Instalar dependências PHP com Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=php

# Criar pastas essenciais e ajustar permissões para o Apache (www-data)
RUN mkdir -p /var/www/html/application/storage \
             /var/www/html/application/logs \
             /var/www/html/assets/uploads \
             /var/www/html/assets/userImage \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/application/logs \
    && chmod -R 775 /var/www/html/application/storage \
    && chmod -R 775 /var/www/html/assets/uploads \
    && chmod -R 775 /var/www/html/assets/userImage

# Copiar e configurar entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Volumes persistentes para uploads de emitente, fotos de perfil, storage e logs
VOLUME ["/var/www/html/assets/uploads", "/var/www/html/assets/userImage", "/var/www/html/application/storage", "/var/www/html/application/logs"]

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
