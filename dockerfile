FROM php:8.4-apache

# 1. Системные зависимости (Убрали лишнее, добавили libpq-dev для Postgres)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    libyaml-dev \
    libicu-dev \
    libpq-dev \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 2. Установка PHP расширений (УДАЛЕНЫ pdo_mysql и mysqli, ДОБАВЛЕНЫ pdo_pgsql и pgsql)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        mbstring \
        xml \
        opcache \
        intl \
        exif

# 3. Установка YAML через PECL
RUN pecl install yaml \
    && docker-php-ext-enable yaml

# 4. Настройка PHP
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'upload_max_filesize=64M'; \
    echo 'post_max_size=64M'; \
    echo 'memory_limit=256M'; \
} > /usr/local/etc/php/conf.d/october-defaults.ini

# 5. Apache модули
RUN a2enmod rewrite expires

# 6. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Подготовка кэша ядра October CMS
WORKDIR /usr/src/october
RUN composer create-project october/october . --no-scripts --no-interaction --ignore-platform-reqs --prefer-dist

# Копируем ваш код в кэш (для Render)
COPY app ./app
COPY plugins ./plugins
COPY themes ./themes

RUN chown -R www-data:www-data /usr/src/october

# Рабочая директория
WORKDIR /var/www/html

# Копируем ваш код в рабочую директорию
COPY app ./app
COPY plugins ./plugins
COPY themes ./themes

# 7. Фикс прав
RUN chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]