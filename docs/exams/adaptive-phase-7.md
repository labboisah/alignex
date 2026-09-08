# Phase 7: diagnostic offline delivery and rollout operations

Updated: 8 September 2026.

Online and offline supervised diagnostic pilot implementation is now available. See [the pilot runbook](adaptive-pilot.md) for deployment, supported settings, authorization, candidate operation, recovery, reconciliation and rollback. This supersedes the earlier containment-only Phase 7 status; it does not complete consequential assessment validation.

## Implemented

- Explicit exam-level online/offline approvals and audit history, with an emergency cloud stop.
- Formative assessment/practice pilots for all five owner contexts; secondary terminal exams remain traditional.
- Separate versioned center-bound HMAC packages, immutable snapshots and unique candidate delivery leases.
- Encrypted local packages/state/transcripts, transactional commands, retry protection, server deadlines, clock rollback detection and device reset without resetting scores.
- Offline candidate and supervisor interfaces in both the center web build and candidate app.
- Difficulty/coverage selection, fresh weakness-only recovery and configurable percentage penalties with integer ledger conservation.
- Cloud deterministic replay, verified/quarantined results, immutable accepted uploads and separate diagnostic reporting.
- Operations aggregates for offline status alongside online cohorts and shadow runs.
- Continued rejection of adaptive data on the traditional fixed-paper export/import paths.

The offline engine is diagnostic-pilot-v1. Online retains its existing Laravel diagnostic engine. Neither becomes the calibrated consequential engine merely because the diagnostic pilot is enabled.

## Remaining acceptance

Distribute reviewed client/server builds and rehearse on target center hardware. Benchmark capacity and establish measured latency/error thresholds. Preserve local administrative audit journals with the center backup. Full asymmetric package signing/key rotation, center transfer and complete centralized local audit ingestion are future hardening work, not capabilities of this HMAC pilot.

Phase 6 still requires representative real-response calibration, specialist criteria and approval before consequential decisions. Synthetic validation remains a demonstration. Cloud replay verifies consistency with frozen inputs; it cannot prove an untrusted center observed real candidate responses.

## Verification

The pilot tests exercise five-context approval/reservation, online delivery exclusion, exact replay, conflicting uploads, quarantine and traditional boundaries. Node tests cover ledger conservation, freshness, retries, stale commands, timing and stop conditions. Offline tests use real SQLite through Electron's native ABI. Browser checks include durable drafts, a lost acknowledgement retry and recovery completion.

For reproducible commands and final results, see the verification section in [the pilot runbook](adaptive-pilot.md). Earlier phase documents describe their historical increments.
