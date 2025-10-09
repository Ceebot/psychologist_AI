FROM php:8.3-fpm-alpine

# Установка системных зависимостей
RUN apk add --no-cache \
    postgresql-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev

# Установка PHP расширений
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    intl \
    opcache \
    mbstring \
    xml \
    dom \
    simplexml

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка рабочей директории
WORKDIR /var/www/html

# Настройка прав
RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
