# Nginx image with a baked-in snapshot of public/ so it can serve the
# static assets without sharing a bind mount with the app container.
# User-uploaded files under public/uploads come from a shared named volume.
FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
