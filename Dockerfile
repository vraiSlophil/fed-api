FROM bitnami/laravel:12.0.8-debian-12-r0

# Copier les fichiers du projet
COPY . .

# Installer les dépendances du projet
RUN composer install --optimize-autoloader --no-dev

# Configurer les permissions
RUN chown -R daemon:daemon storage bootstrap/cache
