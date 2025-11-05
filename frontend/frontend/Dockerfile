# ================================
# FRONTEND Dockerfile (frontend/Dockerfile)
# ================================
FROM nginx:alpine

# Supprime la config par défaut de Nginx
RUN rm /etc/nginx/conf.d/default.conf


# Copie les fichiers du frontend (HTML, CSS, JS)
COPY . /usr/share/nginx/html

# Expose le port 80
EXPOSE 80

# Démarre Nginx
CMD ["nginx", "-g", "daemon off;"]
