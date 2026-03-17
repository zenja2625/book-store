#!/bin/bash
set -e

# Если artisan нет (папка пустая или примонтирован пустой volume)
if [ ! -f "/var/www/html/artisan" ]; then
    echo "Artisan not found. Copying October CMS from cache..."
    # Копируем ядро, не перезаписывая ваши локальные папки темы/плагинов (-n)
    cp -rn /usr/src/october/. /var/www/html/
    
    mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/cms/cache \
             storage/logs
             
    echo "Files copied successfully!"
fi

# Всегда проверяем права перед стартом (особенно важно для storage)
chown -R www-data:www-data storage themes plugins app

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan october:migrate
fi

exec "$@"