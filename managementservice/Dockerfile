# Sử dụng hình ảnh PHP 8.2 làm hình ảnh cơ sở
FROM php:8.2-fpm
# Arguments defined in docker-compose.yml
# ARG user
# ARG uid

# Set Environment Variables
ENV DEBIAN_FRONTEND noninteractive
ENV PATH="${PATH}:/usr/bin/git"


# Cài đặt các công cụ và extension PHP
RUN set -eux; \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends \
            curl \
            libmemcached-dev \
            libz-dev \
            libpq-dev \
            libjpeg-dev \
            libpng-dev \
            libfreetype6-dev \
            libssl-dev \
            libwebp-dev \
            libxpm-dev \
            libmcrypt-dev \
            libonig-dev; \
    rm -rf /var/lib/apt/lists/*# Install system dependencies
RUN apt-get update && apt-get install -y \
        git \
        libonig-dev \
        libxml2-dev \
        zip \
        unzip

RUN apt-get update; \
        # Imagick extension
        apt-get install -y libmagickwand-dev; \
        pecl install imagick; \
        docker-php-ext-install gd; \
        docker-php-ext-enable imagick; \
        # Success
        true



RUN set -eux; \
    # Install PHP extensions
    docker-php-ext-install pdo_mysql pdo_pgsql gd; \
    php -r 'var_dump(gd_info());'

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create system user to run Composer and Artisan Commands
# RUN useradd -G www-data,root -u $uid -d /home/$user $user
# RUN mkdir -p /home/$user/.composer && \
#     chown -R $user:$user /home/$user
# Set working directory
WORKDIR /var/www/html/managementservice

# # Copy source code to container
COPY . /var/www/html/managementservice
# USER $user

# Expose cổng mặc định cho server HTTP
EXPOSE 9000

# Khởi chạy server PHP-FPM
CMD ["php-fpm"]

