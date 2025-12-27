FROM php:8.3-fpm

# Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    libpq-dev \
    postgresql-client \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath gd mbstring xml intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /app

# Copier les fichiers composer en premier pour profiter du cache Docker
COPY composer.json composer.lock ./

# Installer les dépendances PHP (include dev dependencies for seeders/factories)
RUN composer install --optimize-autoloader --no-scripts --no-interaction

# Copier le reste des fichiers du projet
COPY . .

# Finaliser l'installation de Composer (scripts post-install)
RUN composer dump-autoload --optimize

# Créer les répertoires nécessaires et configurer les permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Exposer le port 8000
EXPOSE 8000

# Démarrer le serveur Laravel
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan serve --host=0.0.0.0 --port=8000
