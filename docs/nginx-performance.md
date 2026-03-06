Recommended nginx configuration for static asset caching.

location /build/assets/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

location /assets/images/ {
    expires 30d;
    add_header Cache-Control "public";
}
