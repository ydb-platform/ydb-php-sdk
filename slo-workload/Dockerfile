FROM ilyakharev/php-grpc-and-go:7.2

WORKDIR /workload
COPY . .
RUN composer update
RUN cd go-server
RUN go build .
RUN cd ../
RUN pkill php
ENTRYPOINT ./go-server/testHttpServer & php application.php
