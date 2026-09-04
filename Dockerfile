FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
COPY . /var/www/html/
RUN a2enmod rewrite
# Expose port (Render sets PORT env variable, but expects 80 by default for standard apache if not overridden)
EXPOSE 80
