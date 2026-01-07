FROM php:8.2-apache

# Installer PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copier le code
COPY public/index.php /var/www/html/

# Activer mod_rewrite
RUN a2enmod rewrite

# Exposer le port
EXPOSE 80

# Démarrer Apache
CMD ["apache2-foreground"]