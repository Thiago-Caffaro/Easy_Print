#!/bin/sh

set -eu

php /app/bin/migrate.php

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

exec php -d display_errors=Off -d log_errors=On -S 0.0.0.0:8080 -t /app/public /app/public/router.php
