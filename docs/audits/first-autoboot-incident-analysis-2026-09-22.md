# First Autoboot Incident Analysis - 22 September 2026

**Decision:** No-go for the 25-candidate offline capacity tier. Investigate and improve the existing runtime before considering an architecture redesign.

## Observed Run

The first synthetic full run used the approved 25-client capacity profile with a 15% backup margin:

- Target clients: 29.
- Questions per client: 100.
- Potential answer attempts: 2,900.
- The runtime recorded 29 disconnects, but the observed clients did not physically disconnect. This is an unverified application counter or state-transition signal, not network-disconnection evidence.
- Approximately half remained prepared; the rest entered active state.
- Active clients stopped at roughly 25-30% of the planned duration.
- The highest recorded progress was 26 answers; the lowest non-zero progress was 3 answers.
- About 15% of clients saved no answer. No client completed the paper.

This failed the drill's required outcome: every enrolled client must start, save answers, and submit under the frozen target load.

## Evidence-Based Assessment

The report establishes a failure in the path from preparation through answer acknowledgement. It does **not** identify the root cause because it contains no timestamped server metrics, client logs, request traces, socket lifecycle data, SQLite lock data, or per-client failure reasons. In particular, the recorded disconnect count must not be interpreted as a physical network failure unless it is correlated with socket disconnect reasons, heartbeat loss, and client-side evidence.

The Center Server and Candidate Client source that executed the drill are not included in this checkout. The present repository contains the portal package/reporting integration and drill specification, so it cannot prove whether the stall was caused by the simulator, server runtime, SQLite, LAN, or client runtime.

The current evidence does not justify an architecture redesign. First establish whether a bounded defect in connection handling, answer acknowledgement, database writes, retries, or the autoboot client driver explains the failure.

## Working Hypotheses

1. The prepared-to-active transition is failing through client registration, start-command delivery, session state handling, or a false disconnect/session-expiry counter.
2. The active-client stall is caused by an answer-save critical path becoming slow or blocked, with retries amplifying the load.
3. The simulator may be creating a burst pattern, request rate, or resource contention that differs from installed Candidate Clients.

Each hypothesis is falsifiable in the next run through correlated timestamps, request outcomes, socket disconnect reasons, and server resource measurements.

## Required Instrumentation Before Rerun

Capture these fields with a shared drill/client/attempt identifier and UTC timestamps:

- Client: prepared, registered, connected, start received, first answer, every answer acknowledgement, submit, disconnect, reconnect, terminal state, and error reason.
- Server: registration, manifest delivery, answer receipt, database commit, answer acknowledgement, submission, socket connect/disconnect reason, retry count, and unhandled error.
- System: server CPU, process memory, open sockets, request latency p50/p95/p99, error rate, SQLite busy/locked errors, database size, WAL size, free disk, and LAN packet loss/throughput.

Record the answer-save latency separately from client retry delay. Do not mark an answer successful until the server acknowledgement is received and recorded.

## Progressive Verification Plan

Run synthetic full drills using the same release candidates, Windows image, Center Server hardware, wired LAN, package version/hash, and 100-question paper:

1. 5 clients.
2. 10 clients.
3. 15 clients.
4. 20 clients.
5. 25 clients.
6. 29 clients.

At each level, stop and diagnose before increasing load when any client fails to activate, an answer acknowledgement times out, p95 answer-save latency breaches the configured threshold, a database lock error occurs, or any client cannot submit.

After a stable 29-client full run, execute separate reconnect, client restart, server restart, lost-acknowledgement, deadline, and report-upload retry scenarios. Do not use an official examination or official candidate data.

## Acceptance Gate

The 25-candidate tier can be considered for approval only when the 29-client run has complete evidence, all enrolled clients enter active state, all clients submit or have an explained controlled-failure outcome, no unauthorized or duplicate registrations occur, and the signed report uploads with an idempotent receipt.

The measured capacity must be treated as 25 candidates until the target tier passes on the intended hardware and LAN. This run is an incident record, not a production-capacity certification.