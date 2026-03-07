FROM php:8.3-fpm-bookworm

# Install core system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    zip \
    unzip \
    procps \
    nodejs \
    npm \
    nginx \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Official PHP Extension Installer (much more robust for Railway resource limits)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install PHP extensions in one consolidated, optimized layer
RUN chmod +x /usr/local/bin/install-php-extensions && \
    MAKEFLAGS="-j2" install-php-extensions pdo_mysql mbstring exif pcntl bcmath intl zip gd imap redis imagick rdkafka opcache sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Build Optimization: Install dependencies before copying the full app
# NOTE: Using 'composer update' because composer.lock is out of sync with composer.json.
# Once lock file is regenerated and committed, switch back to 'composer install'.
COPY composer.json composer.lock* ./
RUN COMPOSER_MEMORY_LIMIT=-1 composer update --ignore-platform-reqs --no-dev --no-scripts --no-autoloader --no-interaction

# Copy application files
COPY . /var/www/html

# Complete Composer Autoload
RUN COMPOSER_MEMORY_LIMIT=-1 composer dump-autoload --optimize --no-dev --no-interaction --no-scripts

# Install Node dependencies and build assets
RUN npm ci && NODE_OPTIONS="--max-old-space-size=2048" npm run production

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Note: APP_KEY should be set as a Railway environment variable, not generated at build time

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor && \
    mkdir -p /var/run/nginx && \
    chown -R www-data:www-data /var/log/nginx

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]




