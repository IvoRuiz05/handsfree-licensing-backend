FROM php:8.1-apache

# Instalar extensões do PostgreSQL e outras dependências
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar arquivos da aplicação
COPY . /var/www/html/

# Definir diretório de trabalho
WORKDIR /var/www/html

# Instalar dependências do Composer
RUN composer install --no-dev --optimize-autoloader

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expor porta
EXPOSE 80

CMD ["apache2-foreground"]
