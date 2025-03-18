# Use the official PHP image with Apache as the base image
FROM php:8.2-apache

# Install necessary system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for better URL handling
RUN a2enmod rewrite

# Set up the working directory for your app
WORKDIR /var/www/html

# Copy your application's source code into the container
COPY . /var/www/html/

# Set appropriate file permissions (assuming your app needs write access to the 'storage' folder)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 to allow HTTP traffic
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
