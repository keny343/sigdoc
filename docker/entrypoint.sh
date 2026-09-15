#!/bin/bash
set -euo pipefail

PORT="${PORT:-80}"

sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/uploads /var/www/html/logs /var/www/html/backups
chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs /var/www/html/backups
chmod -R ug+rwX /var/www/html/uploads /var/www/html/logs /var/www/html/backups

exec "$@"
