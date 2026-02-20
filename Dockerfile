FROM php:8.3-apache

# PHP 확장 설치 (PDO MySQL)
RUN docker-php-ext-install pdo pdo_mysql

# Apache mod_rewrite 활성화
RUN a2enmod rewrite

# AllowOverride 활성화 (프로젝트 루트 = Document Root)
RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

# PHP 설정
RUN echo "display_errors = On\n\
error_reporting = E_ALL\n\
log_errors = On\n\
memory_limit = 128M\n\
upload_max_filesize = 2M\n\
post_max_size = 8M\n\
date.timezone = Asia/Seoul" > /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# 엔트리포인트 스크립트 복사
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
