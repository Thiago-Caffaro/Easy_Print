#!/bin/sh

set -eu
umask 077

if [ "$#" -ne 2 ]; then
    printf '%s\n' 'Usage: capture-cups-fixtures.sh <queue-name> <private-output-directory>' >&2
    exit 64
fi

queue_name=$1
output_directory=$2

case "$queue_name" in
    ''|*[!A-Za-z0-9._-]*)
        printf '%s\n' 'Queue name contains unsupported characters.' >&2
        exit 64
        ;;
esac

mkdir -p "$output_directory"

command -v lpstat >/dev/null 2>&1
command -v lpoptions >/dev/null 2>&1

LC_ALL=C lpstat -r -d -v -p >"$output_directory/lpstat-queues.txt" 2>&1
LC_ALL=C lpoptions -p "$queue_name" -l >"$output_directory/lpoptions-capabilities.txt" 2>&1
LC_ALL=C lpstat -W not-completed -o "$queue_name" >"$output_directory/lpstat-active-jobs.txt" 2>&1
LC_ALL=C lpstat -W completed -o "$queue_name" >"$output_directory/lpstat-completed-jobs.txt" 2>&1

{
    cups-config --version 2>/dev/null || true
    lpstat --version 2>/dev/null || true
    uname -srm
} >"$output_directory/environment.txt"

printf '%s\n' 'Raw CUPS output captured. Do not commit it before manual sanitization and review.'
