FROM dunglas/frankenphp:php8.4.23-bookworm

# Install pdo_mysql and mysqli extensions
RUN docker-php-ext-install mysqli pdo_mysql 

# Copy application files
COPY . /app

# Set working directory
WORKDIR /app


