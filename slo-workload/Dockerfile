FROM ilyakharev/php-grpc-and-go:7.2

RUN go version
COPY . /src
WORKDIR /src/slo-workload
RUN composer update
RUN cd go-server && go install && go build .
RUN cd ../
#RUN apt update; apt install htop
ENTRYPOINT  ["php", "application.php"]
