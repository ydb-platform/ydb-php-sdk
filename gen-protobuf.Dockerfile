FROM thecodingmachine/php:8.0-v4-cli

ENV PHP_EXTENSION_GRPC=1
ENV PHP_EXTENSION_BCMATH=1

USER root:root

RUN wget -O /tmp/z.$$ https://github.com/protocolbuffers/protobuf/releases/download/v22.0/protoc-22.0-linux-x86_64.zip \
	&& unzip -d /usr/local /tmp/z.$$ bin/protoc \
	&& unzip -d /usr /tmp/z.$$ include/google/protobuf/*.proto \
	&& rm /tmp/z.$$

RUN chmod a+x /usr/local/bin/protoc


# Copying a compiled version of grpc_php_plugin

COPY php_plugin/grpc_php_plugin /usr/local/bin/grpc_php_plugin

RUN chmod a+x /usr/local/bin/grpc_php_plugin

# [ OR ]

# Building grpc_php_plugin from sources

# RUN sudo apt update
# RUN sudo apt install -y build-essential cmake pkg-config libsystemd-dev

# RUN mkdir -p /opt/grpc && cd /opt/grpc \
# 	&& git clone -b v1.52.1 https://github.com/grpc/grpc grpc-1.25.1 \
# 	&& cd grpc-1.25.1 \
# 	&& git submodule update --init \
# 	&& mkdir -p cmake/build \
# 	&& cd cmake/build \
# 	&& cmake ../.. \
# 	&& make grpc_php_plugin \
# 	&& cp grpc_php_plugin /usr/local/bin/grpc_php_plugin

