FROM php:8.3-apache
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql
COPY index.php /var/www/html/index.php