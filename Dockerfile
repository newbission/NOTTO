FROM php:8.3-apache

# PHP 확장 설치 (PDO MySQL)
RUN docker-php-ext-install pdo pdo_mysql

# Apache mod_rewrite 활성화
RUN a2enmod rewrite

# Document Root 변경 (public/ → htdocs)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# API 디렉토리도 접근 가능하도록 Alias 설정 + Authorization 헤더 전달
RUN echo '<Directory /var/www/html/api>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1\n\
</Directory>\n\
Alias /api /var/www/html/api' > /etc/apache2/conf-available/api-alias.conf \
    && a2enconf api-alias

# PHP 설정
RUN echo "display_errors = On\n\
error_reporting = E_ALL\n\
log_errors = On\n\
memory_limit = 128M\n\
upload_max_filesize = 2M\n\
post_max_size = 8M\n\
date.timezone = Asia/Seoul" > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

EXPOSE 80
