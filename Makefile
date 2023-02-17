protobuf:
	docker build -f gen-protobuf.Dockerfile . -t ydb-php-sdk-proto-generator
	docker run --rm -it -v ${PWD}:${PWD} -w ${PWD} ydb-php-sdk-proto-generator protoc -I./ydb-api-protos -I./ydb-api-protos/protos -I./ydb-api-protos/protos/annotations -I/usr/include --php_out=./protos --grpc_out=./protos --plugin=protoc-gen-grpc=/usr/local/bin/grpc_php_plugin ./ydb-api-protos/*.proto ./ydb-api-protos/protos/*.proto ./ydb-api-protos/protos/annotations/*.proto
