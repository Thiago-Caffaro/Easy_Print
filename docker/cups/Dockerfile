FROM debian:bookworm-slim

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        avahi-daemon \
        ca-certificates \
        cups \
        cups-client \
        dbus \
    && rm -rf /var/lib/apt/lists/*

COPY docker/cups/cupsd.conf /etc/cups/cupsd.conf
COPY --chmod=0755 docker/cups/entrypoint.sh /usr/local/bin/easy-print-cups-entrypoint

EXPOSE 631
VOLUME ["/etc/cups", "/var/spool/cups"]

HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=5 \
    CMD lpstat -h localhost:631 -r || exit 1

ENTRYPOINT ["/usr/local/bin/easy-print-cups-entrypoint"]
