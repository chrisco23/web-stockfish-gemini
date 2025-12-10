# Stage 1: Standard FrankenPHP Runtime Image
FROM dunglas/frankenphp

# Copy the application files into the web root
COPY . /app/public

# Set correct permissions for the web server user (www-data)
RUN chown -R www-data:www-data /app

# Expose ports (for HTTP-01 challenge)
EXPOSE 80 443
