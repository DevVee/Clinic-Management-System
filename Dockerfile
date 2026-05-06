FROM php:8.2-apache

# Install PostgreSQL PDO driver
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable URL rewriting
RUN a2enmod rewrite

# Allow .htaccess to override Apache config
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
    /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies (layer-cached)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# Copy app files
COPY . .

# Uploads directory (writable by Apache)
RUN mkdir -p uploads/profiles uploads/patient_photos \
    && chown -R www-data:www-data uploads \
    && chmod -R 755 uploads

EXPOSE 80
CMD ["apache2-foreground"]
