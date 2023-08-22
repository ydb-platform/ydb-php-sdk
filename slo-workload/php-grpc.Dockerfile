FROM ubuntu:20.04
LABEL authors="ilyakharev"

RUN apt-get upgrade
RUN apt-get update
RUN apt-get install -y software-properties-common
RUN apt-get update
RUN add-apt-repository -y ppa:ondrej/php
RUN apt-get update
RUN apt-get install -y libz-dev
RUN apt-get update
RUN apt-get install -y php7.2-fpm php7.2-cli php7.2-curl php7.2-json php-pear php7.2-dev php7.2-bcmath
RUN apt-get install -y php7.2-xml
RUN apt-get update
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer
RUN pecl install grpc-1.45.0
RUN echo "extension=grpc.so" >> /etc/php/7.2/cli/php.ini
RUN apt-get install --fix-missing -y git
RUN echo "grpc.enable_fork_support = 1"  >> /etc/php/7.2/cli/php.ini
RUN echo "grpc.poll_strategy = poll" >> /etc/php/7.2/cli/php.ini
