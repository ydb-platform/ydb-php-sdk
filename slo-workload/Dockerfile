FROM ilyakharev/php-grpc-and-go:7.2

WORKDIR /workload
COPY ./composer.json .
RUN composer update
#RUN cd go-server && go install && go build .
#RUN cd ../
COPY . .
ENTRYPOINT  ["php", "application.php"]
