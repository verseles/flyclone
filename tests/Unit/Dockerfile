FROM jitesoft/phpunit:8.4-12
COPY --from=rclone/rclone /usr/local/bin/rclone /usr/bin/

LABEL MAINTAINER="Hélio <flyclone@helio.me>"

# make /tmp free to write and read for everyone
RUN chmod -R 777 /tmp

WORKDIR /app
