#!/bin/bash

# ProcessMaker VPS Setup Script
# Run this on your fresh Ubuntu 20.04/22.04 VPS

set -e

echo "🚀 Starting ProcessMaker VPS Setup..."

# 1. Update System
echo "📦 Updating system packages..."
apt-get update && apt-get upgrade -y
apt-get install -y curl git unzip

# 2. Install Docker & Docker Compose
if ! command -v docker &> /dev/null; then
    echo "🐳 Installing Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
fi

# 3. Clone Repository (if strictly needed, otherwise assume manual upload/git clone)
# For this script, we assume the user is running it inside the project folder or will clone it.

# 4. Configuration
echo "⚙️ Configuring Environment..."

if [ ! -f .env ]; then
    read -p "Enter your Domain Name (e.g. pm.example.com): " APP_DOMAIN
    read -p "Enter your Email (for SSL): " SSL_EMAIL
    
    # Generate Passwords
    DB_PASSWORD=$(openssl rand -base64 12)
    DB_ROOT_PASSWORD=$(openssl rand -base64 12)
    REDIS_PASSWORD=$(openssl rand -base64 12)
    APP_KEY="base64:$(openssl rand -base64 32)"
    
    cp .env.production .env
    
    # Replace variables in .env
    sed -i "s/\${APP_DOMAIN}/$APP_DOMAIN/g" .env
    sed -i "s/\${SSL_EMAIL}/$SSL_EMAIL/g" .env
    sed -i "s/\${DB_PASSWORD}/$DB_PASSWORD/g" .env
    sed -i "s/\${DB_ROOT_PASSWORD}/$DB_ROOT_PASSWORD/g" .env
    sed -i "s/\${REDIS_PASSWORD}/$REDIS_PASSWORD/g" .env
    sed -i "s|APP_KEY=|APP_KEY=$APP_KEY|g" .env
    
    echo "✅ Configuration saved to .env"
else
    echo "ℹ️ .env file already exists, skipping configuration."
fi

# 5. Start Services
echo "🚀 Starting Docker Containers..."
docker compose -f docker-compose.prod.yml up -d --build

# 6. Wait for DB
echo "⏳ Waiting for Database to initialize..."
sleep 30

# 7. Install ProcessMaker
echo "💿 Running ProcessMaker Installer..."
docker compose -f docker-compose.prod.yml exec -T app php artisan processmaker:install --no-interaction --username=admin --password=admin --email=admin@example.com

echo "🎉 Deployment Complete!"
echo "Check your site at: https://$APP_DOMAIN"
echo "Login: admin / admin"
