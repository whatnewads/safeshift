FROM php:8.4-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js (for client build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Set working directory for server (PHP backend)
WORKDIR /var/www/html

# Copy server application
COPY server/ /var/www/html/

# Install PHP dependencies if composer.json exists
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Copy and build client application
WORKDIR /var/www/client
COPY client/package*.json /var/www/client/
RUN npm ci
COPY client/ /var/www/client/
RUN npm run build

# Copy built client assets to server public directory for serving static frontend
RUN mkdir -p /var/www/html/public/client && \
    cp -r /var/www/client/dist/* /var/www/html/public/client/ 2>/dev/null || true

# Set working directory back to server
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create logs directory with proper permissions
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs

# Apache configuration - document root is server/public
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80 443

CMD ["apache2-foreground"]
