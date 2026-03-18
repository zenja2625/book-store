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
# Даем права 777 рекурсивно. 
# На Render это безопасно, так как контейнер изолирован.
chmod -R 777 storage

# Явно меняем владельца на пользователя, от которого работает Apache
chown -R www-data:www-data storage

echo "Storage is ready."

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan october:migrate
fi

exec "$@"