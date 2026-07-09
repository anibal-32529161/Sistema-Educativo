FROM dunglas/frankenphp:php8.4.23-bookworm

# Install pdo_mysql extension
RUN docker-php-ext-install pdo_mysql

# Copy application files
COPY . /app

# Set working directory
WORKDIR /app

# Expose port 8080
EXPOSE 8080

# Start FrankenPHP
CMD ["frankenphp", "run", "--bind", "0.0.0.0:8080"]
