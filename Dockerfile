FROM php:8.2-apache

# Расширения PHP: MySQL, ZIP (для XLSX), GD (для сжатия фото)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libwebp-dev \
    libjpeg-dev \
    libpng-dev \
    libgif-dev \
    unzip \
    && docker-php-ext-install pdo_mysql zip \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-png \
    && docker-php-ext-install gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
RUN composer install --no-dev --optimize-autoloader 2>/dev/null || true

# Каталог загрузок
RUN mkdir -p uploads/breakdowns && chown -R www-data:www-data uploads

# mod_rewrite (если нужны «красивые» URL)
RUN a2enmod rewrite
