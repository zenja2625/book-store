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
         storage/temp \
         bootstrap/cache

echo "Fixing permissions..."

chown -R www-data:www-data /var/www/html

# Выставляем права 775 (этого достаточно, если владелец www-data)
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

su -s /bin/bash -c "php artisan cache:clear" www-data

echo "Storage is ready."

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan october:migrate
fi

exec "$@"