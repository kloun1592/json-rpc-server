FROM php:8.3-cli-alpine

RUN apk add --no-cache $PHPIZE_DEPS linux-headers git \
    && pecl install openswoole \
    && docker-php-ext-enable openswoole \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-interaction --prefer-dist

EXPOSE 9501

CMD ["php", "examples/openswoole-server.php"]
