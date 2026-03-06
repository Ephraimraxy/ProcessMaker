FROM php:8.3-fpm-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libmagickwand-dev \
    libc-client-dev \
    libkrb5-dev \
    libssl-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libicu-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Basic PHP Extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath intl zip

# Configure and Install GD (Image Processing)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd

# Configure and Install IMAP (Mail)
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap

# Install PECL Extensions (Redis)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install PECL Extensions (Imagick)
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Build Optimization: Install dependencies before copying the full app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

# Copy application files
COPY . /var/www/html

# Complete Composer Autoload
RUN composer dump-autoload --optimize --no-dev --no-interaction

# Install Node dependencies and build assets
RUN npm ci && npm run production

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Generate key if not exists (usually provided via env)
RUN php artisan key:generate --force || true

# Install Nginx and Supervisor
RUN apt-get update && apt-get install -y nginx supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor && \
    mkdir -p /var/run/nginx && \
    chown -R www-data:www-data /var/log/nginx

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]




