FROM nginx:1.29-alpine AS dev

COPY . /var/www/app

RUN rm -rf /docker

EXPOSE 80

FROM nginx:1.29-alpine AS prod

COPY public/ /var/www/app/public/

USER nginx

EXPOSE 80