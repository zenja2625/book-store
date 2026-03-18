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

# 3. ФИКС ПРАВ (Самый важный этап)
echo "Fixing permissions..."
# Делаем www-data владельцем всего
chown -R www-data:www-data /var/www/html

# Даем права на запись (775 позволяет и владельцу, и группе писать в папки)
find /var/www/html/storage -type d -exec chmod 775 {} \;
find /var/www/html/storage -type f -exec chmod 664 {} \;

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php artisan october:migrate
fi

exec "$@"