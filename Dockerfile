FROM php:8.2-apache

# Extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql zip gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilita mod_rewrite do Apache (necessário para REST API)
RUN a2enmod rewrite

# Configura o DocumentRoot e AllowOverride para .htaccess funcionar
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Copia o projeto para dentro do container
COPY . /var/www/html/

# Remove index.html padrão do Apache se existir
RUN rm -f /var/www/html/index.html

# Permissões corretas
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Porta padrão Apache
EXPOSE 80
