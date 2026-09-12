# SLO workload image. The build context is the repository root, so that the workload
# is built against the SDK sources of the checked out revision:
#
#   docker build -t ydb-app-current -f slo-workload/Dockerfile .
FROM php:8.2-cli

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath grpc pcntl protobuf sysvmsg

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# grpc needs fork support: the workload runs its read, write and metrics jobs
# in forked processes.
RUN { \
        echo "grpc.enable_fork_support=1"; \
        echo "grpc.poll_strategy=poll"; \
    } > /usr/local/etc/php/conf.d/grpc-fork.ini

COPY . /src
WORKDIR /src/slo-workload

# The lock file is not committed, and the SDK is required as a path repository.
RUN composer update --no-dev --no-interaction --no-progress --prefer-dist

ENTRYPOINT ["php", "application.php"]
