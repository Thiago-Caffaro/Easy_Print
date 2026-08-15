# Security Policy

## Supported versions

Easy Print is currently pre-release. Security fixes are applied to the default branch until the first stable release. After v1.0, this section will list supported release lines explicitly.

## Reporting a vulnerability

Use GitHub private vulnerability reporting for this repository. Do not disclose command injection, path traversal, unsafe upload handling, network exposure, or sensitive logging findings in a public Issue.

Include:

- A concise description and impact.
- Reproduction conditions and affected revision.
- A minimal proof of concept that does not print documents or damage data.
- Suggested mitigation, if known.

The maintainer will acknowledge a complete report as soon as practical, validate the finding, and coordinate disclosure after a fix is available.

## Security boundary

v1.0 is intended for a trusted LAN or Tailscale network and has no application login. It must not be exposed directly to the public internet. Deployments remain responsible for network access controls, TLS termination, host security, CUPS hardening, driver provenance, and timely updates. Follow the versioned [private network deployment patterns](docs/network-deployment.md); Tailscale Funnel and equivalent public tunnels are explicitly unsupported.
