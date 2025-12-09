# /home/fugbert/www/web-stockfish-gemini/frontend/Dockerfile
#
# Stage 1: Alpine Builder - Compiles a custom Caddy/FrankenPHP binary with the Cloudflare DNS module
FROM dunglas/frankenphp:1-php8.3-alpine-builder AS caddy_builder
# You may need to adjust the tag (e.g., :latest-alpine-builder or specific php8.x-alpine-builder)
# The `1-php8.3-alpine-builder` tag is often a reliable Alpine option.

# Use xcaddy to build the custom FrankenPHP binary
RUN CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    xcaddy build \
        --output /usr/local/bin/frankenphp \
        --with github.com/dunglas/frankenphp/caddy \
        --with github.com/caddy-dns/cloudflare

# Stage 2: Final Alpine Runtime Image
FROM dunglas/frankenphp:1-php8.3-alpine
# Adjust the tag here to match the builder's PHP version

# Copy the custom-built binary from the builder stage
COPY --from=caddy_builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp

# FrankenPHP already copies your application files based on the volume in docker-compose.
# We will rely on the `volumes` and `Caddyfile` definition in the docker-compose.yml.prod
