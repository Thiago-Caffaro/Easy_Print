#!/bin/sh

set -eu

php /app/bin/migrate.php
exec php -d display_errors=Off -d log_errors=On -S 0.0.0.0:8080 -t /app/public /app/public/router.php
