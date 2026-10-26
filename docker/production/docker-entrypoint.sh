#!/bin/sh
set -eu

PORT="${PORT:-8080}"
export PORT

# App Platform injects PORT; render nginx listen directive.
sed "s/\${PORT}/${PORT}/g" /etc/nginx/nginx.conf > /tmp/nginx.conf
cp /tmp/nginx.conf /etc/nginx/nginx.conf

role="${1:-web}"

case "$role" in
  web)
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/padeliz.conf
    ;;
  worker)
    exec php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600 --no-interaction
    ;;
  scheduler)
    exec php artisan schedule:work --no-interaction
    ;;
  migrate|deploy)
    exec php artisan backend:refresh --deploy --no-interaction
    ;;
  *)
    exec "$@"
    ;;
esac
