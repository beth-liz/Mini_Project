# Use the official PHP 8.1 CLI image as a base image
FROM php:8.1-cli

# Set the working directory inside the container
WORKDIR /app

# Install the mysqli extension (for database access)
RUN docker-php-ext-install mysqli

# Copy all the application files into the container's /app directory
COPY . /app

# Expose port 10000 for accessing the PHP server
EXPOSE 10000

# Start a PHP built-in server on port 10000 and listen on all IPs (0.0.0.0)
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
