FROM php:8.2-cli

WORKDIR /var/www/html

RUN docker-php-ext-install pdo_sqlite sqlite3 mbstring opcache

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
