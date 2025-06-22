# 🐳 Multi-stage Docker build for production-ready PHP application

# ========================================
# BASE STAGE - Common dependencies
# ========================================
FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    redis \
    supervisor \
    nginx

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create application user
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Set working directory
WORKDIR /var/www

# ========================================
# DEVELOPMENT STAGE
# ========================================
FROM base AS development

# Install Xdebug for development
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Copy Xdebug configuration
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Copy PHP configuration for development
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/php.ini

# Install Node.js for asset compilation
RUN apk add --no-cache nodejs npm

# Copy application files
COPY --chown=www:www . .

# Install PHP dependencies
RUN composer install --no-scripts --no-autoloader

# Install Node.js dependencies
RUN npm install

# Generate autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www:www /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

USER www

EXPOSE 9000

CMD ["php-fpm"]

# ========================================
# PRODUCTION STAGE
# ========================================
FROM base AS production

# Copy PHP configuration for production
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application files
COPY --chown=www:www . .

# Install production dependencies only
RUN composer install --no-dev --optimize-autoloader --no-scripts \
    && composer clear-cache

# Copy pre-built assets (from CI/CD)
COPY --chown=www:www public/build/ public/build/

# Set permissions
RUN chown -R www:www /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache \
    && chmod +x /var/www/docker/entrypoint.sh

# Create necessary directories
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /var/log/nginx \
    && mkdir -p /var/run/nginx

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

ENTRYPOINT ["/var/www/docker/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ========================================
# WEBSOCKET STAGE
# ========================================
FROM base AS websocket

# Copy application files
COPY --chown=www:www . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Install ReactPHP and Ratchet for WebSocket
RUN composer require ratchet/pawl react/socket

# Set permissions
RUN chown -R www:www /var/www

USER www

EXPOSE 8080

CMD ["php", "console", "websocket:serve", "--port=8080"]

# ========================================
# QUEUE WORKER STAGE
# ========================================
FROM base AS queue

# Copy application files
COPY --chown=www:www . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy Supervisor configuration for queue workers
COPY docker/supervisor/queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf

# Set permissions
RUN chown -R www:www /var/www

USER www

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/queue-worker.conf"]

# ========================================
# SCHEDULER STAGE
# ========================================
FROM base AS scheduler

# Copy application files
COPY --chown=www:www . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Set permissions
RUN chown -R www:www /var/www

# Copy crontab
COPY docker/cron/crontab /etc/crontabs/www

USER www

CMD ["crond", "-f", "-l", "2"]
