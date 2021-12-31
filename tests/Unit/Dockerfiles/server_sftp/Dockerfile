FROM alpine:latest

LABEL MAINTAINER="Hélio <flyclone@helio.me>"

EXPOSE 22

RUN apk add openssh
RUN echo "PasswordAuthentication yes" >> /etc/ssh/sshd_config
RUN adduser -h /home/flyclone -s /bin/sh -D flyclone
RUN echo -n "flyclone:flyclone" | chpasswd

ENTRYPOINT ["/entrypoint.sh"]

COPY entrypoint.sh /
RUN ["chmod", "+x", "/entrypoint.sh"]

