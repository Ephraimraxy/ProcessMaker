#!/bin/sh

# Replace ${PORT} in nginx config with the actual environment variable
if [ -z "$PORT" ]; then
  export PORT=80
fi

# Apply the port to the nginx configuration
sed -i "s/\${PORT}/${PORT}/g" /etc/nginx/sites-available/default

# Initial setup if needed (optional)
# php artisan migrate --force

# Provision users if variables are set
php artisan db:seed --class=UserSeeder --force

# Start Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
