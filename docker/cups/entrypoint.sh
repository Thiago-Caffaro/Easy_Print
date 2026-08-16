#!/bin/sh

set -eu

mkdir -p /run/dbus /run/avahi-daemon
dbus-daemon --system --fork
avahi-daemon --daemonize

exec cupsd -f
