FROM jitesoft/phpunit:latest
COPY --from=rclone/rclone /usr/local/bin/rclone /usr/bin/

LABEL MAINTAINER="Hélio <flyclone@helio.me>"

RUN rm -rf /tmp/*

WORKDIR /app
