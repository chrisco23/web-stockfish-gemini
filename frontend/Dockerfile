# Stage 1: Final Alpine Runtime Image
# Use the latest Alpine variant of FrankenPHP
FROM dunglas/frankenphp:latest-alpine

# Install the Cloudflare DNS challenge module into the Caddy binary.
# The 'caddy add-package' command is available in this image and is the simplest way.
# We must use a separate RUN command for this to work correctly.
RUN caddy add-package github.com/caddy-dns/cloudflare@v0.1.1

# Copy the frontend files into the correct web root
# We are creating the /app/public directory here and copying the contents of the local frontend directory into it.
COPY . /app/public

# Set correct permissions for the web server user (www-data)
# This prevents permission errors when PHP or Caddy tries to serve files
RUN chown -R www-data:www-data /app

# Expose ports (not strictly necessary but good practice)
EXPOSE 80 443
