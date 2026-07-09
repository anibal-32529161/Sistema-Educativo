FROM dunglas/frankenphp:php8.4.23-bookworm

# Install pdo_mysql and mysqli extensions
RUN docker-php-ext-install pdo_mysql mysqli

# Copy application files
COPY . /app

# Set working directory
WORKDIR /app

# Expose port 8080
EXPOSE 8080

# Start FrankenPHP
CMD ["frankenphp", "php-server", "--listen", "0.0.0.0:8080"]
