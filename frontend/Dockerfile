# /home/fugbert/www/web-stockfish-gemini/frontend/Dockerfile
#
# Stage 1: Alpine Builder - Uses the latest Alpine builder tag
# NOTE: This tag is more likely to exist than the specific '1-php8.3-alpine-builder' tag.
FROM dunglas/frankenphp:latest-alpine-builder AS caddy_builder 

# Use xcaddy to build the custom FrankenPHP binary
RUN CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    xcaddy build \
        --output /usr/local/bin/frankenphp \
        --with github.com/dunglas/frankenphp/caddy \
        --with github.com/caddy-dns/cloudflare

# Stage 2: Final Alpine Runtime Image
# Uses the corresponding latest Alpine runtime image
FROM dunglas/frankenphp:latest-alpine

# Copy the custom-built binary from the builder stage
COPY --from=caddy_builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp

# FrankenPHP relies on the volume mount in docker-compose for the application files.
# COPY commands for application files are not strictly needed here if you rely entirely 
# on the volume mounts defined in docker-compose.yml.prod.
