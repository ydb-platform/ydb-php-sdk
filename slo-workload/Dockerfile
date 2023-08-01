FROM ilyakharev/php-grpc-and-go:7.2

WORKDIR /workload
COPY . .
RUN composer update
RUN cd go-server && go build .
RUN cd ../

ENTRYPOINT ./go-server/testHttpServer & php application.php
