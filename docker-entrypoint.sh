#!/bin/bash
set -e

# Если artisan нет (папка пустая или примонтирован пустой volume)
if [ ! -f "/var/www/html/artisan" ]; then
    echo "Artisan not found. Copying October CMS from cache..."
    # Копируем ядро, не перезаписывая ваши локальные папки темы/плагинов (-n)
    cp -rn /usr/src/october/. /var/www/html/
             
    echo "Files copied successfully!"
fi

mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/cms/cache \
         storage/cms/combiner \
         storage/cms/twig \
         storage/logs \
         storage/temp

echo "Fixing permissions..."

chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# 4. Выставляем права 775 или 777
chmod -R 777 /var/www/html/storage
chmod -R 777 /var/www/html/bootstrap/cache

echo "Storage is ready."

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan october:migrate
fi

exec "$@"