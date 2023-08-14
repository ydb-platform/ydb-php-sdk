FROM ilyakharev/php-grpc-and-go:7.2

WORKDIR /workload

RUN go version
COPY /src ./ydb/src
COPY /protos ./ydb/protos
COPY /composer.json ./ydb/
COPY /slo-workload .
RUN composer update
RUN cd go-server && go install && go build .
RUN cd ../
RUN apt update; apt install htop
ENTRYPOINT  ["php", "application.php"]
