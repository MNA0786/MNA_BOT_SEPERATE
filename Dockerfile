# ==============================
# DOCKERFILE FOR ENTERTAINMENT TADKA BOT
# ==============================

# Use official PHP image with Apache
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nano \
    cron \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache modules
RUN a2enmod rewrite headers expires deflate

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create necessary directories AND files with proper permissions
RUN mkdir -p /var/www/html/data /var/www/html/backups /var/www/html/logs /var/www/html/scans && \
    touch /var/www/html/bot_activity.log && \
    touch /var/www/html/logs/error.log && \
    chmod -R 777 /var/www/html/data /var/www/html/backups /var/www/html/logs /var/www/html/scans && \
    chmod 666 /var/www/html/bot_activity.log && \
    chmod 666 /var/www/html/logs/error.log

# Set environment variables (will be overridden by docker-compose or render)
ENV BOT_TOKEN=""
ENV ADMIN_ID="1080317415"
ENV APP_ENV="production"

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy custom Apache config if needed
# COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Setup cron for auto tasks
RUN echo "0 3 * * * curl -s http://localhost/cron.php?task=backup > /dev/null 2>&1" | crontab - && \
    echo "0 */6 * * * curl -s http://localhost/cron.php?task=scan > /dev/null 2>&1" | crontab - && \
    echo "* * * * * curl -s http://localhost/cron.php?task=timers > /dev/null 2>&1" | crontab -

# Copy cron script (make sure it exists)
# COPY cron.php /var/www/html/cron.php

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
