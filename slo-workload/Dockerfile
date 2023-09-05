FROM ilyakharev/php-grpc:7.2

COPY . /src
WORKDIR /src/slo-workload
RUN composer update
RUN apt instll bmon
#RUN apt update; apt install htop
ENTRYPOINT  ["php", "application.php"]
