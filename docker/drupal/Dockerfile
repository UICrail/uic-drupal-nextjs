FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    postgresql-dev \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    opcache \
    soap

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Drush Launcher
RUN wget -O drush.phar https://github.com/drush-ops/drush-launcher/releases/latest/download/drush.phar && \
    chmod +x drush.phar && \
    mv drush.phar /usr/local/bin/drush

# Set working directory
WORKDIR /var/www/html

# Copy Drupal files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/web/sites

# PHP-FPM configuration
RUN { \
    echo '[www]'; \
    echo 'user = www-data'; \
    echo 'group = www-data'; \
    echo 'listen = 9000'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 50'; \
    echo 'pm.start_servers = 5'; \
    echo 'pm.min_spare_servers = 5'; \
    echo 'pm.max_spare_servers = 35'; \
    echo 'pm.max_requests = 500'; \
} | tee /usr/local/etc/php-fpm.d/www.conf

# OPcache configuration
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} | tee /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 9000

CMD ["php-fpm"]

