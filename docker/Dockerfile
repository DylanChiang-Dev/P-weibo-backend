FROM php:8.2-fpm

# 安裝系統套件與 PHP 擴充：pdo_mysql、gd、fileinfo
RUN apt-get update && apt-get install -y \
    libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
    unzip git \
  && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
  && docker-php-ext-install gd pdo_mysql \
  && rm -rf /var/lib/apt/lists/*

# 啟用 opcache（開發可保留預設）
RUN docker-php-ext-install opcache

# Copy PHP configuration for large file uploads
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/project

# 建立必要目錄（容器內）
RUN mkdir -p storage/uploads logs

# 預設使用者 www-data（與 php-fpm 相同）
RUN chown -R www-data:www-data /var/www/project

CMD ["php-fpm"]