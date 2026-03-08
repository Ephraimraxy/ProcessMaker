# =============================================================================
# STAGE 1: Lightweight asset build (SASS/CSS + Monaco copies only)
# Heavy JS assets (Parts 1-4) are pre-built and committed to the repository.
# =============================================================================
FROM node:20-bookworm AS asset-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --legacy-peer-deps --engine-strict=false

COPY webpack.part5.mix.js webpack-login.mix.js* tailwind.config.js* postcss.config.js* ./
COPY resources/ resources/

# Only build Part 5 (SASS/CSS + Monaco) and login — these are lightweight
RUN NODE_OPTIONS="--max-old-space-size=4096" npx mix --mix-config=webpack.part5.mix.js --production && \
    NODE_OPTIONS="--max-old-space-size=4096" npx mix --mix-config=webpack-login.mix.js --production

# =============================================================================
# STAGE 2: PHP runtime image
# =============================================================================
FROM php:8.3-fpm-bookworm

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

# Install PHP extensions
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

# Copy application files (includes pre-built JS/images/fonts from Parts 1-4)
COPY . /var/www/html

# Complete Composer Autoload
RUN COMPOSER_MEMORY_LIMIT=-1 composer dump-autoload --optimize --no-dev --no-interaction --no-scripts

# Copy ONLY the assets produced by Part 5 + login (CSS, vendor/monaco)
COPY --from=asset-builder /app/public/css/ /var/www/html/public/css/
COPY --from=asset-builder /app/public/vendor/ /var/www/html/public/vendor/

# Merge the Part 5 manifest entries into the pre-built manifest
COPY --from=asset-builder /app/public/mix-manifest.json /tmp/part5-manifest.json
RUN apt-get update && apt-get install -y jq && apt-get clean && rm -rf /var/lib/apt/lists/* && \
    jq -s '.[0] * .[1]' /var/www/html/public/mix-manifest.json /tmp/part5-manifest.json > /tmp/merged-manifest.json && \
    mv /tmp/merged-manifest.json /var/www/html/public/mix-manifest.json && \
    rm /tmp/part5-manifest.json

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor && \
    mkdir -p /var/run/nginx && \
    chown -R www-data:www-data /var/log/nginx

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
