# =============================================================================
# PHP runtime image (All JS/CSS assets are pre-built locally and committed)
# =============================================================================
FROM php:8.4-fpm-bookworm

# Install core system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    zip \
    unzip \
    procps \
    nginx \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Official PHP Extension Installer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install PHP extensions in batches to save memory
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql mbstring exif pcntl bcmath
RUN IPE_GD_WITHOUTAVIF=1 install-php-extensions intl zip gd imap
RUN install-php-extensions redis imagick rdkafka opcache sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Build Optimization: Install dependencies before copying the full app
COPY composer.json composer.lock* ./

# Configure GitHub OAuth token to prevent rate limiting
ARG GITHUB_TOKEN
RUN if [ -n "$GITHUB_TOKEN" ]; then \
        composer config -g github-oauth.github.com "$GITHUB_TOKEN"; \
    fi

RUN COMPOSER_MEMORY_LIMIT=-1 composer install --ignore-platform-reqs --no-dev --no-scripts --no-autoloader --no-interaction

# Copy application files (includes pre-built JS/CSS/images/fonts/monaco from local build)
COPY . /var/www/html

# Complete Composer Autoload
RUN COMPOSER_MEMORY_LIMIT=-1 composer dump-autoload --optimize --no-dev --no-interaction --no-scripts

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy configuration files and entrypoint
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Set permissions
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create necessary directories
RUN mkdir -p /var/log/supervisor && \
    mkdir -p /var/run/nginx && \
    chown -R www-data:www-data /var/log/nginx

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
