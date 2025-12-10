# Stage 1: Build Caddy with the Cloudflare DNS module
# Use the official Caddy builder image for reliable compilation and xcaddy
FROM caddy:builder-alpine AS caddy_builder

# Compile a new Caddy binary with the Cloudflare DNS module
# This uses the latest stable version of the module by omitting the version tag.
RUN xcaddy build \
    --output /usr/bin/caddy \
    --with github.com/caddy-dns/cloudflare

# Stage 2: Final FrankenPHP Runtime Image
# Use the small, efficient FrankenPHP base for the final environment
FROM dunglas/frankenphp:latest-alpine

# Copy the custom Caddy binary (with the Cloudflare plugin) from the builder stage
# This step overwrites the default Caddy binary in the FrankenPHP image.
COPY --from=caddy_builder /usr/bin/caddy /usr/local/bin/caddy

# Copy the application files into the web root
# We are assuming your local 'frontend' directory is the build context.
COPY . /app/public

# Set correct permissions for the web server user (www-data)
RUN chown -R www-data:www-data /app

# Expose ports (good practice, though Docker Compose handles the mapping)
EXPOSE 80 443
