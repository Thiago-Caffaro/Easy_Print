#!/bin/sh

set -eu

php /app/bin/migrate.php

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

exec php \
    -d display_errors=Off \
    -d log_errors=On \
    -d max_file_uploads=1 \
    -d max_input_nesting_level=16 \
    -d max_input_vars=256 \
    -d post_max_size="${REQUEST_BODY_MAX_BYTES:-27262976}" \
    -d upload_max_filesize="${UPLOAD_MAX_BYTES:-26214400}" \
    -S 0.0.0.0:8080 \
    -t /app/public \
    /app/public/router.php
