FROM php:8.2-apache

# Install PostgreSQL PDO extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Fix MPM conflict: forcibly remove event/worker, enable prefork + needed modules
# Force cache bust: 2026-08-18T13:24
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Remove dev-only and legacy files that should not be served
RUN rm -f assets/js/main.js

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod 600 /var/www/html/.env 2>/dev/null || true

# Apache configuration — listen on PORT (Railway) or default 80
RUN echo '<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Railway injects PORT env var; default to 80 for local builds
RUN echo 'Listen ${PORT}' > /etc/apache2/ports.conf

ENV PORT=80
EXPOSE 80

CMD ["apache2-foreground"]
