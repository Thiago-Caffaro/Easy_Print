# Sanitized CUPS fixtures

Fixtures make CUPS parsing deterministic without contacting a printer during normal tests. The Epson L4150 environment is the first evidence source, not a universal behavior definition.

## Current state

The captured-fixture contract and capture procedure are versioned here. No Epson L4150 command output has been committed yet because the current development machine does not have working CUPS client tools. Issue #3 remains open until output is captured from the real server, sanitized, reviewed, paired with expected normalized JSON, and exercised by parser contract tests.

Do not replace missing evidence with invented output.

Synthetic fixtures under `Contract/` exercise protocol-shape edge cases and are labeled `synthetic-contract` inside each file. They are test inputs, not evidence about a physical printer, driver, or CUPS version. Captured evidence and synthetic examples must remain visibly separate.

The capability contracts cover the categories the product can safely render, missing categories, and preserved-but-non-renderable driver-specific options. The submission contract covers the exact neutral one-file response shape used to parse a job identifier. They do not assert that the Epson reference device or any other printer supports those features or response variants.

## Layout

Each captured scenario will contain:

```text
<scenario>/
├── manifest.json
├── source.txt
└── expected.json
```

The manifest must satisfy `fixture.schema.json`. Queue names become neutral aliases such as `REFERENCE_QUEUE`; addresses, usernames, document names, tokens, and private paths must not survive sanitization.

## Capture workflow

1. Run `scripts/capture-cups-fixtures.sh` on a Linux host that has CUPS client tools and can reach the reference server.
2. Keep the generated `raw/` directory outside version control.
3. Replace private values consistently and remove data unrelated to the parser contract.
4. Record the CUPS version, driver or printer-application family, locale, and exact argument array.
5. Create the expected normalized JSON beside the sanitized source.
6. Have a second review confirm sanitization before changing `sanitizationReviewed` to `true`.
7. Run parser contract tests. They must not contact CUPS or submit a document.

Submission, cancellation, and physical job lifecycle captures require explicit operator authorization because they can consume paper or ink. Record those scenarios manually; the read-only capture script intentionally does not print or cancel jobs.

## Required evidence

- scheduler availability, default destination, queue URI, and queue state;
- queue options and defaults from `lpoptions`;
- active and completed job listings;
- accepted submission output and parsed job identifier;
- completed and cancelled job states;
- representative stopped, offline, media, and unknown printer reasons;
- non-zero exit, timeout, empty, malformed, reordered, and unknown-field cases.

Additional devices and CUPS versions are expected to add fixtures where their output shape differs.
