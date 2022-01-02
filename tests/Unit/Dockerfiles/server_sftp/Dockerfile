FROM alpine:latest

LABEL MAINTAINER="Hélio <flyclone@helio.me>"

EXPOSE 22

RUN apk add openssh
RUN echo "PasswordAuthentication yes" >> /etc/ssh/sshd_config
RUN adduser -h /home/sftp -s /bin/sh -D sftp
RUN echo -n "sftp:sftp" | chpasswd

ENTRYPOINT ["/entrypoint.sh"]

COPY entrypoint.sh /
RUN ["chmod", "+x", "/entrypoint.sh"]

