# Stage 1: Build Caddy with the Cloudflare DNS module
# Use the official Caddy builder image for reliable compilation
FROM caddy:builder-alpine AS caddy_builder

# Compile a new Caddy binary with the Cloudflare DNS module
RUN xcaddy build \
    --output /usr/bin/caddy \
    --with github.com/caddy-dns/cloudflare@v0.1.1

# Stage 2: Final FrankenPHP Runtime Image
# Use the small, efficient FrankenPHP base
FROM dunglas/frankenphp:latest-alpine

# Copy the custom Caddy binary (with Cloudflare plugin) from the builder stage
# We overwrite the default Caddy binary in the FrankenPHP image
COPY --from=caddy_builder /usr/bin/caddy /usr/local/bin/caddy

# Copy the frontend files into the web root
COPY . /app/public

# Set correct permissions
RUN chown -R www-data:www-data /app

# Expose ports
EXPOSE 80 443

