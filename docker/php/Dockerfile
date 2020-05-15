FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    libc-client-dev \
    libkrb5-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libxml2-dev \
    npm \
    && pecl install xdebug-2.9.5 \
    && rm -r /var/lib/apt/lists/*

RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) zip imap intl gd soap pcntl pdo_mysql \
    && docker-php-ext-enable gd imap intl zip soap pcntl pdo_mysql xdebug

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php \
	&& php -r "unlink('composer-setup.php');" \
	&& mv composer.phar /usr/bin/composer

# Install node
RUN curl -sL https://deb.nodesource.com/setup_12.x  | bash - \
    &&  apt-get install -y nodejs