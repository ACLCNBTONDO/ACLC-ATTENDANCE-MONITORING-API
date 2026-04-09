FROM php:8.2-cli

# Install mysqli and other extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN docker-php-ext-enable mysqli

WORKDIR /app
COPY . .

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "index.php"]
