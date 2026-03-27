# Use the official PHP image with Apache (PHP 8.2 as specified)
FROM php:8.2-apache

# Install PostgreSQL client libraries and pdo_pgsql extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy all files from the repo to the web root
COPY . /var/www/html/

# Set proper permissions for web root
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 10000 (Render uses this for web services)
EXPOSE 10000

# Change the Apache port to 10000 (Render requirement)
RUN sed -i 's/80/10000/' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Enable Apache rewrite module (useful for frameworks)
RUN a2enmod rewrite

# Start Apache in the foreground
CMD ["apache2-foreground"]
