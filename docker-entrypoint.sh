#!/bin/bash
set -e

# 1. Проверка наличия ядра October CMS
if [ ! -f "/var/www/html/artisan" ]; then
    echo "Artisan not found. Copying October CMS from cache..."
    # Копируем ядро из кэша образа в рабочую директорию 
    cp -rn /usr/src/october/. /var/www/html/
    echo "Files copied successfully!"
fi

echo "Preparing storage structure..."

# 2. Создаем базовые папки 
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/cms/cache \
         storage/cms/combiner \
         storage/cms/twig \
         storage/logs \
         storage/temp \
         bootstrap/cache

# 3. ХАК ДЛЯ RENDER: Создаем подпапки кэша заранее (00..ff)
# Это лечит ошибку "No such file or directory", так как папки уже будут существовать
echo "Pre-creating cache subdirectories..."
cd /var/www/html/storage/framework/cache/data
printf "%s\n" {00..ff} | xargs -I {} mkdir -p {}
cd /var/www/html

# 4. Фикс прав доступа
echo "Applying ownership and permissions..."
# Делаем www-data владельцем ВСЕГО, что было скопировано или создано 
chown -R www-data:www-data /var/www/html

# Права 775 позволяют владельцу (www-data) и группе писать/создавать файлы 
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# 5. Очистка кэша перед запуском (от имени www-data) 
echo "Refreshing application state..."
# Удаляем старые конфиги, чтобы подхватились переменные окружения Render
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/services.php

# Принудительная очистка через artisan
su -s /bin/bash -c "php artisan cache:clear --no-interaction" www-data
su -s /bin/bash -c "php artisan config:clear --no-interaction" www-data


echo "Container is ready to start!"

# Запуск основной команды (apache2-foreground) 
exec "$@"