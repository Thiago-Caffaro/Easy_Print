# ADR 0001: Separate web and CUPS containers

- Status: Accepted
- Date: 2026-08-14

## Context

The product is distributed as one Docker Compose package, but the web application and print server have different privileges, dependencies, and lifecycles. CUPS may require USB, host networking, drivers, or printer applications. The web application requires only CUPS client access and private application storage.

## Decision

Provide separate `web` and `cups` services. Avahi belongs with the CUPS side of the boundary. Do not mount printer USB devices or the full CUPS spool into the web container.

## Consequences

- The security boundary is clearer and the web image stays small.
- Linux Docker remains the portable primary target.
- TrueNAS SCALE needs platform-specific networking, storage, and USB documentation.
- Compose configuration must expose only the network and volumes required by each service.
