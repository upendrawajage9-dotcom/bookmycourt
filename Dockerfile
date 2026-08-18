FROM php:8.2-apache

# Install PostgreSQL PDO extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable required Apache modules (rewrite, headers)
RUN a2enmod rewrite headers

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

# Write the VirtualHost config (uses PORT env var at runtime)
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

# Create entrypoint script that fixes MPM at RUNTIME (bypasses all caching)
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# ── Fix MPM: ensure ONLY mpm_prefork is loaded ──\n\
rm -f /etc/apache2/mods-enabled/mpm_event.load \\\n\
      /etc/apache2/mods-enabled/mpm_event.conf \\\n\
      /etc/apache2/mods-enabled/mpm_worker.load \\\n\
      /etc/apache2/mods-enabled/mpm_worker.conf\n\
\n\
# Enable prefork if not already enabled\n\
if [ ! -f /etc/apache2/mods-enabled/mpm_prefork.load ]; then\n\
    ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/\n\
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/\n\
fi\n\
\n\
# ── Set Apache listen port from Railway PORT env var (default 80) ──\n\
export PORT="${PORT:-80}"\n\
echo "Listen ${PORT}" > /etc/apache2/ports.conf\n\
sed -i "s/\\${PORT}/${PORT}/g" /etc/apache2/sites-available/000-default.conf\n\
\n\
echo ">>> Apache starting on port ${PORT} with mpm_prefork"\n\
exec apache2-foreground\n\
' > /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

ENV PORT=80
EXPOSE 80

CMD ["/usr/local/bin/docker-entrypoint.sh"]
