FROM ilyakharev/php-grpc-and-go:7.2

WORKDIR /workload
COPY /src ./ydb/src
COPY /protos ./ydb/protos
COPY /composer.json ./ydb/
COPY /slo-workload .
RUN composer update
#RUN cd go-server && go install && go build .
#RUN cd ../
RUN go version
ENTRYPOINT  ["php", "application.php"]
