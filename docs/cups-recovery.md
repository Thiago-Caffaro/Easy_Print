# CUPS recovery contract

Easy Print treats CUPS as the source of truth and discovers external state on every request or polling cycle. It never assumes that a queue, capability snapshot, or job remains valid after the browser renders a page.

| External change | Application behavior | Safety property |
| --- | --- | --- |
| Scheduler unavailable, unauthorized, timed out, or malformed | Render a dependency-unavailable state and use the slower active-job retry cadence | No empty queue or successful print is inferred from a failed query |
| Queue removed or renamed | Queue selection resolver drops the stale selection; submission validation returns `queue_unavailable`/`queue_changed` | No command is invoked for an unknown queue |
| Driver capabilities changed | The form fingerprint no longer matches; stale values are rejected with `stale_capabilities` and the form is refreshed | Browser values never bypass the current option allowlist |
| A queue state lookup fails | Keep known active jobs visible with `unknown` state | The UI does not claim pending or processing without evidence |
| Cancellation target disappears | Return a safe not-found result; no second command is issued | Stale job IDs cannot cancel a different job |
| Submission response is ambiguous | Persist an indeterminate metadata state and never retry automatically | A timeout cannot duplicate a physical print |
| CUPS returns after an outage | The next discovery/readiness cycle uses the dependency again | Recovery requires no application restart |

The corresponding tests use changing fake snapshots and process results. Local SQLite records are updated only after the external outcome is classified, and CUPS queue/job state is never reconstructed from stale local metadata. A physical recovery run against the Epson L4150 remains an opt-in compatibility check, not a prerequisite for the safety contract.
