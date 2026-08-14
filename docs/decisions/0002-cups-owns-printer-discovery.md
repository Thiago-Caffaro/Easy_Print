# ADR 0002: CUPS owns printer discovery

- Status: Accepted
- Date: 2026-08-14

## Context

Easy Print should eventually work with any printer represented by a CUPS queue. Duplicating mDNS, USB, driver, or queue-administration logic would turn the application into another print server and create model-specific behavior.

## Decision

Easy Print consumes queues already configured in one CUPS server. CUPS and Avahi discover devices and own drivers, queues, capabilities, and active job state. The application lists queues, normalizes advertised capabilities, and submits validated jobs.

## Consequences

- The domain is queue- and capability-oriented rather than printer-model-oriented.
- Epson L4150 is a reference validation device only.
- Queue administration is explicitly outside v1.0.
- Direct IPP is evaluated later only if it solves a proven integration limitation.
