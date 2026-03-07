# =============================================================================
# STAGE 1: Build frontend assets with Node 20 (needs lots of RAM for Webpack)
# =============================================================================
FROM node:20-bookworm AS asset-builder

WORKDIR /app

# Copy package files first for layer caching
COPY package.json package-lock.json* ./

# Install Node dependencies
RUN npm install --legacy-peer-deps --engine-strict=false

# Copy only the files needed for the Webpack build
COPY webpack.mix.js webpack-login.mix.js* tailwind.config.js* postcss.config.js* ./
COPY resources/ resources/

# Build production assets with generous memory
RUN NODE_OPTIONS="--max-old-space-size=6144" npx mix --production
RUN NODE_OPTIONS="--max-old-space-size=6144" npx mix --mix-config=webpack-login.mix.js --production

# Create a synchronization token to force sequential execution in BuildKit
RUN touch /app/build-done.txt

# =============================================================================
# STAGE 2: PHP runtime image
# =============================================================================
FROM php:8.3-fpm-bookworm

# Force sequential build execution for Railway: wait for asset-builder to finish 
# before starting PHP extensions/composer compilation. This prevents OOM errors 
# by avoiding parallel CPU/RAM exhaustion.
COPY --from=asset-builder /app/build-done.txt /tmp/build-done.txt

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
COPY composer.json composer.lock* ./

# Configure GitHub OAuth token to prevent rate limiting
ARG GITHUB_TOKEN
RUN if [ -n "$GITHUB_TOKEN" ]; then \
        composer config -g github-oauth.github.com "$GITHUB_TOKEN"; \
    fi

RUN COMPOSER_MEMORY_LIMIT=-1 composer update --ignore-platform-reqs --no-dev --no-scripts --no-autoloader --no-interaction

# Copy application files
COPY . /var/www/html

# Complete Composer Autoload
RUN COMPOSER_MEMORY_LIMIT=-1 composer dump-autoload --optimize --no-dev --no-interaction --no-scripts

# Copy pre-built assets from the Node builder stage
COPY --from=asset-builder /app/public/js/ /var/www/html/public/js/
COPY --from=asset-builder /app/public/css/ /var/www/html/public/css/
COPY --from=asset-builder /app/public/fonts/ /var/www/html/public/fonts/
COPY --from=asset-builder /app/public/img/ /var/www/html/public/img/
COPY --from=asset-builder /app/public/mix-manifest.json /var/www/html/public/mix-manifest.json
COPY --from=asset-builder /app/public/vendor/ /var/www/html/public/vendor/

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
